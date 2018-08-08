<?php
namespace Atlas\Pdo;

use PDO;
use PDOStatement;
use stdClass;

class ConnectionTest extends \PHPUnit\Framework\TestCase
{
    protected $pdo;

    protected $connection;

    protected $data = [
        1 => 'Anna',
        2 => 'Betty',
        3 => 'Clara',
        4 => 'Donna',
        5 => 'Fiona',
        6 => 'Gertrude',
        7 => 'Hanna',
        8 => 'Ione',
        9 => 'Julia',
        10 => 'Kara',
    ];

    public function setUp()
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped("Need 'pdo_sqlite' to test in memory.");
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->connection = new Connection($this->pdo);

        $this->connection->exec("CREATE TABLE pdotest (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(10) NOT NULL
        )");

        foreach ($this->data as $id => $name) {
            $this->connection->perform(
                "INSERT INTO pdotest (name) VALUES (:name)",
                [
                    'name' => $name
                ]
            );
        }
    }

    public function testFactory()
    {
        $factory = Connection::factory('sqlite::memory:');
        $connection = $factory();
        $this->assertInstanceOf(Connection::CLASS, $connection);
    }

    public function testGetDriverName()
    {
        $this->assertSame('sqlite', $this->connection->getDriverName());
    }

    public function testGetPdo()
    {
        $this->assertSame($this->pdo, $this->connection->getPdo());
    }

    public function testPerform()
    {
        $stm = $this->connection->perform("SELECT * FROM pdotest WHERE id = ?", [1]);
        $this->assertInstanceOf(PDOStatement::CLASS, $stm);
    }

    public function testFetchAffected()
    {
        $stm = "DELETE FROM pdotest";
        $actual = $this->connection->fetchAffected($stm);
        $expect = 10;
        $this->assertSame($expect, $actual);
    }

    public function testFetchAll()
    {
        $stm = "SELECT * FROM pdotest";
        $actual = $this->connection->fetchAll($stm);
        $expect = [];
        foreach ($this->data as $id => $name) {
            $expect[] = [
                'id' => $id,
                'name' => $name
            ];
        }
        $this->assertEquals($expect, $actual);
    }

    public function testFetchUnique()
    {
        $stm = "SELECT * FROM pdotest ORDER BY id";
        $result = $this->connection->fetchUnique($stm);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);

        // 1-based IDs, not 0-based sequential values
        $expect = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $actual = array_keys($result);
        $this->assertEquals($expect, $actual);
    }

    public function testFetchColumn()
    {
        $stm = "SELECT id FROM pdotest ORDER BY id";
        $result = $this->connection->fetchColumn($stm);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);

        // // 1-based IDs, not 0-based sequential values
        $expect = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
        $this->assertEquals($expect, $result);
    }

    public function testFetchObject()
    {
        $stm = "SELECT id, name FROM pdotest WHERE id = :id";
        $actual = $this->connection->fetchObject($stm, ['id' => 1]);
        $this->assertSame('1', $actual->id);
        $this->assertSame('Anna', $actual->name);
    }

    public function testFetchObject_withCtorArgs()
    {
        $stm = "SELECT id, name FROM pdotest WHERE id = :id";
        $actual = $this->connection->fetchObject(
            $stm,
            ['id' => 1],
            'Atlas\Pdo\FakeObject',
            ['bar']
        );
        $this->assertSame('1', $actual->id);
        $this->assertSame('Anna', $actual->name);
        $this->assertSame('bar', $actual->foo);
    }

    public function testFetchObjects()
    {
        $stm = "SELECT * FROM pdotest";
        $actual = $this->connection->fetchObjects($stm);
        $expect = [];
        foreach ($this->data as $id => $name) {
            $expect[] = (object) [
                'id' => $id,
                'name' => $name
            ];
        }
        $this->assertEquals($expect, $actual);
    }

    public function testFetchObjects_withCtorArgs()
    {
        $stm = "SELECT * FROM pdotest";
        $actual = $this->connection->fetchObjects(
            $stm,
            [],
            'Atlas\Pdo\FakeObject',
            ['bar']
        );
        $expect = [];
        foreach ($this->data as $id => $name) {
            $object = new FakeObject('bar');
            $object->id = $id;
            $object->name = $name;
            $expect[] = $object;
        }
        $this->assertEquals($expect, $actual);
    }

    public function testFetchOne()
    {
        $stm = "SELECT id, name FROM pdotest WHERE id = 1";
        $actual = $this->connection->fetchOne($stm);
        $expect = [
            'id'   => '1',
            'name' => 'Anna',
        ];
        $this->assertEquals($expect, $actual);

        $stm = "SELECT id, name FROM pdotest WHERE id = -99";
        $actual = $this->connection->fetchOne($stm);
        $this->assertNull($actual);
    }

    public function testFetchGroup()
    {
        $stm = "SELECT id, name FROM pdotest WHERE id = 1";
        $actual = $this->connection->fetchGroup($stm, [], PDO::FETCH_NAMED);
        $expect = [
            '1' => [
                [
                    'name' => 'Anna'
                ]
            ]
        ];
        $this->assertEquals($expect, $actual);
    }

    public function testFetchGroup_singleColumn()
    {
        $stm = "SELECT id, name FROM pdotest WHERE id = 1";
        $actual = $this->connection->fetchGroup($stm);
        $expect = [
            '1' => [
                'Anna'
            ]
        ];
        $this->assertEquals($expect, $actual);
    }

    public function testFetchKeyPair()
    {
        $stm = "SELECT id, name FROM pdotest ORDER BY id";
        $actual = $this->connection->fetchKeyPair($stm);
        $this->assertEquals($this->data, $actual);
    }

    public function testYieldKeyPair()
    {
        $stm = "SELECT id, name FROM pdotest ORDER BY id";
        $actual = [];
        foreach ($this->connection->yieldKeyPair($stm) as $key => $value) {
            $actual[$key] = $value;
        }
        $this->assertEquals($this->data, $actual);
    }

    public function testFetchValue()
    {
        $stm = "SELECT id FROM pdotest WHERE id = 1";
        $actual = $this->connection->fetchValue($stm);
        $expect = '1';
        $this->assertEquals($expect, $actual);
    }

    public function testYieldAll()
    {
        $stm = "SELECT * FROM pdotest";
        $actual = [];
        foreach ($this->connection->yieldAll($stm) as $row) {
            $actual[$row['id']] = $row['name'];
        }
        $this->assertEquals($this->data, $actual);
    }

    public function testYieldUnique()
    {
        $stm = "SELECT * FROM pdotest ORDER BY id";
        $actual = [];
        foreach ($this->connection->yieldUnique($stm) as $key => $row) {
            $actual[$key] = $row['name'];
        }
        $this->assertEquals($this->data, $actual);
    }

    public function testYieldColumn()
    {
        $stm = "SELECT id FROM pdotest ORDER BY id";
        $actual = [];
        foreach ($this->connection->yieldColumn($stm) as $value) {
            $actual[]= $value;
        };
        $this->assertEquals(array_keys($this->data), $actual);
    }

    public function testYieldObjects()
    {
        $stm = "SELECT * FROM pdotest";
        $actual = [];
        foreach ($this->connection->yieldObjects($stm) as $object) {
            $actual[]= $object;
        }
        $expect = [];
        foreach ($this->data as $id => $name) {
            $expect[] = (object) [
                'id' => $id,
                'name' => $name
            ];
        }
        $this->assertEquals($expect, $actual);
    }

    public function testYieldObjects_withCtorArgs()
    {
        $stm = "SELECT * FROM pdotest";
        $actual = [];
        foreach ($this->connection->yieldObjects(
            $stm,
            [],
            'Atlas\Pdo\FakeObject',
            ['bar']
        ) as $object)
        {
            $actual[]= $object;
        }
        $expect = [];
        foreach ($this->data as $id => $name) {
            $object = new FakeObject('bar');
            $object->id = $id;
            $object->name = $name;
            $expect[] = $object;
        }
        $this->assertEquals($expect, $actual);
    }

    public function testQuery()
    {
        $stm = "SELECT id, name FROM pdotest WHERE id = 1";
        $sth = $this->connection->query($stm);
        $this->assertInstanceOf(PDOStatement::class, $sth);
    }

    public function testQueryLogging()
    {
        // query logging turned off
        $stm = "SELECT id, name FROM pdotest WHERE id = :id";
        $sth = $this->connection->perform($stm, ['id' => [false, PDO::PARAM_BOOL]]);
        $this->assertInstanceOf(PDOStatement::class, $sth);
        $this->assertSame([], $this->connection->getQueries());

        // query logging turned on
        $this->connection->logQueries(true);
        $sth = $this->connection->perform($stm, ['id' => [false, PDO::PARAM_BOOL]]);
        $this->assertInstanceOf(LoggedStatement::CLASS, $sth);

        $queries = $this->connection->getQueries();
        $this->assertCount(1, $queries);

        $query = $queries[0];
        $this->assertTrue($query['start'] > 0);
        $this->assertTrue($query['finish'] > $query['start']);
        $this->assertTrue($query['duration'] > 0);
        $this->assertTrue($query['statement'] == 'SELECT id, name FROM pdotest WHERE id = :id');
        $this->assertTrue($query['values']['id'] === '0');
        $this->assertTrue($query['trace'] != '');

        // transaction entries
        $this->connection->beginTransaction();
        $this->connection->commit();
        $this->connection->beginTransaction();
        $this->connection->rollBack();
        $queries = $this->connection->getQueries();
        $this->assertCount(5, $queries); // including the earlier one
        $this->assertSame('Atlas\\Pdo\\Connection::beginTransaction', $queries[1]['statement']);
        $this->assertSame('Atlas\\Pdo\\Connection::commit', $queries[2]['statement']);
        $this->assertSame('Atlas\\Pdo\\Connection::beginTransaction', $queries[3]['statement']);
        $this->assertSame('Atlas\\Pdo\\Connection::rollBack', $queries[4]['statement']);
    }

    public function testStatementLogging()
    {
        // query logging turned off
        $stm = "SELECT id, name FROM pdotest WHERE id = :id";
        $sth = $this->connection->prepare($stm);
        $this->assertInstanceOf(PDOStatement::class, $sth);
        $this->assertTrue($sth->execute(['id' => '0']));
        $this->assertSame([], $this->connection->getQueries());

        // query logging turned on
        $this->connection->logQueries(true);
        $stm = "SELECT id, name FROM pdotest WHERE id = :id";
        $sth = $this->connection->prepare($stm);
        $this->assertInstanceOf(LoggedStatement::CLASS, $sth);

        $this->assertTrue($sth->execute(['id' => '0']));

        $queries = $this->connection->getQueries();
        $this->assertCount(1, $queries);

        $query = $queries[0];
        $this->assertTrue($query['start'] > 0);
        $this->assertTrue($query['finish'] > $query['start']);
        $this->assertTrue($query['duration'] > 0);
        $this->assertTrue($query['statement'] == 'SELECT id, name FROM pdotest WHERE id = :id');
        $this->assertTrue($query['values']['id'] === '0');
        $this->assertTrue($query['trace'] != '');
    }
}
