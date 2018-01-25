# Installation and Requirements

Install this library using composer:

```console
$ composer require zendframework/zend-diactoros zendframework/zend-stratigility
```

Stratigility has the following dependencies (which are managed by Composer):

- [psr/http-message](https://github.com/php-fig/http-message), which provides
  the interfaces specified in [PSR-7](http://www.php-fig.org/psr/psr-7),
  and type-hinted against in this package. In order to use Stratigility, you
  will need an implementation of PSR-7; one such package is
  [Diactoros](https://zendframework.github.io/zend-diactoros/).

- [http-interop/http-middleware](https://github.com/http-interop/http-middleware),
  which provides the interfaces that will become PSR-15. In Stratigility 1.3,
  this is pinned to the 0.2 series; in Stratigility 2.0, this is pinned to
  0.4.1+. Since Stratigility 2.1 you have to explicitly define an
  http-interop/http-middleware dependency in your `composer.json`, and you can
  use any version which is currently supported by the polyfill package
  [webimpress/http-middleware-compatibility](https://github.com/webimpress/http-middleware-compatibility);
  if you are creating a new project, we recommend version 0.5.0 (though current
  versions of Expressive may only support 0.4.1).

- `zendframework/zend-escaper`, used by the `ErrorHandler` middleware and the
  (legacy) `FinalHandler` implementation for escaping error messages prior to
  passing them to the response.

You can provide your own request and response implementations if desired as
long as they implement the PSR-7 HTTP message interfaces.
