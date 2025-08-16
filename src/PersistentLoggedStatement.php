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

    public function setAttribute(int $attribute, mixed $value) : bool
    {
        return $this->parent->setAttribute($attribute, $value);
    }

    public function getAttribute(int $attribute) : mixed
    {
        return $this->parent->getAttribute($attribute);
    }

    /* Binding */

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

    public function execute(array $inputParameters = null) : bool
    {
        $result = $this->parent->execute($inputParameters);
        $this->log($inputParameters);
        return $result;
    }

    /* Fetching */

    public function setFetchMode(int $mode, mixed ...$args) : bool
    {
        return $this->parent->setFetchMode($mode, ...$args);
    }

    public function fetch(
        int $fetch_style = PDO::FETCH_DEFAULT,
        int $cursor_orientation = PDO::FETCH_ORI_NEXT,
        int $cursor_offset = 0
    ) : mixed
    {
        return $this->parent->fetch($fetch_style, $cursor_orientation, $cursor_offset);
    }

    public function fetchAll(
        int $fetch_style = PDO::FETCH_DEFAULT,
        mixed ...$args
    ) : array
    {
        /** @var array<array-key, callable|int|string> $args */
        return $this->parent->fetchAll($fetch_style, ...$args);
    }

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

    public function rowCount() : int
    {
        return $this->parent->rowCount();
    }

    public function columnCount() : int
    {
        return $this->parent->columnCount();
    }

    public function getColumnMeta(int $column) : array|false
    {
        return $this->parent->getColumnMeta($column);
    }

    /* Errors */

    public function errorCode() : ?string
    {
        return $this->parent->errorCode();
    }

    public function errorInfo() : array
    {
        return $this->parent->errorInfo();
    }

    /* Other */

    public function closeCursor() : bool
    {
        return $this->parent->closeCursor();
    }

    public function debugDumpParams() : ?bool
    {
        return $this->parent->debugDumpParams();
    }

    public function nextRowset() : bool
    {
        return $this->parent->nextRowset();
    }

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
