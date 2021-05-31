# Upgrade Notes

Upgrading from Atlas.Pdo 1.x to 2.x should be painless.

The primary difference is that it requires PHP 8.0, given the addition of
expanded and stricter typehinting in 2.x.

Connection::fetchOne() now returns `array|false` instead of `?array`; if you
previously checked for `null`, check for `false` instead.

Connection::fetchObject() now returns `object|false` instead of `mixed`.

Connection::fetchValue() now explicitly returns `mixed`.

Connection::fetchAll(), fetchColumn(), fetchGroup(), fetchKeyPair(),
fetchObjects(), and fetchUnique() now return `array|false` instead of
`array`.
