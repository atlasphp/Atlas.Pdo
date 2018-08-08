# PDO Connection

## Installation

This package is installable and autoloadable via [Composer](https://getcomposer.org/)
as [atlas/pdo](https://packagist.org/packages/atlas/pdo). Add the following lines
to your `composer.json` file, then call `composer update`.

```json
{
    "require": {
        "atlas/pdo": "~1.0"
    }
}
```

## Instantiation

The easiest way to create a _Connection_ is to use its static  `new()` method, either with _PDO_ connection arguments, or with an actual _PDO_ instance:

```php
use Atlas\Pdo\Connection;

// pass PDO constructor arguments ...
$connection = Connection::new(
    'mysql:host=localhost;dbname=testdb',
    'username',
    'password'
);

// ... or a PDO instance.
$connection = Connection::new($pdo);
```

If you need a callable factory to create a _Connection_ and its PDO instance at a later time, such as in a service container, you can use the `Connection::factory()` method:

```php
use Atlas\Pdo\Connection;

// get a callable factory that creates a Connection
$factory = Connection::factory('sqlite::memory:');

// later, call the factory to instantiate the Connection
$connection = $factory();
```

## Calling PDO Methods

The _Connection_ acts as a proxy to the decorated PDO instance, so you can call any method on the _Connection_ that you would normally call on PDO.

## Fetching Results

The _Connection_ provides several `fetch*()` methods to help reduce boilerplate code. Instead of issuing `prepare()`, a series of `bindValue()` calls, `execute()`, and then `fetch*()` on a _PDOStatement_, you can bind values and fetch results in one call on _Connection_ directly.

### fetchAll()

The plain-old PDO way to fetch all rows looks like this:

```php
use PDO;

$pdo = new PDO('sqlite::memory:');

$stm  = 'SELECT * FROM test WHERE foo = :foo AND bar = :bar';
$bind = ['foo' => 'baz', 'bar' => 'dib'];
$sth = $pdo->prepare($stm);
$sth->execute($bind);
$result = $sth->fetchAll(PDO::FETCH_ASSOC);
```

This is how to do the same thing with an Atlas PDO _Connection_:

```php
use Atlas\Pdo\Connection;

$connection = Connection::new('sqlite::memory:');

$stm  = 'SELECT * FROM test WHERE foo = :foo AND bar = :bar';
$bind = ['foo' => 'baz', 'bar' => 'dib'];

$result = $connection->fetchAll($stm, $bind);
```

### fetchAffected()

The `fetchAffected()` method returns the number of affected rows.

```php
$stm = "UPDATE test SET incr = incr + 1 WHERE foo = :foo AND bar = :bar";
$rowCount = $connection->fetchAffected($stm, $bind);
```

### fetchColumn()

The `fetchColumn()` method returns a sequential array of the first column from all rows.

```php
$result = $connection->fetchColumn($stm, $bind);
```

You can choose another column number with an optional third argument (columns are zero-indexed):

```php
// use column 3 (i.e. the 4th column)
$result = $connection->fetchColumn($stm, $bind, 3);
```

### fetchGroup()

The `fetchGroup()` method is like fetchUnique() except that the values aren't wrapped in arrays. Instead, single column values are returned as a single dimensional array and multiple columns are returned as an array of arrays.

```php
$result = $connection->fetchGroup($stm, $bind, $style = PDO::FETCH_COLUMN)
```

Set `$style` to `PDO::FETCH_NAMED` when values are an array (i.e. there are more than two columns in the select).

### fetchKeyPair()

The `fetchKeyPair()` method returns an associative array where each key is the first column and each value is the second column

```php
$result = $connection->fetchKeyPair($stm, $bind);
```

### fetchObject()

The `fetchObject()` method returns the first row as an object of your choosing; the columns are mapped to object properties. an optional 4th parameter array provides constructor arguments when instantiating the object.

```php
$result = $connection->fetchObject($stm, $bind, 'ClassName', ['ctor_arg_1']);
```

### fetchObjects()

The `fetchObjects()` method returns an array of objects of your choosing; the columns are mapped to object properties. An optional 4th parameter array provides constructor arguments when instantiating the object.

```php
$result = $connection->fetchObjects($stm, $bind, 'ClassName', ['ctor_arg_1']);
```

### fetchOne()

The `fetchOne()` method returns the first row as an associative array where the keys are the column names.

```php
$result = $connection->fetchOne($stm, $bind);
```

### fetchUnique()

The `fetchUnique()` method returns an associative array of all rows where the key is the value of the first column, and the row arrays are keyed on the remaining column names.

```php
$result = $connection->fetchUnique($stm, $bind);
```

### fetchValue()

The `fetchValue()` method returns the value of the first row in the first column.

```php
$result = $connection->fetchValue($stm, $bind);
```

## Yielding Results

The _Connection_ provides several `yield*()` methods to help reduce memory usage. Whereas `fetch*()` methods may collect all the query result rows before returning them all at once, the equivalent `yield*()` methods generate one result row at a time. For example:

### yieldAll()

This is the yielding equivalent of `fetchAll()`.

```php
foreach ($connection->yieldAll($stm, $bind) as $row) {
    // ...
}
```

### yieldColumn()

This is the yielding equivalent of `fetchColumn()`.

```php

foreach ($connection->yieldColumn($stm, $bind) as $val) {
    // ...
}
```

### yieldKeyPair()

This is the yielding equivalent of `fetchKeyPair()`.

```php
foreach ($connection->yieldPairs($stm, $bind) as $key => $val) {
    // ...
}
```

### yieldObjects()

This is the yielding equivalent of `fetchObjects()`.

```php
$class = 'ClassName';
$args = ['arg0', 'arg1', 'arg2'];
foreach ($connection->yieldObjects($stm, $bind, $class, $args) as $object) {
    // ...
}
```

### yieldUnique()

This is the yielding equivalent of `fetchUnique()`.

```php
foreach ($connection->yieldUnique($stm, $bind) as $key => $row) {
    // ...
}
```

## Query Logging

It is sometimes useful to see a log of all queries passing through a
_Connection_. To do so, call its `logQueries()` method, issue your queries, and
then call `getQueries()`.

```php
// start logging
$connection->logQueries(true);

// at this point, all query(), exec(), perform(), fetch*(), and yield*()
// queries will be logged.

// get the query log entries
$queries = $connection->getQueries();

// stop logging
$connection->logQueries(false);
```

Each query log entry will be an array with these keys:

- `start`: when the query started
- `finish`: when the query finished
- `duration`: how long the query took
- `statement`: the query statement string
- `values`: the array of bound values
- `trace`: an exception trace showing where the query was issued

You may wish to set a custom logger on the _Connection_. To do so, call
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
$connection->setQueryLogger($customDebugger);
$connection->logQueries(true);

// now the Connection will send query log entries to the CustomDebugger
```

> **Note:**
>
> If you set a custom logger, the _Connection_ will no longer retain its own
> query log entries; they will all go to the custom logger. This means that
> `getQueries()` on the _Connection_ not show any new entries.
