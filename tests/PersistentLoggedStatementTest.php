<?php
namespace Atlas\Pdo;

use BadMethodCallException;
use PDO;
use PDOStatement;
use stdClass;

class PersistentLoggedStatementTest extends \PHPUnit\Framework\TestCase
{
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

    protected function setUp()
    {
        $this->connection = Connection::new(
            'sqlite::memory:',
            '',
            '',
            [
                PDO::ATTR_PERSISTENT => true,
            ]
        );

        $this->connection->exec("DROP TABLE IF EXISTS pdotest");

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

        $this->connection->logQueries(true);
    }

    public function testInstantiation()
    {
        $sth = $this->connection->prepare('SELECT * FROM pdotest WHERE name = :name');

        $expect = ($this->connection->getPdo()->getAttribute(PDO::ATTR_PERSISTENT))
            ? PersistentLoggedStatement::CLASS
            : LoggedStatement::CLASS;

        $this->assertInstanceOf($expect, $sth);
    }

    public function testBindColumn_badMethod()
    {
        $sth = $this->connection->prepare('SELECT * FROM pdotest WHERE name = "Anna"');
        $sth->setFetchMode(PDO::FETCH_ASSOC);
        $sth->execute();
        $this->expectException(BadMethodCallException::CLASS);
        $sth->bindColumn('name', $name);
    }

    public function testBindValue()
    {
        $sth = $this->connection->prepare('SELECT * FROM pdotest WHERE name = :name');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->bindValue('name', 'Anna');

        $sth->execute();

        $expect = ['id' => '1', 'name' => 'Anna'];
        $actual = $sth->fetch();
        $this->assertSame($expect, $actual);
    }

    public function testBindParam()
    {
        $sth = $this->connection->prepare('SELECT * FROM pdotest WHERE name = :name');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $name = 'none';
        $sth->bindParam('name', $name);
        $name = 'Anna';

        $sth->execute();

        $expect = ['id' => '1', 'name' => 'Anna'];
        $actual = $sth->fetch();
        $this->assertSame($expect, $actual);
    }

    public function testFetchAll()
    {
        $sth = $this->connection->prepare('SELECT * FROM pdotest WHERE id <= 3 ORDER BY id');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();

        $actual = $sth->fetchAll();
        $expect = [
            [
                'id' => '1',
                'name' => 'Anna',
            ],
            [
                'id' => '2',
                'name' => 'Betty',
            ],
            [
                'id' => '3',
                'name' => 'Clara',
            ]
        ];

        $this->assertSame($expect, $actual);

        $this->assertSame(2, $sth->columnCount());
    }

    public function testFetchColumn()
    {
        $sth = $this->connection->prepare('SELECT * FROM pdotest ORDER BY id');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();

        $actual = $sth->fetchColumn(1);
        $expect = 'Anna';
        $this->assertSame($expect, $actual);
    }

    public function testFetchObject()
    {
        $sth = $this->connection->prepare('SELECT * FROM pdotest ORDER BY id');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();

        $actual = $sth->fetchObject();
        $expect = (object) ['id' => '1', 'name' => 'Anna'];
        $this->assertEquals($expect, $actual);
    }

    public function testRowCount()
    {
        $sth = $this->connection->prepare('INSERT INTO pdotest (id, name) VALUES (11, "Lara")');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();
        $this->assertSame(1, $sth->rowCount());
    }

    public function testGetColumnMeta()
    {
        $sth = $this->connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();
        $actual = $sth->getColumnMeta(0);
        unset($actual['len']);
        $expect = [
            'native_type' => 'string',
            'sqlite:decl_type' => 'VARCHAR(10)',
            'table' => 'pdotest',
            'flags' => [],
            'name' => 'name',
            'precision' => 0,
            'pdo_type' => 2
        ];
        $this->assertEquals($expect, $actual);
    }

    public function testErrorCode()
    {
        $sth = $this->connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $this->assertNull($sth->errorCode());
    }

    public function testErrorInfo()
    {
        $sth = $this->connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $expect = ['', null, null];
        $actual = $sth->errorInfo();
        $this->assertSame($expect, $actual);
    }

    public function testCloseCursor()
    {
        $sth = $this->connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $sth->execute();
        $this->assertTrue($sth->closeCursor());
    }

    public function testDebugDumpParams()
    {
        $sth = $this->connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $sth->execute();

        ob_start();
        $sth->debugDumpParams();
        $actual = ob_get_clean();

        $expect = "SQL: [36] SELECT name FROM pdotest ORDER BY id" . PHP_EOL
            . "Params:  0" . PHP_EOL;
        $this->assertSame($expect, $actual);
    }
}
