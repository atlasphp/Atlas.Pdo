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

class Exception extends \Exception
{
    public static function connectionNotFound($type, $name)
    {
        return new Exception("{$type}:{$name}");
    }
}
