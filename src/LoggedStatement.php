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
    private mixed $queryLogger;

    private array $logEntry;

    protected function __construct(callable $queryLogger, array $logEntry)
    {
        $this->queryLogger = $queryLogger;
        $this->logEntry = $logEntry;
        $this->logEntry['statement'] = $this->queryString;
    }

    public function execute(array $inputParameters = null) : bool
    {
        $result = parent::execute($inputParameters);
        $this->log($inputParameters);
        return $result;
    }

    public function bindValue(
        mixed $parameter,
        mixed $value,
        int $dataType = PDO::PARAM_STR
    ) : bool
    {
        $result = parent::bindValue($parameter, $value, $dataType);

        if ($result && $this->logEntry !== null) {
            $this->logEntry['values'][$parameter] = $value;
        }

        return $result;
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
