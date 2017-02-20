<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\HandlerStack;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use WyriHaximus\React\GuzzlePsr7\HttpClientAdapter;

class HttpClient implements Client
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var Guzzle
     */
    private $guzzle;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var array
     */
    private $guzzleConfig;

    const DEFAULT_OPTIONS = [
        'host'      => 'localhost',
        'port'      => 8086,
        'database'  => '',
        'username'  => '',
        'password'  => '',
        'ssl'       => false,
        'verifySSL' => false,
        'timeout'   => 0,
    ];

    public function __construct(array $options, Guzzle $guzzle = null, LoopInterface $loop = null)
    {
        $options = array_merge(static::DEFAULT_OPTIONS, $options);

        if (! $guzzle) {
            $guzzle = new Guzzle();
        }

        if (! $loop) {
            $loop   = LoopFactory::create();
        }

        $guzzleReactAdapter = new HttpClientAdapter($loop);

        $scheme = 'http' . ($options['ssl'] ? 's' : '');

        $guzzleConfig = [
            'handler'  => HandlerStack::create($guzzleReactAdapter),
            'base_uri' => sprintf('%s://%s:%d', $scheme, $options['host'], $options['port']),
            'timeout'  => $options['timeout'],
            'verify'   => $options['verifySSL'],
        ];

        // add authentication to the driver if needed
        if (! empty($options['username']) && ! empty($options['password'])) {
            $guzzleConfig['auth'] = [ $options['username'], $options['password'] ];
        }

        $this->options      = $options;
        $this->guzzle       = $guzzle;
        $this->loop         = $loop;
        $this->guzzleConfig = $guzzleConfig;
    }

    public function query(string $query, array $params = []): ExtendedPromiseInterface
    {
        $params['db'] = $params['db'] ?? $this->options['database'];
        $params['q']  = $query;
        $url          = 'query?' . http_build_query($params);

        return $this->guzzle->requestAsync('GET', $url, $this->guzzleConfig);
    }

    public function write(string $payload, array $params = []): ExtendedPromiseInterface
    {
        $params['db'] = $params['db'] ?? $this->options['database'];
        $url          = 'write?' . http_build_query($params);
        $guzzleConfig = $this->guzzleConfig;

        $guzzleConfig['body'] = $payload;

        return $this->guzzle->requestAsync('POST', $url, $guzzleConfig);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function selectDatabase(string $database)
    {
        $this->options['database'] = $database;
    }

    public function run(): void
    {
        $this->loop->run();
    }
}
