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

class Connection
{
    protected $pdo;

    public static function new(...$args) : Connection
    {
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

    public function perform(
        string $statement,
        array $values = []
    ) : PDOStatement
    {
        $sth = $this->prepare($statement);
        foreach ($values as $name => $args) {
            if (is_int($name)) {
                // sequential placeholders are 1-based
                $name ++;
            }
            settype($args, 'array');
            $sth->bindValue($name, ...$args);
        }
        $sth->execute();
        return $sth;
    }

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
        array $args = []
    ) {
        $sth = $this->perform($statement, $values);

        if (! empty($args)) {
            return $sth->fetchObject($class, $args);
        }

        return $sth->fetchObject($class);
    }

    public function fetchObjects(
        string $statement,
        array $values = [],
        string $class = 'stdClass',
        array $args = []
    ) : array
    {
        $sth = $this->perform($statement, $values);

        if (! empty($args)) {
            return $sth->fetchAll(PDO::FETCH_CLASS, $class, $args);
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
        array $args = []
    ) : Generator {
        $sth = $this->perform($statement, $values);

        if (empty($args)) {
            while ($instance = $sth->fetchObject($class)) {
                yield $instance;
            }
        } else {
            while ($instance = $sth->fetchObject($class, $args)) {
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
}
