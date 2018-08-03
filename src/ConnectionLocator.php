<?php
/**
 *
 * This file is part of Atlas for PHP.
 *
 * @license https://opensource.org/licenses/MIT MIT
 *
 */
declare(strict_types=1);

namespace Atlas\Pdo;

class ConnectionLocator
{
    const DEFAULT = 'DEFAULT';

    const READ = 'READ';

    const WRITE = 'WRITE';

    protected $factories = [
        self::DEFAULT => null,
        self::READ => [],
        self::WRITE => [],
    ];

    protected $instances = [
        self::DEFAULT => null,
        self::READ => [],
        self::WRITE => [],
    ];

    protected $read;

    protected $write;

    protected $lockToWrite = false;

    protected $logQueries = false;

    protected $queries = [];

    protected $queryLogger;

    public static function new(...$args)
    {
        if ($args[0] instanceof Connection) {
            return new ConnectionLocator(function () use ($args) {
                return $args[0];
            });
        }

        return new ConnectionLocator(Connection::factory(...$args));
    }

    public function __construct(
        callable $defaultFactory = null,
        array $readFactories = [],
        array $writeFactories = []
    ) {
        if ($defaultFactory) {
            $this->setDefaultFactory($defaultFactory);
        }

        foreach ($readFactories as $name => $factory) {
            $this->setReadFactory($name, $factory);
        }

        foreach ($writeFactories as $name => $factory) {
            $this->setWriteFactory($name, $factory);
        }
    }

    public function setDefaultFactory(callable $factory) : void
    {
        $this->factories[static::DEFAULT] = $factory;
    }

    public function setReadFactory(
        string $name,
        callable $factory
    ) : void
    {
        $this->factories[static::READ][$name] = $factory;
    }

    public function setWriteFactory(
        string $name,
        callable $factory
    ) : void
    {
        $this->factories[static::WRITE][$name] = $factory;
    }

    public function getDefault() : Connection
    {
        if ($this->instances[static::DEFAULT] === null) {
            $this->instances[static::DEFAULT] = $this->newConnection(
                $this->factories[static::DEFAULT],
                static::DEFAULT
            );
        }

        return $this->instances[static::DEFAULT];
    }

    public function getRead() : Connection
    {
        if ($this->lockToWrite) {
            return $this->getWrite();
        }

        if (! isset($this->read)) {
            $this->read = $this->getType(static::READ);
        }

        return $this->read;
    }

    public function getWrite() : Connection
    {
        if (! isset($this->write)) {
            $this->write = $this->getType(static::WRITE);
        }

        return $this->write;
    }

    protected function getType(string $type) : Connection
    {
        if (empty($this->factories[$type])) {
            return $this->getDefault();
        }

        if (! empty($this->instances[$type])) {
            return reset($this->instances[$type]);
        }

        return $this->get($type, array_rand($this->factories[$type]));
    }

    public function get(
        string $type,
        string $name
    ) : Connection
    {
        if (! isset($this->factories[$type][$name])) {
            throw Exception::connectionNotFound($type, $name);
        }

        if (! isset($this->instances[$type][$name])) {
            $this->instances[$type][$name] = $this->newConnection(
                $this->factories[$type][$name],
                "{$type}:{$name}"
            );
        }

        return $this->instances[$type][$name];
    }

    protected function newConnection(
        callable $factory,
        string $label
    ) : Connection
    {
        $connection = $factory();
        $queryLogger = function (array $entry) use ($label) : void {
            $entry = ['connection' => $label] + $entry;
            $this->addLogEntry($entry);
        };
        $connection->setQueryLogger($queryLogger);
        $connection->logQueries($this->logQueries);
        return $connection;
    }

    public function hasRead() : bool
    {
        return isset($this->read);
    }

    public function hasWrite() : bool
    {
        return isset($this->write);
    }

    public function lockToWrite(bool $lockToWrite = true) : void
    {
        $this->lockToWrite = $lockToWrite;
    }

    public function isLockedToWrite() : bool
    {
        return $this->lockToWrite;
    }

    public function logQueries(bool $logQueries = true) : void
    {
        if ($this->instances[static::DEFAULT] !== null) {
            $this->instances[static::DEFAULT]->logQueries($logQueries);
        }

        $types = [static::READ, static::WRITE];
        foreach ($types as $type) {
            foreach ($this->instances[$type] as $connection) {
                $connection->logQueries($logQueries);
            }
        }

        $this->logQueries = $logQueries;
    }

    public function getQueries()
    {
        return $this->queries;
    }

    public function setQueryLogger(callable $queryLogger)
    {
        $this->queryLogger = $queryLogger;
    }

    protected function addLogEntry(array $entry) : void
    {
        if ($this->queryLogger !== null) {
            ($this->queryLogger)($entry);
        } else {
            $this->queries[] = $entry;
        }
    }
}
