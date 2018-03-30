# Atlas.Pdo

This package provides a _Connection_ that decorates any [PDO](http://php.net/PDO)
instance to provide the following:

- A `perform()` method acts just like `query()`, but binds values to a prepared statement as part of the call.

- Several `fetch*()` methods to return results in commonly-occurring situations.

- Several `yield*()` methods as `fetch*()` equivalents to yield results instead of returning them.

- The _Connection_ always sets the PDO connection to `ERRMODE_EXCEPTION` mode for error reporting.

This package also provides a _ConnectionLocator_ to register, instantiate, and
retain named _Connection_ objects for default, read, and write databases.

Read the documentation [here](./docs/index.md).

## Lineage

This package is a descendant of [Aura.Sql](https://github.com/auraphp/Aura.Sql). The Atlas.Pdo _Connection_ differs from the Aura.Sql _ExtendedPdo_ object in significant ways:

- The _Connection_ does not extend PDO; it cannot fulfill a PDO typehint, though it does proxy method calls to the decorated PDO instance.

- The _Connection_ does not rebuild the query statements to allow for array binding and repeated placeholders. That kind of work is now left to other Atlas packages.

- The _Connection_ object is not lazy-loading; creating a _Connection_ actually opens a database connection. Lazy-loading is now in the province of the _ConnectionLocator_.

- The _ConnectionLocator_ `getRead()` and `getWrite()` methods no longer take a `$name` argument. They will always return the first read or write connection opened with the locator (or the default connection if there are no factories for read or write connections). To get a named connection for a connection type, use the `get()` method.
