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
        $result = parent::execute($inputParameters);
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
        $result = parent::bindValue($parameter, $value, $dataType);
        if ($result && $this->logEntry !== null) {
            $this->logEntry['values'][$parameter] = $value;
        }
        return $result;
    }
}
