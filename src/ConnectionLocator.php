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
    public const DEFAULT = 'DEFAULT';

    public const READ = 'READ';

    public const WRITE = 'WRITE';

    static public function new(mixed $arg, mixed ...$args) : static
    {
        if ($arg instanceof Connection) {
            $defaultFactory = function () use ($arg) {
                return $arg;
            };

            return new static($defaultFactory);
        }

        return new static(Connection::factory($arg, ...$args));
    }

    protected array $instances = [
        self::DEFAULT => null,
        self::READ => [],
        self::WRITE => [],
    ];

    protected ?Connection $read = null;

    protected ?Connection $write = null;

    protected bool $lockToWrite = false;

    protected bool $logQueries = false;

    protected array $queries = [];

    protected mixed $queryLogger = null;

    public function __construct(
        protected mixed /* callable */ $defaultFactory = null,
        protected array $readFactories = [],
        protected array $writeFactories = []
    ) {
    }

    public function setDefaultFactory(callable $factory) : void
    {
        $this->defaultFactory = $factory;
    }

    public function setReadFactory(
        string $name,
        callable $factory
    ) : void
    {
        $this->readFactories[$name] = $factory;
    }

    public function setWriteFactory(
        string $name,
        callable $factory
    ) : void
    {
        $this->writeFactories[$name] = $factory;
    }

    public function getDefault() : Connection
    {
        if ($this->instances[static::DEFAULT] === null) {
            $this->instances[static::DEFAULT] = $this->newConnection(
                $this->defaultFactory,
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
            $this->read = $this->getConnection(static::READ, $this->readFactories);
        }

        return $this->read;
    }

    public function getWrite() : Connection
    {
        if (! isset($this->write)) {
            $this->write = $this->getConnection(static::WRITE, $this->writeFactories);
        }

        return $this->write;
    }

    protected function getConnection(string $type, array $factories) : Connection
    {
        if (empty($factories)) {
            return $this->getDefault();
        }

        if (! empty($this->instances[$type])) {
            return reset($this->instances[$type]);
        }

        return $this->get($type, (string) array_rand($factories));
    }

    public function get(
        string $type,
        string $name
    ) : Connection
    {
        $prop = strtolower($type) . 'Factories';
        $factories = $this->$prop;

        if (! isset($factories[$name])) {
            throw Exception::connectionNotFound($type, $name);
        }

        if (! isset($this->instances[$type][$name])) {
            $this->instances[$type][$name] = $this->newConnection(
                $factories[$name],
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

    public function getQueries() : array
    {
        return $this->queries;
    }

    public function setQueryLogger(callable $queryLogger) : void
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
