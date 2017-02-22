<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use Closure;
use Clue\React\Buzz;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use React\SocketClient;
use function \React\Promise\reject as reject_promise;

final class BuzzReactClient extends AbstractClient
{
    /**
     * {@inheritdoc}
     */
    protected static $clientOptions = [
        'nameserver' => '8.8.8.8',
    ];

    /**
     * @var Buzz\Browser
     */
    private $buzz;

    public function __construct(array $options = [], LoopInterface $loop = null, Buzz\Browser $buzz = null)
    {
        parent::__construct($options, $loop);

        if (! $buzz) {
            $buzz = new Buzz\Browser($this->loop);
        }

        $this->buzz = $this->configureBuzz($buzz);
    }


    public function query(string $query, array $params = []): ExtendedPromiseInterface
    {
        $url     = $this->createQueryUrl($query, $params);
        $headers = $this->createRequestHeaders();
        $method  = $this->detectQueryMethod($query);

        return $this->buzz
            ->{$method}($url, $headers)
            ->otherwise(Closure::fromCallable([ $this, 'convertResponseExceptionToResponse' ]))
        ;
    }

    public function write(string $payload, array $params = []): ExtendedPromiseInterface
    {
        $url     = $this->createWriteUrl($params);
        $headers = $this->createRequestHeaders();

        return $this->buzz
            ->post($url, $headers, $payload)
            ->otherwise(Closure::fromCallable([ $this, 'convertResponseExceptionToResponse' ]))
        ;
    }

    public function ping(): ExtendedPromiseInterface
    {
        return $this->buzz->head('ping');
    }

    protected function configureBuzz(Buzz\Browser $buzz): Buzz\Browser
    {
        $options = $this->getOptions();
        $loop    = $this->loop;

        $dns       = (new ResolverFactory())->createCached($options['nameserver'], $loop);
        $connector = new SocketClient\DnsConnector(new SocketClient\TcpConnector($loop), $dns);

        if ($options['timeout'] > 0) {
            $connector = new SocketClient\TimeoutConnector($connector, $options['timeout'], $loop);
        }

        if ($options['ssl']) {
            $connector = new SocketClient\SecureConnector($connector, $loop, [
                'verify_peer'      => $options['verifySSL'],
                'verify_peer_name' => $options['verifySSL'],
            ]);
        }

        $sender = Buzz\Io\Sender::createFromLoopConnectors($loop, $connector);

        return $buzz
            ->withBase($this->createBaseUri())
            ->withSender($sender)
        ;
    }

    protected function createRequestHeaders(): array
    {
        $headers = [];
        $options = $this->getOptions();

        if (! empty($options['username']) && ! empty($options['password'])) {
            $headers['Authorization'] = 'Basic ' . base64_encode(sprintf('%s:%s', $options['username'], $options['password']));
        }

        return $headers;
    }

    private function convertResponseExceptionToResponse($exception)
    {
        if (! $exception instanceof Buzz\Message\ResponseException) {
            return reject_promise($exception);
        }

        return reject_promise($exception->getResponse());
    }
}
