# Connection Locator

Some applications may use multiple database servers; for example, one for writes, and one or more for reads. The _ConnectionLocator_ allows you to define multiple _Connection_ objects for lazy-loaded read and write connections. It will create the connections only when they are when called. The _Connection_ creation logic should be wrapped in factory callable.

## Instantiation

The easiest way to create a _ConnectionLoctor_ is to use its static  `new()` method, either with _PDO_ connection arguments, or with an actual _PDO_ instance:

```php
use Atlas\Pdo\ConnectionLocator;

// pass PDO constructor arguments ...
$connectionLocator = ConnectionLocator::new(
    'mysql:host=localhost;dbname=testdb',
    'username',
    'password'
);

// ... or a PDO instance.
$connectionLocator = ConnectionLocator::new($pdo);
```

Doing so will define the default connection factory for the _ConnectionLocator_.


## Runtime Configuration

Once you have a _ConnectionLocator_, you can add as many named read and write connection factories as you like:

```php
// the write (master) server
$connectionLocator->setWriteFactory('master', Connection::factory(
    'mysql:host=master.db.localhost;dbname=database',
    'username',
    'password'
));

// read (slave) #1
$connectionLocator->setReadFactory('slave1', Connection::factory(
    return new Connection(
        'mysql:host=slave1.db.localhost;dbname=database',
        'username',
        'password'
    );
});

// read (slave) #2
$connectionLocator->setReadFactory('slave2', Connection::factory(
    'mysql:host=slave2.db.localhost;dbname=database',
    'username',
    'password'
));

// read (slave) #3
$connectionLocator->setReadFactory('slave3', Connection::factory(
    'mysql:host=slave3.db.localhost;dbname=database',
    'username',
    'password'
));
```

## Getting Connections

Retrieve a _Connection_ from the locator when you need it. This will create the _Connection_ (if needed) and then return it.

- `getDefault()` will return the default _Connection_.

- `getRead()` will return a random read _Connection_; after the first call, `getRead()` will always return the same _Connection_. (If no read _Connections_ are defined, it will return the default connection.)

- `getWrite()` will return a random write _Connection_; after the first call, `getWrite()` will always return the same _Connection_. (If no read _Connections_ are defined, it will return the default connection.)

```php
$read = $connectionLocator->getRead();
$results = $read->fetchAll('SELECT * FROM table_name LIMIT 10');

$readAgain = $connectionLocator->getRead();
assert($read === $readAgain); // true
```

You can get any read or write connection directly by name using the `get()` method:

```php
$foo = $connectionLocator->get(ConnectionLocator::READ, 'foo');
$bar = $connectionLocator->get(ConnectionLocator::WRITE, 'bar');
```

## Locking To The Write Connection

If you call the `lockToWrite()` method, calls to `getRead()` will return the write connection instead of the read connection.

```php
$read = $connectionLocator->getRead();
$write = $connectionLocator->getWrite();

$connectionLocator->lockToWrite();
$readAgain = $connectionLocator->getRead();
assert($readAgain === $write); // true
```

You can disable the lock-to-write behavior by calling `lockToWrite(false)`.

## Construction-Time Configuration

The _ConnectionLocator_ can be configured with all its connections at construction time; this can be useful with dependency injection mechanisms. (Note that this requires using the constructor proper, not the static `new()` method.)

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
$connectionLocator = new ConnectionLocator($default, $read, $write);
```

## Query Logging

As with an individual _Connection_, it is sometimes useful to log all
queries on all connections in the _ConnectionLocator_. To do so, call its
`logQueries()` method, issue your queries, and then call `getQueries()` to
get back the log entries.

```php
// start logging
$connectionLocator->logQueries(true);

// retrieve connections and issue queries, then:
$queries = $connectionLocator->getQueries();

// stop logging
$connectionLocator->logQueries(false);
```

Each query log entry will have one added key, `connection`, indicating which
connection performed the query. The `connection` label will be `DEFAULT` for the
default connection, `READ:` and the read connection name, or `WRITE:` and the
write connection name.

> **Note:**
>
> Calling `logQueries()` will turn logging on and off for all instances in the
> locator, even if those instances are not "in hand" at the moment. That is,
> you do not have to re-get the instance; logging for each connection will be
> turned on and off "at a distance."

You may wish to set a custom logger on the _ConnectionLocator_. To do so, call
`setQueryLogger()` and pass a callable with the signature
`function (array $entry) : void`.

```php
class CustomDebugger
{
    public function __invoke(array $entry) : void
    {
        // call an injected logger to record the entry
    }
}

$customDebugger = new CustomDebugger();
$connectionLocator->setQueryLogger($customDebugger);
$connectionLocator->logQueries(true);

// now the Connection will send query log entries to the CustomDebugger
```

> **Note:**
>
> If you set a custom logger, the _Connection_ will no longer retain its own
> query log entries; they will all go to the custom logger. This means that
> `getQueries()` on the _Connection_ not show any new entries.
