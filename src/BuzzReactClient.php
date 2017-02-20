<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use Clue\React\Buzz;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use React\SocketClient;

class BuzzReactClient extends AbstractClient
{
    /**
     * @var Buzz\Browser
     */
    private $buzz;

    public function __construct(array $options = [], LoopInterface $loop = null, Buzz\Browser $buzz = null)
    {
        parent::__construct($options, $loop);

        if (! $buzz) {
            $buzz = $this->createBuzz();
        }

        $this->buzz = $buzz;
    }


    public function query(string $query, array $params = []): ExtendedPromiseInterface
    {
        $url = $this->createQueryUrl($query, $params);
        $headers = [
            'Authorization' => 'Basic ' . base64_encode("{$this->getOptions()['username']}:{$this->getOptions()['password']}")
        ];

        $this->buzz->get($url, $headers);
    }

    public function write(string $payload, array $params = []): ExtendedPromiseInterface
    {
        // TODO: Implement write() method.
    }

    protected function createBuzz(): Buzz\Browser
    {
        $options = $this->getOptions();
        $loop    = $this->getLoop();

        $dns       = (new ResolverFactory())->createCached($options['nameserver'], $loop);
        $tcp       = new SocketClient\TcpConnector($loop);
        $connector = new SocketClient\DnsConnector($tcp, $dns);

        if ($options['ssl']) {
            $connector = new SocketClient\SecureConnector($connector, $loop, [
                'verify_peer'      => $options['verifySSL'],
                'verify_peer_name' => $options['verifySSL']
            ]);
        }

        $sender = Buzz\Io\Sender::createFromLoopConnectors($loop, $connector);

        $buzz = (new Buzz\Browser($loop, $sender))
            ->withBase($this->createBaseUri());

        return $buzz;
    }
}
