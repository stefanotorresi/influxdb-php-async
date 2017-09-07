# InfluxDB PHP Async
 
 An asyncronous client for [InfluxDB][InfluxDB], implemented via [ReactPHP][ReactPHP].
 
 [![Build Status](https://travis-ci.org/stefanotorresi/influxdb-php-async.svg?branch=master)](https://travis-ci.org/stefanotorresi/influxdb-php-async)
 [![Latest Stable Version](https://poser.pugx.org/stefanotorresi/influxdb-php-async/version)](https://packagist.org/packages/stefanotorresi/influxdb-php-async)
 [![License](https://poser.pugx.org/stefanotorresi/influxdb-php-async/license)](https://packagist.org/packages/stefanotorresi/influxdb-php-async)

### Installation

Use [Composer][Composer]

`composer require stefanotorresi/influxdb-php-async`

### Usage

Each client implementation exposes three main methods:
 
```php
interface AsyncClient
{
    public function query(string $query, array $params = []): Promise;
    public function write(string $payload, array $params = []): Promise;
    public function ping(): Promise;
    /* etc. */
}
```

The default implementation uses [Buzz React][Buzz React] and we'll use it throughout the rest of this document. 

Here is a basic usage example where we first create a database, then write a line to it:

```php
$client = new ReactHttpClient();

$client
    ->query('CREATE DATABASE test')
    ->then(function($response) use ($client) {
        return $client->write('measure,tag="foo" value="bar"', ['db' => 'test']);
    })
    ->done()
;

$client->run();
```

Note that you need to run the ReactPHP event loop. If you don't inject your own, a default loop is composed by the client, and can be started via the `run` method.

This API assumes that you're familiar with [ReactPHP promises][ReactPHP promises].

#### Configuration

These are the default options:

```php
[
    'host'           => 'localhost',
    'port'           => 8086,
    'database'       => '',
    'username'       => '',
    'password'       => '',
    'socket_options' => [],
];
```

You can change them at instantion time, defaults will be merged with the one passed:

```php
$options = [ 
    'host' => 'influx-db.domain.tld', 
    'socket_options' => [
        'tls' => true,
    ],   
];

$client = new ReactHttpClient($options);
```

For details about the `socket_options` key, please refer to [react/socket documentation]. 

### Future developments / TO-DO list

- An UDP client implemented with [react/datagram](https://github.com/reactphp/datagram).
- A QueryBuilder, possibly identical to the one in the [official influxdb-php client].
- A set of response decoders that convert the JSON body from PSR-7 Responses to something more readily consumable.
- Explore the possibility of merging this package into the official sdk.

### License

This package is released under the [MIT](https://github.com/stefanotorresi/influxdb-php-async/blob/master/LICENSE) license.


[InfluxDB]: https://github.com/influxdata/influxdb
[ReactPHP]: http://reactphp.org
[Composer]: https://getcomposer.org
[Buzz React]: https://github.com/clue/php-buzz-react
[ReactPHP promises]: https://github.com/reactphp/promise
[official influxdb-php client]: https://github.com/influxdata/influxdb-php
[react/socket documentation]: https://github.com/reactphp/socket#connector
