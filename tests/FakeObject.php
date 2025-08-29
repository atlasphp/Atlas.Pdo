<?php
namespace Atlas\Pdo;

use stdClass;

class FakeObject extends stdClass
{
    public $foo;

    public function __construct($foo)
    {
        $this->foo = $foo;
    }
}
