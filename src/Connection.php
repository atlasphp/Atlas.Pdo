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

use Exception;
use Generator;
use PDO;
use PDOStatement;

/**
 * Decorator for PDO instances.
 *
 * @method mixed errorCode()
 * @method array errorInfo()
 * @method mixed getAttribute($attribute)
 * @method bool inTransaction()
 * @method string lastInsertId(string $name = null)
 * @method string quote($value, int $dataType = PDO::PARAM_STR)
 * @method mixed setAttribute($attribute, $value)
 */
class Connection
{
    protected $pdo;

    protected $logQueries = false;

    protected $queries = [];

    protected $queryLogger;

    public static function new(...$args) : Connection
    {
        if ($args[0] instanceof PDO) {
            return new static($args[0]);
        }

        return new static(new PDO(...$args));
    }

    public static function factory(...$args) : callable
    {
        return function () use ($args) {
            return static::new(...$args);
        };
    }

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function __call(
        string $method,
        array $params
    ) {
        return $this->pdo->$method(...$params);
    }

    public function getDriverName() : string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function getPdo() : PDO
    {
        return $this->pdo;
    }

    /* Transactions */

    public function beginTransaction() : bool
    {
        $entry = $this->newLogEntry(__METHOD__);
        $result = $this->pdo->beginTransaction();
        $this->addLogEntry($entry);
        return $result;
    }

    public function commit() : bool
    {
        $entry = $this->newLogEntry(__METHOD__);
        $result = $this->pdo->commit();
        $this->addLogEntry($entry);
        return $result;
    }

    public function rollBack() : bool
    {
        $entry = $this->newLogEntry(__METHOD__);
        $result = $this->pdo->rollBack();
        $this->addLogEntry($entry);
        return $result;
    }

    /* Queries */

    public function exec(string $statement) : int
    {
        $entry = $this->newLogEntry($statement);
        $rowCount = $this->pdo->exec($statement);
        $this->addLogEntry($entry);
        return $rowCount;
    }

    public function prepare(
        string $statement,
        array $driverOptions = []
    ) : PDOStatement
    {
        $sth = $this->pdo->prepare($statement, $driverOptions);
        if ($sth instanceof LoggedStatement) {
            $sth->setLogEntry($this->newLogEntry($statement));
            $sth->setQueryLogger(function (array $entry) : void {
                $this->addLogEntry($entry);
            });
        }
        return $sth;
    }

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

    protected function performBind(PDOStatement $sth, $name, $args)
    {
        if (is_int($name)) {
            // sequential placeholders are 1-based
            $name ++;
        }

        if (! is_array($args)) {
            $sth->bindValue($name, $args);
            return;
        }

        $type = $args[1] ?? PDO::PARAM_STR;
        if ($type === PDO::PARAM_BOOL && is_bool($args[0])) {
            $args[0] = $args[0] ? '1' : '0';
        }

        $sth->bindValue($name, ...$args);
    }

    public function query(string $statement, ...$fetch) : PDOStatement
    {
        $entry = $this->newLogEntry($statement);
        $sth = $this->pdo->query($statement, ...$fetch);
        $this->addLogEntry($entry);
        return $sth;
    }

    /* Fetching */

    public function fetchAffected(
        string $statement,
        array $values = []
    ) : int
    {
        $sth = $this->perform($statement, $values);
        return $sth->rowCount();
    }

    public function fetchAll(
        string $statement,
        array $values = []
    ) : array
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchUnique(
        string $statement,
        array $values = []
    ) : array
    {
        $sth  = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_UNIQUE);
    }

    public function fetchColumn(
        string $statement,
        array $values = [],
        int $column = 0
    ) : array
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_COLUMN, $column);
    }

    public function fetchGroup(
        string $statement,
        array $values = [],
        int $style = PDO::FETCH_COLUMN
    ) : array
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_GROUP | $style);
    }

    public function fetchObject(
        string $statement,
        array $values = [],
        string $class = 'stdClass',
        array $ctorArgs = []
    ) {
        $sth = $this->perform($statement, $values);

        if (! empty($ctorArgs)) {
            return $sth->fetchObject($class, $ctorArgs);
        }

        return $sth->fetchObject($class);
    }

    public function fetchObjects(
        string $statement,
        array $values = [],
        string $class = 'stdClass',
        array $ctorArgs = []
    ) : array
    {
        $sth = $this->perform($statement, $values);

        if (! empty($ctorArgs)) {
            return $sth->fetchAll(PDO::FETCH_CLASS, $class, $ctorArgs);
        }

        return $sth->fetchAll(PDO::FETCH_CLASS, $class);
    }

    public function fetchOne(
        string $statement,
        array $values = []
    ) : ?array
    {
        $sth = $this->perform($statement, $values);
        $result = $sth->fetch(PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }
        return $result;
    }

    public function fetchKeyPair(
        string $statement,
        array $values = []
    ) : array
    {
        $sth = $this->perform($statement, $values);
        return $sth->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function fetchValue(
        string $statement,
        array $values = [],
        int $column = 0
    ) {
        $sth = $this->perform($statement, $values);
        return $sth->fetchColumn($column);
    }

    /* Yielding */

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

    public function yieldUnique(
        string $statement,
        array $values = []
    ) : Generator
    {
        $sth = $this->perform($statement, $values);
        while ($row = $sth->fetch(PDO::FETCH_UNIQUE)) {
            $key = array_shift($row);
            yield $key => $row;
        }
    }

    public function yieldColumn(
        string $statement,
        array $values = [],
        int $column = 0
    ) : Generator
    {
        $sth = $this->perform($statement, $values);
        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
            yield $row[$column];
        }
    }

    public function yieldObjects(
        string $statement,
        array $values = [],
        string $class = 'stdClass',
        array $ctorArgs = []
    ) : Generator
    {
        $sth = $this->perform($statement, $values);

        if (empty($ctorArgs)) {
            while ($instance = $sth->fetchObject($class)) {
                yield $instance;
            }
        } else {
            while ($instance = $sth->fetchObject($class, $ctorArgs)) {
                yield $instance;
            }
        }
    }

    public function yieldKeyPair(
        string $statement,
        array $values = []
    ) : Generator
    {
        $sth = $this->perform($statement, $values);
        while ($row = $sth->fetch(PDO::FETCH_NUM)) {
            yield $row[0] => $row[1];
        }
    }

    /* Logging */

    public function logQueries(bool $logQueries = true) : void
    {
        $this->logQueries = $logQueries;

        $statementClass = ($this->logQueries)
            ? LoggedStatement::CLASS
            : PDOStatement::CLASS;

        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [$statementClass]);
    }

    public function getQueries()
    {
        return $this->queries;
    }

    public function setQueryLogger(callable $queryLogger) : void
    {
        $this->queryLogger = $queryLogger;
    }

    protected function newLogEntry(string $statement) : array
    {
        return [
            'start' => microtime(true),
            'finish' => null,
            'duration' => null,
            'statement' => $statement,
            'values' => [],
            'trace' => null,
        ];
    }

    protected function addLogEntry(array $entry) : void
    {
        if (! $this->logQueries) {
            return;
        }

        $entry['finish'] = microtime(true);
        $entry['duration'] = $entry['finish'] - $entry['start'];
        $entry['trace'] = (new Exception())->getTraceAsString();

        if ($this->queryLogger !== null) {
            ($this->queryLogger)($entry);
        } else {
            $this->queries[] = $entry;
        }
    }
}
