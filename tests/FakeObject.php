<?php
namespace Atlas\Pdo;

class FakeObject
{
    public $foo;

    public $id;

    public $name;

    public function __construct($foo)
    {
        $this->foo = $foo;
    }
}
