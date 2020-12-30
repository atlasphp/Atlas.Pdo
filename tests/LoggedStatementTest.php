<?php
namespace Atlas\Pdo;

use BadMethodCallException;
use PDO;
use PDOStatement;
use stdClass;

class LoggedStatementTest extends \PHPUnit\Framework\TestCase
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

    public function provideConnectionFactory()
    {
        return [
            // transient
            [Connection::factory(
                'sqlite::memory:'
            )],
            // persistent
            [Connection::factory(
                'sqlite::memory:',
                '',
                '',
                [
                    PDO::ATTR_PERSISTENT => true,
                ]
            )],
        ];
    }

    protected function init($connectionFactory) : Connection
    {
        $connection = $connectionFactory();

        $connection->exec("DROP TABLE IF EXISTS pdotest");

        $connection->exec("CREATE TABLE pdotest (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(10) NOT NULL
        )");

        foreach ($this->data as $id => $name) {
            $connection->perform(
                "INSERT INTO pdotest (name) VALUES (:name)",
                [
                    'name' => $name
                ]
            );
        }

        $connection->logQueries();

        return $connection;
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testInstantiation($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT * FROM pdotest WHERE name = :name');

        $expect = ($connection->getPdo()->getAttribute(PDO::ATTR_PERSISTENT))
            ? PersistentLoggedStatement::CLASS
            : LoggedStatement::CLASS;

        $this->assertInstanceOf($expect, $sth);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testBindColumn($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT * FROM pdotest WHERE name = "Anna"');
        $sth->setFetchMode(PDO::FETCH_ASSOC);
        $sth->execute();

        if ($connection->getPdo()->getAttribute(PDO::ATTR_PERSISTENT)) {
            $this->expectException(BadMethodCallException::CLASS);
        }

        $sth->bindColumn('name', $name);
        $sth->fetch();
        $this->assertSame('Anna', $name);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testBindValue($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT * FROM pdotest WHERE name = :name');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->bindValue('name', 'Anna');

        $sth->execute();

        $expect = ['id' => '1', 'name' => 'Anna'];
        $actual = $sth->fetch();
        $this->assertSame($expect, $actual);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testBindParam($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT * FROM pdotest WHERE name = :name');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $name = 'none';
        $sth->bindParam('name', $name);
        $name = 'Anna';

        $sth->execute();

        $expect = ['id' => '1', 'name' => 'Anna'];
        $actual = $sth->fetch();
        $this->assertSame($expect, $actual);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testFetchAll($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT * FROM pdotest WHERE id <= 3 ORDER BY id');
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

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testFetchColumn($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT * FROM pdotest ORDER BY id');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();

        $actual = $sth->fetchColumn(1);
        $expect = 'Anna';
        $this->assertSame($expect, $actual);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testFetchObject($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT * FROM pdotest ORDER BY id');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();

        $actual = $sth->fetchObject();
        $expect = (object) ['id' => '1', 'name' => 'Anna'];
        $this->assertEquals($expect, $actual);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testRowCount($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('INSERT INTO pdotest (id, name) VALUES (11, "Lara")');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();
        $this->assertSame(1, $sth->rowCount());
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testGetColumnMeta($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $sth->execute();
        $actual = $sth->getColumnMeta(0);
        $expect = [
            'native_type' => 'string',
            'sqlite:decl_type' => 'VARCHAR(10)',
            'table' => 'pdotest',
            'flags' => [],
            'name' => 'name',
            'len' => -1,
            'precision' => 0,
            'pdo_type' => 2
        ];
        $this->assertEquals($expect, $actual);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testErrorCode($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $this->assertNull($sth->errorCode());
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testErrorInfo($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $expect = ['', null, null];
        $actual = $sth->errorInfo();
        $this->assertSame($expect, $actual);
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testCloseCursor($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $sth->execute();
        $this->assertTrue($sth->closeCursor());
    }

    /**
     * @dataProvider provideConnectionFactory
     */
    public function testDebugDumpParams($connectionFactory)
    {
        $connection = $this->init($connectionFactory);
        $sth = $connection->prepare('SELECT name FROM pdotest ORDER BY id');
        $sth->execute();

        ob_start();
        $sth->debugDumpParams();
        $actual = ob_get_clean();

        $expect = "SQL: [36] SELECT name FROM pdotest ORDER BY id" . PHP_EOL
            . "Params:  0" . PHP_EOL;
        $this->assertSame($expect, $actual);
    }
}
