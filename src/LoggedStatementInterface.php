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

interface LoggedStatementInterface
{
    public function setLogEntry(array $logEntry) : void;
    public function setQueryLogger(callable $queryLogger) : void;
}
