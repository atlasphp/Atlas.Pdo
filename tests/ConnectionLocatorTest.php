<?php
namespace Atlas\Pdo;

class ConnectionLocatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ConnectionLocator
     */
    protected $locator;

    protected $conns;

    protected $default;

    protected $read = [];

    protected $write = [];

    protected function setUp()
    {
        $this->conns = [
            'default' => Connection::new('sqlite::memory:'),
            'read1' => Connection::new('sqlite::memory:'),
            'read2' => Connection::new('sqlite::memory:'),
            'read3' => Connection::new('sqlite::memory:'),
            'write1' => Connection::new('sqlite::memory:'),
            'write2' => Connection::new('sqlite::memory:'),
            'write3' => Connection::new('sqlite::memory:'),
        ];

        $conns = $this->conns;
        $this->default = function () use ($conns) { return $conns['default']; };
        $this->read = [
            'read1' => function () use ($conns) { return $conns['read1']; },
            'read2' => function () use ($conns) { return $conns['read2']; },
            'read3' => function () use ($conns) { return $conns['read3']; },
        ];
        $this->write = [
            'write1' => function () use ($conns) { return $conns['write1']; },
            'write2' => function () use ($conns) { return $conns['write2']; },
            'write3' => function () use ($conns) { return $conns['write3']; },
        ];
    }

    protected function newLocator($read = [], $write = [])
    {
        return new ConnectionLocator($this->default, $read, $write);
    }

    public function testGetDefault()
    {
        $locator = $this->newLocator();
        $actual = $locator->getDefault();
        $expect = $this->conns['default'];
        $this->assertSame($expect, $actual);
    }

    public function testGetReadDefault()
    {
        $locator = $this->newLocator();

        $this->assertFalse($locator->hasRead());
        $actual = $locator->getRead();
        $expect = $this->conns['default'];
        $this->assertSame($expect, $actual);
        $this->assertTrue($locator->hasRead());
    }

    public function testGetReadRandom()
    {
        $locator = $this->newLocator($this->read, $this->write);

        $expect = [
            $this->conns['read1'],
            $this->conns['read2'],
            $this->conns['read3'],
        ];

        $actual = $locator->getRead();
        $this->assertTrue(in_array($actual, $expect, true));

        $again = $locator->getRead();
        $this->assertSame($actual, $again);
    }

    public function testGetReadName()
    {
        $locator = $this->newLocator($this->read, $this->write);
        $actual = $locator->get($locator::READ, 'read2');
        $expect = $this->conns['read2'];
        $this->assertSame($expect, $actual);

        $again = $locator->getRead();
        $this->assertSame($actual, $again);
    }

    public function testGetReadMissing()
    {
        $locator = $this->newLocator($this->read, $this->write);
        $this->expectException(Exception::CLASS);
        $locator->get($locator::READ, 'no-such-connection');
    }

    public function testGetWriteDefault()
    {
        $locator = $this->newLocator();
        $this->assertFalse($locator->hasWrite());
        $actual = $locator->getWrite();
        $expect = $this->conns['default'];
        $this->assertSame($expect, $actual);
        $this->assertTrue($locator->hasWrite());
    }

    public function testGetWriteRandom()
    {
        $locator = $this->newLocator($this->read, $this->write);

        $expect = [
            $this->conns['write1'],
            $this->conns['write2'],
            $this->conns['write3'],
        ];

        $actual = $locator->getWrite();
        $this->assertTrue(in_array($actual, $expect, true));

        $again = $locator->getWrite();
        $this->assertSame($actual, $again);
    }

    public function testGetWriteName()
    {
        $locator = $this->newLocator($this->read, $this->write);
        $actual = $locator->get($locator::WRITE, 'write2');
        $expect = $this->conns['write2'];
        $this->assertSame($expect, $actual);

        $again = $locator->getWrite();
        $this->assertSame($actual, $again);
    }

    public function testGetWriteMissing()
    {
        $locator = $this->newLocator($this->read, $this->write);
        $this->expectException(Exception::CLASS);
        $locator->get($locator::WRITE, 'no-such-connection');
    }

    public function testLockToWrite()
    {
        $locator = $this->newLocator($this->read, $this->write);

        $this->assertFalse($locator->isLockedToWrite());

        $read = $locator->getRead();
        $write = $locator->getWrite();
        $this->assertNotSame($read, $write);

        $locator->lockToWrite();
        $this->assertTrue($locator->isLockedToWrite());
        $read = $locator->getRead();
        $this->assertSame($read, $write);
    }
}
