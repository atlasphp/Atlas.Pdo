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

use BadMethodCallException;
use PDO;
use PDOStatement;

/**
 * @phpstan-import-type logEntry from Connection
 */
class PersistentLoggedStatement extends PDOStatement
{
    /**
     * @param PDOStatement $parent
     * @param callable     $queryLogger
     * @param logEntry     $logEntry
     *
     * @return static
     */
    static public function new(
        PDOStatement $parent,
        callable $queryLogger,
        array $logEntry
    ) : static
    {
        $sth = new static();
        $sth->parent = $parent;
        $sth->queryLogger = $queryLogger;
        $sth->logEntry = $logEntry;
        return $sth;
    }

    private PDOStatement $parent;

    /** @var callable  */
    private mixed /* callable */ $queryLogger;

    /**
     * @var logEntry
     */
    private array $logEntry;

    /* Attributes */

    /**
     * @param int   $attribute
     * @param mixed $value
     *
     * @return bool
     */
    public function setAttribute(int $attribute, mixed $value) : bool
    {
        return $this->parent->setAttribute($attribute, $value);
    }

    /**
     * @param int $attribute
     *
     * @return mixed
     */
    public function getAttribute(int $attribute) : mixed
    {
        return $this->parent->getAttribute($attribute);
    }

    /* Binding */

    /**
     * @param mixed      $column
     * @param mixed      $param
     * @param int        $type
     * @param int        $maxlen
     * @param mixed|null $driverdata
     *
     * @return bool
     */
    public function bindColumn(
        mixed $column,
        mixed &$param,
        int $type = 0,
        int $maxlen = 0,
        mixed $driverdata = null
    ) : bool
    {
        throw new BadMethodCallException(
            'Cannot call bindColumn() on persistent logged statements.'
        );
    }

    /**
     * @param string|int $parameter
     * @param mixed      $variable
     * @param int        $data_type
     * @param int        $length
     * @param mixed|null $driver_options
     *
     * @return bool
     */
    public function bindParam(
        string|int $parameter,
        mixed &$variable,
        int $data_type = PDO::PARAM_STR,
        int $length = 0,
        mixed $driver_options = null
    ) : bool
    {
        return $this->parent->bindParam(
            $parameter,
            $variable,
            $data_type,
            $length,
            $driver_options
        );
    }

    /**
     * @param string|int $parameter
     * @param mixed      $value
     * @param int        $dataType
     *
     * @return bool
     */
    public function bindValue(
        string|int $parameter,
        mixed $value,
        int $dataType = PDO::PARAM_STR
    ) : bool
    {
        $result = $this->parent->bindValue($parameter, $value, $dataType);

        if ($result) {
            $this->logEntry['values'][$parameter] = $value;
        }

        return $result;
    }

    /* Execution */

    /**
     * @param array|null $inputParameters
     *
     * @return bool
     */
    public function execute(?array $inputParameters = null) : bool
    {
        $result = $this->parent->execute($inputParameters);
        $this->log($inputParameters);
        return $result;
    }

    /* Fetching */

    /**
     * @param int   $mode
     * @param mixed ...$args
     *
     * @return bool
     */
    public function setFetchMode(int $mode, mixed ...$args) : bool
    {
        return $this->parent->setFetchMode($mode, ...$args);
    }

    /**
     * @param int $fetch_style
     * @param int $cursor_orientation
     * @param int $cursor_offset
     *
     * @return mixed
     */
    public function fetch(
        int $fetch_style = PDO::FETCH_DEFAULT,
        int $cursor_orientation = PDO::FETCH_ORI_NEXT,
        int $cursor_offset = 0
    ) : mixed
    {
        return $this->parent->fetch($fetch_style, $cursor_orientation, $cursor_offset);
    }

    /**
     * @param int   $fetch_style
     * @param mixed ...$args
     *
     * @return array
     */
    public function fetchAll(
        int $fetch_style = PDO::FETCH_DEFAULT,
        mixed ...$args
    ) : array
    {
        /** @var array<array-key, callable|int|string> $args */
        return $this->parent->fetchAll($fetch_style, ...$args);
    }

    /**
     * @param int $column_number
     *
     * @return mixed
     */
    public function fetchColumn(int $column_number = 0) : mixed
    {
        return $this->parent->fetchColumn($column_number);
    }

    /**
     * @template T of object
     * @param class-string<T> $class_name
     * @param array           $ctor_args
     *
     * @return T|false
     */
    public function fetchObject(
        ?string $class_name = 'stdClass',
        array $ctor_args = []
    ) : object|false
    {
        /** @var class-string<T> $class_name */
        return $this->parent->fetchObject($class_name, $ctor_args);
    }

    /* Metadata */

    /**
     * @return int
     */
    public function rowCount() : int
    {
        return $this->parent->rowCount();
    }

    /**
     * @return int
     */
    public function columnCount() : int
    {
        return $this->parent->columnCount();
    }

    /**
     * @param int $column
     *
     * @return array|false
     */
    public function getColumnMeta(int $column) : array|false
    {
        return $this->parent->getColumnMeta($column);
    }

    /* Errors */

    /**
     * @return string|null
     */
    public function errorCode() : ?string
    {
        return $this->parent->errorCode();
    }

    /**
     * @return array
     */
    public function errorInfo() : array
    {
        return $this->parent->errorInfo();
    }

    /* Other */

    /**
     * @return bool
     */
    public function closeCursor() : bool
    {
        return $this->parent->closeCursor();
    }

    /**
     * @return bool|null
     */
    public function debugDumpParams() : ?bool
    {
        return $this->parent->debugDumpParams();
    }

    /**
     * @return bool
     */
    public function nextRowset() : bool
    {
        return $this->parent->nextRowset();
    }

    /**
     * @param array|null $inputParameters
     *
     * @return void
     */
    private function log(?array $inputParameters) : void
    {
        if ($inputParameters !== null) {
            $this->logEntry['values'] = array_replace(
                $this->logEntry['values'],
                $inputParameters
            );
        }

        ($this->queryLogger)($this->logEntry);
    }
}
