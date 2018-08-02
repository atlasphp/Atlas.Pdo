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
 * @method bool beginTransaction()
 * @method bool commit()
 * @method mixed errorCode()
 * @method array errorInfo()
 * @method int exec(string $statement)
 * @method mixed getAttribute($attribute)
 * @method bool inTransaction()
 * @method string lastInsertId(string $name = null)
 * @method PDOStatement prepare(string $statement, array $options = null)
 * @method PDOStatement query(string $statement, ...$fetch)
 * @method string quote($value, int $parameter_type = PDO::PARAM_STR)
 * @method bool rollBack()
 * @method mixed setAttribute($attribute, $value)
 */
class Connection
{
    protected $pdo;

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
            $type = $args[1] ?? PDO::PARAM_STR;
            if ($type === PDO::PARAM_BOOL && is_bool($args[0])) {
                $args[0] = $args[0] ? '1' : '0';
            }

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
    ) : Generator {
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
}
