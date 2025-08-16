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

use Generator;
use PDO;
use PDOStatement;

/**
 * Decorator for PDO instances.
 *
 * @method mixed errorCode()
 * @method array errorInfo()
 * @method mixed getAttribute(int $attribute)
 * @method bool inTransaction()
 * @method string lastInsertId(string $name = null)
 * @method string quote(mixed $string, int $parameterType = PDO::PARAM_STR)
 * @method mixed setAttribute(int $attribute, mixed $value)
 *
 * @phpstan-type dsnArgs array{
 *      dsn: string,
 *      username?: string,
 *      password?: string,
 *      options?: array<int, mixed>
 *  }
 *
 * @phpstan-type logEntry array{
 *      start: float,
 *      finish: ?float,
 *      duration: ?float,
 *      performed: ?bool,
 *      statement: ?string,
 *      values: array<array-key, mixed>,
 *      trace: ?string,
*       connection?: string
 * }
 */
class Connection
{
    /**
     * @param mixed ...$args
     *
     * @return Connection
     */
    static public function new(mixed ...$args) : Connection
    {
        if ($args[0] instanceof PDO) {
            return new static($args[0]);
        }

        /** @var dsnArgs $args */
        return new static(new PDO(...$args));
    }

    /**
     * @param mixed ...$args
     *
     * @return callable
     */
    static public function factory(mixed ...$args) : callable
    {
        return function () use ($args) {
            return static::new(...$args);
        };
    }

    /**
     * @var bool
     */
    protected bool $logQueries = false;

    /**
     * @var bool
     */
    protected bool $persistent = false;

    /**
     * @var array
     */
    protected array $queries = [];

    /**
     * @var callable|null
     */
    protected mixed /* callable */ $queryLogger = null;

    /**
     * @param PDO $pdo
     */
    public function __construct(protected PDO $pdo)
    {
        $this->persistent = (bool)$this->pdo->getAttribute(PDO::ATTR_PERSISTENT);
    }

    /**
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(
        string $method,
        array $arguments
    ) : mixed
    {
        return $this->pdo->$method(...$arguments);
    }

    /**
     * @return string
     */
    public function getDriverName() : string
    {
        /** @var string $driverName */
        $driverName = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return $driverName;
    }

    /**
     * @return PDO
     */
    public function getPdo() : PDO
    {
        return $this->pdo;
    }

    /* Transactions */

    /**
     * @return bool
     */
    public function beginTransaction() : bool
    {
        $entry = $this->newLogEntry(__METHOD__);
        $result = $this->pdo->beginTransaction();
        $this->addLogEntry($entry);
        return $result;
    }

    /**
     * @return bool
     */
    public function commit() : bool
    {
        $entry = $this->newLogEntry(__METHOD__);
        $entry['performed'] = false;

        try {
            $result = $this->pdo->commit();
            $entry['performed'] = true;
        } finally {
            $this->addLogEntry($entry);
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function rollBack() : bool
    {
        $entry = $this->newLogEntry(__METHOD__);
        $result = $this->pdo->rollBack();
        $this->addLogEntry($entry);
        return $result;
    }

    /* Queries */

    /**
     * @param string $statement
     *
     * @return int|false
     */
    public function exec(string $statement) : int|false
    {
        $entry = $this->newLogEntry($statement);
        $rowCount = $this->pdo->exec($statement);
        $this->addLogEntry($entry);
        return $rowCount;
    }

    /**
     * @param string $statement
     * @param array  $driverOptions
     *
     * @return PDOStatement
     */
    public function prepare(
        string $statement,
        array $driverOptions = []
    ) : PDOStatement
    {
        $sth = $this->pdo->prepare($statement, $driverOptions);

        if ($this->logQueries && $this->persistent) {
            $sth = PersistentLoggedStatement::new(
                $sth,
                function (array $entry) : void {
                    /** @var logEntry $entry */
                    $this->addLogEntry($entry);
                },
                $this->newLogEntry()
            );
        }

        return $sth;
    }

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return PDOStatement
     */
    public function perform(
        string $statement,
        array $values = []
    ) : PDOStatement
    {
        $sth = $this->prepare($statement);

        foreach ($values as $name => $args) {
            $this->performBind($sth, $name, $args);
        }

        $sth->execute();
        return $sth;
    }

    /**
     * @param PDOStatement $sth
     * @param mixed        $name
     * @param mixed        $args
     *
     * @return void
     */
    protected function performBind(
        PDOStatement $sth,
        mixed $name,
        mixed $args
    ) : void
    {
        if (is_int($name)) {
            // sequential placeholders are 1-based
            $name ++;
        }

        if (! is_array($args)) {
            /** @var int|string $name */
            $sth->bindValue($name, $args);
            return;
        }

        $type = $args[1] ?? PDO::PARAM_STR;

        if ($type === PDO::PARAM_BOOL && is_bool($args[0])) {
            $args[0] = $args[0] ? '1' : '0';
        }

        /** @var int|string $name */
        $sth->bindValue($name, ...$args);
    }

    /**
     * @param string $statement
     * @param mixed  ...$fetch
     *
     * @return PDOStatement|false
     */
    public function query(string $statement, mixed ...$fetch) : PDOStatement|false
    {
        $entry = $this->newLogEntry($statement);
        $sth = $this->pdo->query($statement, ...$fetch);
        $this->addLogEntry($entry);
        return $sth;
    }

    /* Fetching */

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return int
     */
    public function fetchAffected(
        string $statement,
        array $values = []
    ) : int
    {
        $sth = $this->perform($statement, $values);
        return $sth->rowCount();
    }

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return array|false
     */
    public function fetchAll(
        string $statement,
        array $values = []
    ) : array|false
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $statement
     * @param array  $values
     * @param int    $column
     *
     * @return array|false
     */
    public function fetchColumn(
        string $statement,
        array $values = [],
        int $column = 0
    ) : array|false
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_COLUMN, $column);
    }

    /**
     * @param string $statement
     * @param array  $values
     * @param int    $style
     *
     * @return array|false
     */
    public function fetchGroup(
        string $statement,
        array $values = [],
        int $style = PDO::FETCH_COLUMN
    ) : array|false
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_GROUP | $style);
    }

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return array|false
     */
    public function fetchKeyPair(
        string $statement,
        array $values = []
    ) : array|false
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * @template T of object
     *
     * @param string          $statement
     * @param array           $values
     * @param class-string<T> $class
     * @param array<mixed>    ...$args
     *
     * @return T|false
     */
    public function fetchObject(
        string $statement,
        array $values = [],
        string $class = 'stdClass',
        mixed ...$args
    ) : object|false
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchObject($class, ...$args);
    }

    /**
     * @template T of object
     * *
     * @param string          $statement
     * @param array           $values
     * @param class-string<T> $class
     * @param array<mixed>    ...$args
     *
     * @return array|false
     */
    public function fetchObjects(
        string $statement,
        array $values = [],
        string $class = 'stdClass',
        mixed ...$args
    ) : array|false
    {
        $sth = $this->perform($statement, $values);
        /** @var array<array-key, callable|int|string> $args */
        return $sth->fetchAll(PDO::FETCH_CLASS, $class, ...$args);
    }

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return array|false
     */
    public function fetchOne(
        string $statement,
        array $values = []
    ) : array|false
    {
        $sth    = $this->perform($statement, $values);
        /** @var array<array-key, mixed> $result */
        $result = $sth->fetch(PDO::FETCH_ASSOC);

        return $result;
    }

    /**
     * @param string $statement
     * @param array  $values
     * @param int    $column
     *
     * @return mixed
     */
    public function fetchValue(
        string $statement,
        array $values = [],
        int $column = 0
    ) : mixed
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchColumn($column);
    }

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return array|false
     */
    public function fetchUnique(
        string $statement,
        array $values = []
    ) : array|false
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_UNIQUE);
    }

    /* Yielding */

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return Generator
     */
    public function yieldAll(
        string $statement,
        array $values = []
    ) : Generator
    {
        $sth = $this->perform($statement, $values);

        while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return Generator
     */
    public function yieldUnique(
        string $statement,
        array $values = []
    ) : Generator
    {
        $sth = $this->perform($statement, $values);

        while ($row = $sth->fetch(PDO::FETCH_UNIQUE)) {
            /** @var array<string, mixed> $row */
            $key = array_shift($row);
            yield $key => $row;
        }
    }

    /**
     * @param string $statement
     * @param array  $values
     * @param int    $column
     *
     * @return Generator
     */
    public function yieldColumn(
        string $statement,
        array $values = [],
        int $column = 0
    ) : Generator
    {
        $sth = $this->perform($statement, $values);

        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
            /** @var array<int, mixed> $row */
            yield $row[$column];
        }
    }

    /**
     * @template T of object
     *
     * @param string          $statement
     * @param array           $values
     * @param class-string<T> $class
     * @param array<mixed>    ...$args
     *
     * @return Generator
     */
    public function yieldObjects(
        string $statement,
        array $values = [],
        string $class = 'stdClass',
        mixed ...$args
    ) : Generator
    {
        $sth = $this->perform($statement, $values);

        while ($instance = $sth->fetchObject($class, ...$args)) {
            yield $instance;
        }
    }

    /**
     * @param string $statement
     * @param array  $values
     *
     * @return Generator
     */
    public function yieldKeyPair(
        string $statement,
        array $values = []
    ) : Generator
    {
        $sth = $this->perform($statement, $values);

        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
            /** @var array<int, mixed> $row */
            yield $row[0] => $row[1];
        }
    }

    /* Logging */

    /**
     * @param bool $logQueries
     *
     * @return void
     */
    public function logQueries(bool $logQueries = true) : void
    {
        $this->logQueries = $logQueries;

        if ($this->persistent) {
            return;
        }

        if (! $this->logQueries) {
            $this->pdo->setAttribute(
                PDO::ATTR_STATEMENT_CLASS,
                [PDOStatement::CLASS]
            );
            return;
        }

        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [
            LoggedStatement::CLASS,
            [
                function (array $entry) : void {
                    /** @var logEntry $entry */
                    $this->addLogEntry($entry);
                },
                $this->newLogEntry()
            ]
        ]);
    }

    /**
     * @return array
     */
    public function getQueries() : array
    {
        return $this->queries;
    }

    /**
     * @param callable $queryLogger
     *
     * @return void
     */
    public function setQueryLogger(callable $queryLogger) : void
    {
        $this->queryLogger = $queryLogger;
    }

    /**
     * @param string|null $statement
     *
     * @return logEntry
     */
    protected function newLogEntry(string $statement = null) : array
    {
        return [
            'start'     => microtime(true),
            'finish'    => null,
            'duration'  => null,
            'performed' => null,
            'statement' => $statement,
            'values'    => [],
            'trace'     => null,
        ];
    }

    /**
     * @param logEntry $entry
     *
     * @return void
     */
    protected function addLogEntry(array $entry) : void
    {
        if (! $this->logQueries) {
            return;
        }

        if ($entry['performed'] === null) {
            $entry['performed'] = true;
        }

        $entry['finish'] = microtime(true);
        $entry['duration'] = $entry['finish'] - $entry['start'];
        $entry['trace'] = (new \Exception())->getTraceAsString();

        if ($this->queryLogger === null) {
            $this->queries[] = $entry;
            return;
        }

        ($this->queryLogger)($entry);
    }
}
