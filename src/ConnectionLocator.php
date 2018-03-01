<?php
declare(strict_types=1);

/**
 *
 * This file is part of Atlas for PHP.
 *
 * @license https://opensource.org/licenses/MIT MIT
 *
 */
namespace Atlas\Pdo;

class ConnectionLocator
{
    protected $default;

    protected $read = [];

    protected $write = [];

    public function __construct(
        callable $default = null,
        array $read = [],
        array $write = []
    ) {
        if ($default) {
            $this->setDefaultFactory($default);
        }
        foreach ($read as $name => $factory) {
            $this->setReadFactory($name, $factory);
        }
        foreach ($write as $name => $factory) {
            $this->setWriteFactory($name, $factory);
        }
    }

    public function setDefaultFactory(callable $factory) : void
    {
        $this->default = $factory;
    }

    public function getDefault() : Connection
    {
        if (! $this->default instanceof Connection) {
            $this->default = call_user_func($this->default);
        }

        return $this->default;
    }

    public function setReadFactory($name, callable $factory) : void
    {
        $this->read[$name] = $factory;
    }

    public function getRead($name = '') : Connection
    {
        return $this->getConnection('read', $name);
    }

    public function setWriteFactory($name, callable $factory) : void
    {
        $this->write[$name] = $factory;
    }

    public function getWrite($name = '') : Connection
    {
        return $this->getConnection('write', $name);
    }

    protected function getConnection($type, $name) : Connection
    {
        $connections = &$this->{$type};

        if (empty($connections)) {
            return $this->getDefault();
        }

        if ($name === '') {
            $name = array_rand($connections);
        }

        if (! isset($connections[$name])) {
            throw Exception::connectionNotFound($type, $name);
        }

        if (! $connections[$name] instanceof Connection) {
            $factory = $connections[$name];
            $connections[$name] = $factory();
        }

        return $connections[$name];
    }
}
