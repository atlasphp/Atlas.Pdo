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
    private $logEntry;

    private $queryLogger;

    private $parent;

    public static function new(PDOStatement $parent = null)
    {
        $sth = new self();
        $sth->parent = $parent;
        return $sth;
    }

    /* Atttributes */

    public function setAttribute($attribute, $value)
    {
        return $this->parent
            ? $this->parent->setAttribute(...func_get_args())
            : parent::setAttribute(...func_get_args());
    }

    public function getAttribute($attribute)
    {
        return $this->parent
            ? $this->parent->getAttribute(...func_get_args())
            : parent::getAttribute(...func_get_args());
    }

    /* Binding */

    public function bindColumn($column, &$param, $type = 0, $maxlen = 0, $driverdata = null)
    {
        $result = $this->parent
            ? $this->parent->bindColumn($column, $param, $type, $maxlen, $driverdata)
            : parent::bindColumn($column, $param, $type, $maxlen, $driverdata);
    }

    public function bindParam(
        $parameter,
        &$variable,
        $data_type = PDO::PARAM_STR,
        $length = 0,
        $driver_options = null
    ) {
        return $this->parent
            ? $this->parent->bindParam($parameter, $variable, $data_type, $length, $driver_options)
            : parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
    }

    public function bindValue(
        $parameter,
        $value,
        $dataType = PDO::PARAM_STR
    ) : bool
    {
        $result = $this->parent
            ? $this->parent->bindValue($parameter, $value, $dataType)
            : parent::bindValue($parameter, $value, $dataType);

        if ($result && $this->logEntry !== null) {
            $this->logEntry['values'][$parameter] = $value;
        }

        return $result;
    }

    /* Execution */

    public function execute($inputParameters = null) : bool
    {
        $result = $this->parent
            ? $this->parent->execute($inputParameters)
            : parent::execute($inputParameters);

        $this->log($inputParameters);
        return $result;
    }

    /* Fetching */

    public function setFetchMode($mode, $params = null)
    {
        return $this->parent
            ? $this->parent->setFetchMode(...func_get_args())
            : parent::setFetchMode(...func_get_args());
    }

    public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        return $this->parent
            ? $this->parent->fetch(...func_get_args())
            : parent::fetch(...func_get_args());
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = [])
    {
        return $this->parent
            ? $this->parent->fetchAll(...func_get_args())
            : parent::fetchAll(...func_get_args());
    }

    public function fetchColumn($column_number = 0)
    {
        return $this->parent
            ? $this->parent->fetchColumn(...func_get_args())
            : parent::fetchColumn(...func_get_args());
    }

    public function fetchObject($class_name = 'stdClass', $ctor_args = [])
    {
        return $this->parent
            ? $this->parent->fetchObject(...func_get_args())
            : parent::fetchObject(...func_get_args());
    }

    /* Metadata */

    public function rowCount()
    {
        return $this->parent
            ? $this->parent->rowCount()
            : parent::rowCount();
    }

    public function columnCount()
    {
        return $this->parent
            ? $this->parent->columnCount()
            : parent::columnCount();
    }

    public function getColumnMeta($column)
    {
        return $this->parent
            ? $this->parent->getColumnMeta(...func_get_args())
            : parent::getColumnMeta(...func_get_args());
    }

    /* Errors */

    public function errorCode()
    {
        return $this->parent
            ? $this->parent->errorCode()
            : parent::errorCode();
    }

    public function errorInfo()
    {
        return $this->parent
            ? $this->parent->errorInfo()
            : parent::errorInfo();
    }

    /* Other */

    public function closeCursor()
    {
        return $this->parent
            ? $this->parent->closeCursor()
            : parent::closeCursor();
    }

    public function debugDumpParams()
    {
        return $this->parent
            ? $this->parent->debugDumpParams()
            : parent::debugDumpParams();
    }

    public function nextRowset()
    {
        return $this->parent
            ? $this->parent->nextRowset()
            : parent::nextRowset();
    }

    /* Logging */

    public function setLogEntry(array $logEntry) : void
    {
        $this->logEntry = $logEntry;
    }

    public function setQueryLogger(callable $queryLogger) : void
    {
        $this->queryLogger = $queryLogger;
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
}
