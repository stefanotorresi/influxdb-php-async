<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\HandlerStack;
use React\Dns\Resolver;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use WyriHaximus\React\GuzzlePsr7\HttpClientAdapter;

final class GuzzleClient extends AbstractClient
{
    /**
     * @var Guzzle
     */
    private $guzzle;

    /**
     * @var array
     */
    private $guzzleConfig;

    /**
     * @var HttpClientAdapter
     */
    private $guzzleAdapter;

    public function __construct(array $options = [], LoopInterface $loop = null, Guzzle $guzzle = null)
    {
        parent::__construct($options, $loop);

        $options = $this->getOptions();
        $loop    = $this->getLoop();

        if (! $guzzle) {
            $guzzle = new Guzzle();
        }

        $dnsResolver = (new Resolver\Factory())->createCached($options['nameserver'], $loop);
        $this->guzzleAdapter = new HttpClientAdapter($loop, null, $dnsResolver);

        $guzzleConfig = [
            'handler'  => HandlerStack::create($this->guzzleAdapter),
            'base_uri' => $this->createBaseUri($options),
            'timeout'  => $options['timeout'],
            'verify'   => $options['verifySSL'],
        ];

        // add authentication to the driver if needed
        if (! empty($options['username']) && ! empty($options['password'])) {
            $guzzleConfig['auth'] = [ $options['username'], $options['password'] ];
        }

        $this->guzzle       = $guzzle;
        $this->guzzleConfig = $guzzleConfig;
    }

    public function query(string $query, array $params = []): ExtendedPromiseInterface
    {
        $url = $this->createQueryUrl($query, $params);

        $guzzlePromise = $this->guzzle->requestAsync('GET', $url, $this->guzzleConfig);

        return \React\Promise\resolve($guzzlePromise);
    }

    public function write(string $payload, array $params = []): ExtendedPromiseInterface
    {
        $params['db'] = $params['db'] ?? $this->getOptions()['database'];
        $url          = 'write?' . http_build_query($params);
        $guzzleConfig = $this->guzzleConfig;

        $guzzleConfig['body'] = $payload;

        $guzzlePromise = $this->guzzle->requestAsync('POST', $url, $guzzleConfig);

        return \React\Promise\resolve($guzzlePromise);
    }
}
