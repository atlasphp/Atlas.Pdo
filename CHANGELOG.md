# CHANGELOG

## 1.0.0

First stable release.

This package is a descendant of [Aura.Sql](https://github.com/auraphp/Aura.Sql). The Atlas.Pdo _Connection_ differs from the Aura.Sql _ExtendedPdo_ object in significant ways:

- The _Connection_ does not extend PDO; it cannot fulfill a PDO typehint, though it does proxy method calls to the decorated PDO instance.

- The _Connection_ does not rebuild the query statements to allow for array binding and repeated placeholders. That kind of work is now left to other Atlas packages.

- The _Connection_ object is not lazy-loading; creating a _Connection_ actually opens a database connection. Lazy-loading is now in the province of the _ConnectionLocator_.

- The _ConnectionLocator_ `getRead()` and `getWrite()` methods no longer take a `$name` argument. They will always return the first read or write connection opened with the locator (or the default connection if there are no factories for read or write connections). To get a named connection for a connection type, use the `get()` method.

## 1.0.0-beta1

This is a hygiene release to update the documentation.

## 1.0.0-alpha1

First release.
