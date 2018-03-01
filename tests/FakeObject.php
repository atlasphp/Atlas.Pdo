<?php
namespace Atlas\Pdo;

class FakeObject
{
    public $foo;

    public function __construct($foo)
    {
        $this->foo = $foo;
    }
}
