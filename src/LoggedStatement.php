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
        if ($inputParameters !== null) {
            $this->logEntry['values'] = array_replace(
                $this->logEntry['values'],
                $inputParameters
            );
        }

        $result = parent::execute($inputParameters);
        ($this->queryLogger)($this->logEntry);
        return $result;
    }

    public function bindValue(
        $parameter,
        $value,
        $dataType = null
    ) : bool
    {
        $result = parent::bindValue($parameter, $value, $dataType);
        if ($result) {
            $this->logEntry['values'][$parameter] = $value;
        }
        return $result;
    }
}
