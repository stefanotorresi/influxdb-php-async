<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync\Test\Functional;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use React\Promise\ExtendedPromiseInterface as Promise;
use Thorr\InfluxDBAsync\AsyncClient;

abstract class AbstractFunctionalTest extends TestCase
{
    /**
     * @var AsyncClient
     */
    protected $client;

    protected $options = [
        'host'     => '127.0.0.1',
        'port'     => 8086,
        'database' => 'test',
    ];

    protected function setUp()
    {
        $address = sprintf('tcp://%s:%s', $this->options['host'], $this->options['port']);
        $socket = @stream_socket_client($address);
        if (! $socket) {
            static::markTestSkipped("InfluxDB doesn't appear to be running on $address. Cannot run functional tests");
            return;
        }
        fclose($socket);
    }

    protected function tearDown()
    {
        $this->client->run();
    }

    public function testCanCreateDB(): Promise
    {
        $dbCreated = $this->client
            ->query('CREATE DATABASE test')
            ->then(
                function(Response $response) {
                    $responseString = (string) $response->getBody();
                    static::assertSame(200, $response->getStatusCode());
                    static::assertNotContains('deprecated', $responseString);
                    static::assertNotContains('error', $responseString);
                },
                function () {
                    static::fail('Request yielded a rejected promise');
                }
            )
        ;

        return $dbCreated;
    }

    /**
     * @depends testCanCreateDB
     */
    public function testShowDatabases(Promise $dbCreated)
    {
        $dbCreated->then(function() {
            return $this->client->query('SHOW DATABASES');
        })->done(
            function(Response $response) {
                static::assertSame(200, $response->getStatusCode());
                $responseString = (string) $response->getBody();
                $responseArray = json_decode($responseString, true);
                static::assertTrue(isset($responseArray['results'][0]['series'][0]['name']));
                static::assertTrue(isset($responseArray['results'][0]['series'][0]['values']));
                static::assertEquals('databases', $responseArray['results'][0]['series'][0]['name']);
                static::assertContains([ 'test' ], $responseArray['results'][0]['series'][0]['values']);
            },
            function () {
                static::fail('Request yielded a rejected promise');
            }
        );
    }

    /**
     * @depends testCanCreateDB
     */
    public function testSelectEverything(Promise $dbCreated)
    {
        $dbCreated->then(function(){
            return $this->client->query('SELECT * FROM /.*/');
        })->done(
            function(Response $response) {
                $responseString = (string) $response->getBody();
                static::assertSame(200, $response->getStatusCode());
                static::assertNotContains('error', $responseString);
            },
            function () {
                static::fail('Request yielded a rejected promise');
            }
        );
    }

    /**
     * @depends testCanCreateDB
     */
    public function testWrite(Promise $dbCreated): Promise
    {
        $measureWritten = $dbCreated->then(function(){
            return $this->client->write('measure,tag="foo" value="bar"');
        })->then(
            function(Response $response) {
                $responseString = (string) $response->getBody();
                static::assertSame(204, $response->getStatusCode());
                static::assertEmpty($responseString);
            },
            function () {
                static::fail('Request yielded a rejected promise');
            }
        );

        return $measureWritten;
    }

    /**
     * @depends testWrite
     */
    public function testSelectIntoDatabases(Promise $measureWritten)
    {
        $measureWritten->then(function () {
            return $this->client->query('SELECT * INTO another_measure FROM measure', ['pretty' => 'true']);
        })->done(
            function(Response $response) {
                static::assertSame(200, $response->getStatusCode());
                $responseString = (string) $response->getBody();
                $responseArray = json_decode($responseString, true);
                static::assertTrue(isset($responseArray['results'][0]['series'][0]['name']));
                static::assertEquals('result', $responseArray['results'][0]['series'][0]['name']);
                static::assertContains('time', $responseArray['results'][0]['series'][0]['columns']);
                static::assertContains('written', $responseArray['results'][0]['series'][0]['columns']);
            },
            function () {
                static::fail('Request yielded a rejected promise');
            }
        );
    }

    /**
     * @depends testCanCreateDB
     */
    public function testMalformedQuery(Promise $dbCreated)
    {
        $dbCreated->then(function(){
            return $this->client->query('foobarbaz');
        })->done(
            function() {
                static::fail('Request yielded a fullfilled promise');
            },
            function (Response $response) {
                $responseString = (string) $response->getBody();
                $responseArray = json_decode($responseString, true);
                static::assertSame(400, $response->getStatusCode());
                static::assertArrayHasKey('error', $responseArray);
                static::assertContains('error parsing query', $responseArray['error']);
            }
        );
    }

    /**
     * @depends testCanCreateDB
     */
    public function testMalformedWrite(Promise $dbCreated)
    {
        $dbCreated->then(function(){
            return $this->client->write('foobarbaz');
        })->done(
            function() {
                static::fail('Request yielded a fullfilled promise');
            },
            function (Response $response) {
                $responseString = (string) $response->getBody();
                $responseArray = json_decode($responseString, true);
                static::assertSame(400, $response->getStatusCode());
                static::assertArrayHasKey('error', $responseArray);
                static::assertContains('unable to parse', $responseArray['error']);
                static::assertContains('missing fields', $responseArray['error']);
            }
        );
    }
}
