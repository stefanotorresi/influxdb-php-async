<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDB\Test\Functional;

use Clue\React\Buzz\Message\ResponseException;
use PHPUnit\Framework\TestCase as BaseTestCase;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use Thorr\InfluxDB\AsyncClient;
use function Clue\React\Block\await;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var AsyncClient
     */
    protected $client;

    /**
     * @var LoopInterface
     */
    protected $loop;

    protected $options = [
        'host'     => '127.0.0.1',
        'port'     => 8086,
        'database' => 'test',
    ];

    protected function setUp()
    {
        $address = sprintf('tcp://%s:%s', $this->options['host'], $this->options['port']);
        $socket  = @stream_socket_client($address);
        if (! $socket) {
            static::markTestSkipped("InfluxDB doesn't appear to be running on $address. Cannot run functional tests");

            return;
        }
        fclose($socket);

        $this->loop = LoopFactory::create();
    }

    public function testCanCreateDB()
    {
        $response = await($this->client->query('CREATE DATABASE test'), $this->loop);

        $responseString = (string) $response->getBody();
        static::assertSame(200, $response->getStatusCode());
        static::assertNotContains('deprecated', $responseString);
        static::assertNotContains('error', $responseString);
    }

    /**
     * @depends testCanCreateDB
     */
    public function testShowDatabases()
    {
        $response = await($this->client->query('SHOW DATABASES'), $this->loop);

        static::assertSame(200, $response->getStatusCode());
        $responseString = (string) $response->getBody();
        $responseArray  = json_decode($responseString, true);
        static::assertTrue(isset($responseArray['results'][0]['series'][0]['name']));
        static::assertTrue(isset($responseArray['results'][0]['series'][0]['values']));
        static::assertEquals('databases', $responseArray['results'][0]['series'][0]['name']);
        static::assertContains([ 'test' ], $responseArray['results'][0]['series'][0]['values']);
    }

    /**
     * @depends testCanCreateDB
     */
    public function testSelectEverything()
    {
        $response = await($this->client->query('SELECT * FROM /.*/'), $this->loop);

        $responseString = (string) $response->getBody();
        static::assertSame(200, $response->getStatusCode());
        static::assertNotContains('error', $responseString);
    }

    /**
     * @depends testCanCreateDB
     */
    public function testWrite()
    {
        $response       = await($this->client->write('measure,tag="foo" value="bar"'), $this->loop);
        $responseString = (string) $response->getBody();
        static::assertSame(204, $response->getStatusCode());
        static::assertEmpty($responseString);
    }

    /**
     * @depends testWrite
     */
    public function testSelectIntoDatabases()
    {
        $query    = 'SELECT * INTO another_measure FROM measure';
        $response = await($this->client->query($query, ['pretty' => 'true']), $this->loop);

        static::assertSame(200, $response->getStatusCode());
        $responseString = (string) $response->getBody();
        $responseArray  = json_decode($responseString, true);
        static::assertTrue(isset($responseArray['results'][0]['series'][0]['name']));
        static::assertEquals('result', $responseArray['results'][0]['series'][0]['name']);
        static::assertContains('time', $responseArray['results'][0]['series'][0]['columns']);
        static::assertContains('written', $responseArray['results'][0]['series'][0]['columns']);
    }

    public function testMalformedQuery()
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Bad Request');

        await($this->client->query('foobarbaz'), $this->loop);
    }

    public function testMalformedWrite()
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Bad Request');

        await($this->client->write('foobarbaz'), $this->loop);
    }
}
