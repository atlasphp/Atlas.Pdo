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

class PersistentLoggedStatement extends PDOStatement
{
    private $parent;

    private $queryLogger;

    private $logEntry;

    public static function new(
        PDOStatement $parent,
        callable $queryLogger,
        array $logEntry
    ) {
        $sth = new self();
        $sth->parent = $parent;
        $sth->queryLogger = $queryLogger;
        $sth->logEntry = $logEntry;
        return $sth;
    }

    /* Atttributes */

    public function setAttribute($attribute, $value)
    {
        return $this->parent->setAttribute(...func_get_args());
    }

    public function getAttribute($attribute)
    {
        return $this->parent->getAttribute(...func_get_args());
    }

    /* Binding */

    public function bindColumn($column, &$param, $type = 0, $maxlen = 0, $driverdata = null)
    {
        throw new BadMethodCallException('Cannot call bindColumn() on persistent logged statements.');
    }

    public function bindParam(
        $parameter,
        &$variable,
        $data_type = PDO::PARAM_STR,
        $length = 0,
        $driver_options = null
    ) {
        return $this->parent->bindParam($parameter, $variable, $data_type, $length, $driver_options);
    }

    public function bindValue(
        $parameter,
        $value,
        $dataType = PDO::PARAM_STR
    ) : bool
    {
        $result = $this->parent->bindValue($parameter, $value, $dataType);

        if ($result) {
            $this->logEntry['values'][$parameter] = $value;
        }

        return $result;
    }

    /* Execution */

    public function execute($inputParameters = null) : bool
    {
        $result = $this->parent->execute($inputParameters);
        $this->log($inputParameters);
        return $result;
    }

    /* Fetching */

    public function setFetchMode($mode, $params = null)
    {
        return $this->parent->setFetchMode(...func_get_args());
    }

    public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        return $this->parent->fetch(...func_get_args());
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = [])
    {
        return $this->parent->fetchAll(...func_get_args());
    }

    public function fetchColumn($column_number = 0)
    {
        return $this->parent->fetchColumn(...func_get_args());
    }

    public function fetchObject($class_name = 'stdClass', $ctor_args = [])
    {
        return $this->parent->fetchObject(...func_get_args());
    }

    /* Metadata */

    public function rowCount()
    {
        return $this->parent->rowCount();
    }

    public function columnCount()
    {
        return $this->parent->columnCount();
    }

    public function getColumnMeta($column)
    {
        return $this->parent->getColumnMeta(...func_get_args());
    }

    /* Errors */

    public function errorCode()
    {
        return $this->parent->errorCode();
    }

    public function errorInfo()
    {
        return $this->parent->errorInfo();
    }

    /* Other */

    public function closeCursor()
    {
        return $this->parent->closeCursor();
    }

    public function debugDumpParams()
    {
        return $this->parent->debugDumpParams();
    }

    public function nextRowset()
    {
        return $this->parent->nextRowset();
    }

    private function log($inputParameters) : void
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
