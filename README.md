# Atlas.Pdo

This package provides a _Connection_ that decorates any [PDO](http://php.net/PDO) instance to provide the following:

- A `perform()` method acts just like `query()`, but binds values to a prepared statement as part of the call.

- Several `fetch*()` methods to return results in commonly-occurring situations.

- Several `yield*()` methods as `fetch*()` equivalents to yield results instead of returning them.

- Query logging, including backtraces to find where queries were issued.

- The _Connection_ always sets the PDO connection to `ERRMODE_EXCEPTION` mode for error reporting.

This package also provides a _ConnectionLocator_ to register, instantiate, and retain named _Connection_ objects for default, read (slave), and write (master) databases.

Read the documentation [here](http://atlasphp.io/cassini/pdo/).
