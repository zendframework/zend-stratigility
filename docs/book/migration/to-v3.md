# Migrating from version 2 to version 3

## PHP 7.1 support

We support only PHP 7.1.

## PSR-15

Since version 3.0.0 Stratigility supports PSR-15 middlewares.
Support of `http-interop/http-middleware` has been dropped.

All middlewares and request handlers now implement PSR-15 interfaces.
