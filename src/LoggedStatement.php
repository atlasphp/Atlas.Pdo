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

use PDO;
use PDOStatement;

class LoggedStatement extends PDOStatement
{
    private $statement;

    private $logEntry;

    private $queryLogger;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    public function setLogEntry(array $logEntry) : void
    {
        $this->logEntry = $logEntry;
    }

    public function setQueryLogger(callable $queryLogger) : void
    {
        $this->queryLogger = $queryLogger;
    }

    public function execute($inputParameters = null) : bool
    {
        $result = $this->statement->execute($inputParameters);
        $this->log($inputParameters);
        return $result;
    }

    private function log($inputParameters) : void
    {
        if ($this->queryLogger === null || $this->logEntry === null) {
            return;
        }

        if ($inputParameters !== null) {
            $this->logEntry['values'] = array_replace(
                $this->logEntry['values'],
                $inputParameters
            );
        }

        ($this->queryLogger)($this->logEntry);
    }

    public function bindValue(
        $parameter,
        $value,
        $dataType = PDO::PARAM_STR
    ) : bool
    {
        $result = $this->statement->bindValue($parameter, $value, $dataType);
        if ($result && $this->logEntry !== null) {
            $this->logEntry['values'][$parameter] = $value;
        }
        return $result;
    }

    public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        return $this->statement->fetch(...func_get_args());
    }

    public function bindParam(
        $parameter,
        &$variable,
        $data_type = PDO::PARAM_STR,
        $length = null,
        $driver_options = null
    ) {
        return $this->statement->bindParam(...func_get_args());
    }

    public function bindColumn($column, &$param, $type = null, $maxlen = null, $driverdata = null)
    {
        return $this->statement->bindColumn(...func_get_args());
    }

    public function rowCount()
    {
        return $this->statement->rowCount();
    }

    public function fetchColumn($column_number = 0)
    {
        return $this->statement->fetchColumn(...func_get_args());
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = [])
    {
        return $this->statement->fetchAll(...func_get_args());
    }

    public function fetchObject($class_name = "stdClass", $ctor_args = [])
    {
        return $this->statement->fetchObject(...func_get_args());
    }

    public function errorCode()
    {
        return $this->statement->errorCode();
    }

    public function errorInfo()
    {
        return $this->statement->errorInfo();
    }

    public function setAttribute($attribute, $value)
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    public function getAttribute($attribute)
    {
        return $this->statement->getAttribute($attribute);
    }

    public function columnCount()
    {
        return $this->statement->columnCount();
    }

    public function getColumnMeta($column)
    {
        return $this->statement->getColumnMeta($column);
    }

    public function setFetchMode($mode, $classNameObject = null, array $ctorarfg = [])
    {
        return $this->statement->setFetchMode(...func_get_args());
    }

    public function nextRowset()
    {
        return $this->statement->nextRowset();
    }

    public function closeCursor()
    {
        return $this->statement->closeCursor();
    }

    public function debugDumpParams()
    {
        return $this->statement->debugDumpParams();
    }
}
