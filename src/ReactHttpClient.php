<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDB;

use Clue\React\Buzz;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface as Promise;
use React\Socket;

final class ReactHttpClient extends AbstractClient
{
    const DEFAULT_OPTIONS = [
        'host'           => 'localhost',
        'port'           => 8086,
        'database'       => '',
        'username'       => '',
        'password'       => '',
        'socket_options' => [],
    ];

    /**
     * @var Buzz\Browser
     */
    private $buzz;

    public function __construct(
        array $options = [],
        LoopInterface $loop = null,
        Socket\ConnectorInterface $connector = null,
        Buzz\Browser $buzz = null
    ) {
        $this->options = array_merge(static::DEFAULT_OPTIONS, $options);

        if (! $loop) {
            $loop = LoopFactory::create();
        }

        $this->loop = $loop;
        $this->buzz = $this->configureBuzz($connector, $buzz);
    }


    public function query(string $query, array $params = []): Promise
    {
        $url     = $this->createQueryUrl($query, $params);
        $headers = $this->createRequestHeaders();
        $method  = $this->detectQueryMethod($query);

        return $this->buzz->{$method}($url, $headers);
    }

    public function write(string $payload, array $params = []): Promise
    {
        $url     = $this->createWriteUrl($params);
        $headers = $this->createRequestHeaders();

        return $this->buzz->post($url, $headers, $payload);
    }

    public function ping(): Promise
    {
        return $this->buzz->head('ping');
    }

    private function createBaseUri(): string
    {
        $options = $this->getOptions();
        $scheme  = 'http' . (isset($options['socket_options']['tls']) ? 's' : '');

        return sprintf('%s://%s:%d', $scheme, $options['host'], $options['port']);
    }

    private function createRequestHeaders(): array
    {
        $headers = [];
        $options = $this->getOptions();

        if (! empty($options['username']) && ! empty($options['password'])) {
            $headers['Authorization'] = 'Basic ' . base64_encode(sprintf('%s:%s', $options['username'], $options['password']));
        }

        return $headers;
    }

    /**
     * @param Socket\ConnectorInterface $connector
     * @param Buzz\Browser              $buzz
     *
     * @return Buzz\Browser
     */
    public function configureBuzz(?Socket\ConnectorInterface $connector, ?Buzz\Browser $buzz): Buzz\Browser
    {
        if (! $connector) {
            $connector = new Socket\Connector($this->loop, $this->getOptions()['socket_options']);
        }

        $buzz = $buzz ?: new Buzz\Browser($this->loop, $connector);

        $baseUri = $this->createBaseUri();
        $buzz    = $buzz->withBase($baseUri);

        return $buzz;
    }
}
