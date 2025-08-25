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

/**
 * @phpstan-type connectionStore array{
 *      DEFAULT: ?Connection,
 *      READ: array<string, ?Connection>,
 *      WRITE: array<string, ?Connection>
 * }
 * @phpstan-import-type logEntryType from Connection
 */
class ConnectionLocator
{
    /**
     * @var string
     */
    public const DEFAULT = 'DEFAULT';

    /**
     * @var string
     */
    public const READ = 'READ';

    /**
     * @var string
     */
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

    /**
     * @var connectionStore
     */
    protected array $instances = [
        self::DEFAULT => null,
        self::READ => [],
        self::WRITE => [],
    ];

    protected ?Connection $read = null;

    protected ?Connection $write = null;

    protected bool $lockToWrite = false;

    protected bool $logQueries = false;

    /**
     * @var logEntryType[]
     */
    protected array $queries = [];

    /**
     * @var callable|null
     */
    protected mixed $queryLogger = null;

    /**
     * @param callable $defaultFactory
     * @param array    $readFactories
     * @param array    $writeFactories
     */
    public function __construct(
        protected mixed $defaultFactory = null,
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
        /** @var 'DEFAULT' $type */
        $type = static::DEFAULT;

        if ($this->instances[$type] === null) {
            $this->instances[$type] = $this->newConnection(
                $this->defaultFactory,
                $type
            );
        }

        return $this->instances[$type];
    }

    public function getRead() : Connection
    {
        if ($this->lockToWrite) {
            return $this->getWrite();
        }

        if (! isset($this->read)) {
            $this->read = $this->getConnection(
                static::READ,
                $this->readFactories
            );
        }

        return $this->read;
    }

    public function getWrite() : Connection
    {
        if (! isset($this->write)) {
            $this->write = $this->getConnection(
                static::WRITE,
                $this->writeFactories
            );
        }

        return $this->write;
    }

    protected function getConnection(
        string $type,
        array $factories
    ) : Connection
    {
        if (empty($factories)) {
            return $this->getDefault();
        }

        if (! empty($this->instances[$type])) {
            /** @var array<string, array<string, Connection>> $instances */
            $instances = $this->instances;
            /** @var Connection $connection */
            $connection = reset($instances[$type]);

            return $connection;
        }

        return $this->get($type, (string) array_rand($factories));
    }

    public function get(
        string $type,
        string $name
    ) : Connection
    {
        $prop = strtolower($type) . 'Factories';
        /** @var array<string, callable> $factories */
        $factories = $this->$prop;

        if (! isset($factories[$name])) {
            throw Exception::connectionNotFound($type, $name);
        }

        /** @var 'READ'|'WRITE' $type */
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
        /** @var Connection $connection */
        $connection = $factory();

        $queryLogger = function (array $entry) use ($label) : void {
            /** @var logEntryType $entry */
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
        /** @var Connection|null $defaultConnection */
        $defaultConnection = $this->instances[static::DEFAULT] ?? null;
        if ($defaultConnection !== null) {
            $defaultConnection->logQueries($logQueries);
        }

        $types = [static::READ, static::WRITE];

        foreach ($types as $type) {
            /** @var array<string, Connection> $instances */
            $instances = $this->instances[$type];
            foreach ($instances as $connection) {
                $connection->logQueries($logQueries);
            }
        }

        $this->logQueries = $logQueries;
    }

    /**
     * @return logEntryType[]
     */
    public function getQueries() : array
    {
        return $this->queries;
    }

    public function setQueryLogger(callable $queryLogger) : void
    {
        $this->queryLogger = $queryLogger;
    }

    /**
     * @param logEntryType $entry
     *
     * @return void
     */
    protected function addLogEntry(array $entry) : void
    {
        /** @var callable|null $queryLogger */
        $queryLogger = $this->queryLogger;
        if ($queryLogger !== null) {
            $queryLogger($entry);
        } else {
            $this->queries[] = $entry;
        }
    }
}
