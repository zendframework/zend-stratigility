# Installation and Requirements

Install this library using composer:

```console
$ composer require zendframework/zend-diactoros zendframework/zend-stratigility:^2.0
```

Stratigility has the following dependencies (which are managed by Composer):

- `psr/http-message`, which provides the interfaces specified in [PSR-7](http://www.php-fig.org/psr/psr-7),
  and type-hinted against in this package. In order to use Stratigility, you
  will need an implementation of PSR-7; one such package is
  [Diactoros](https://docs.zendframework.com/zend-diactoros/).

- [`http-interop/http-middleware`](https://github.com/http-interop/http-middleware),
  which provides the interfaces that will become PSR-15. This is pinned to 0.4.1+.

- `zendframework/zend-escaper`, used by the `ErrorHandler` middleware and the
  (legacy) `FinalHandler` implementation for escaping error messages prior to
  passing them to the response.

You can provide your own request and response implementations if desired as
long as they implement the PSR-7 HTTP message interfaces.

## Previous versions

- [Version 1 documentation](../v1/install.md)

## Later versions

- [Version 3 (current) documentation](../v3/install.md)
