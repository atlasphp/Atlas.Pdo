# Connection Locator

Some applications may multiple database servers; for example, one for writes, and one or more for reads. The _ConnectionLocator_ allows you to define multiple _Connection_ objects for lazy-loaded read and write connections. It will create the connections only when they are when called. The _Connection_ creation logic should be wrapped in factory callable.

## Runtime Configuration

First, create the _ConnectionLocator_:

```php
use Atlas\Pdo\Connection;
use Atlas\Pdo\ConnectionLocator;

$connections = new ConnectionLocator();
```

Now add a default connection factory; this will be used when a read or write connection is not defined. (This is also useful for setting up connection location in advance of actually having multiple database servers.)

```php
$connections->setDefaultFactory(Connection::factory(
    'mysql:host=default.db.localhost;dbname=database',
    'username',
    'password'
));
```

Next, add as many named read and write connection factories as you like:

```php
// the write (master) server
$connections->setWriteFactory('master', Connection::factory(
    'mysql:host=master.db.localhost;dbname=database',
    'username',
    'password'
));

// read (slave) #1
$connections->setReadFactory('slave1', Connection::factory(
    return new Connection(
        'mysql:host=slave1.db.localhost;dbname=database',
        'username',
        'password'
    );
});

// read (slave) #2
$connections->setReadFactory('slave2', Connection::factory(
    'mysql:host=slave2.db.localhost;dbname=database',
    'username',
    'password'
));

// read (slave) #3
$connections->setReadFactory('slave3', Connection::factory(
    'mysql:host=slave3.db.localhost;dbname=database',
    'username',
    'password'
));
```

Finally, retrieve a connection from the locator when you need it. This will create the connection (if needed) and then return it.

- `getDefault()` will return the default _Connection_ instance.

- `getRead()` will return a named read _Connection_ instance. If no name is specified, it will return a random read _Connection_. If no read connections are defined, it will return the default _Connection_.

- `getWrite()` will return a named write _Connection_. If no name is specified, it will return a random write _Connection_. If no write connections are defined, it will return the default _Connection_.

```php
$read = $connections->getRead();
$results = $read->fetchAll('SELECT * FROM table_name LIMIT 10');
```

## Construction-Time Configuration

The _ConnectionLocator_ can be configured with all its connections at construction time; this is useful with dependency injection mechanisms.

```php
use Atlas\Pdo\Connection;
use Atlas\Pdo\ConnectionLocator;

// default connection
$default = Connection::factory(
    'mysql:host=default.db.localhost;dbname=database',
    'username',
    'password'
);

// read connections
$read = [
    'slave1' => Connection::factory(
        'mysql:host=slave1.db.localhost;dbname=database',
        'username',
        'password'
    ),
    'slave2' => Connection::factory(
        'mysql:host=slave2.db.localhost;dbname=database',
        'username',
        'password'
    ),
    'slave3' => Connection::factory(
        'mysql:host=slave3.db.localhost;dbname=database',
        'username',
        'password'
    ),
];

// write connection
$write = [
    'master' => Connection::factory(
        'mysql:host=master.db.localhost;dbname=database',
        'username',
        'password'
    ),
];

// configure locator at construction time
$connections = new ConnectionLocator($default, $read, $write);
```
