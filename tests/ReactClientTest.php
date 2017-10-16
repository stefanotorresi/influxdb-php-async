<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDB\Test;

use Clue\React\Buzz\Browser;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Socket\ConnectorInterface;
use Thorr\InfluxDB\ReactHttpClient;

class ReactClientTest extends TestCase
{
    /**
     * @var ReactHttpClient
     */
    private $SUT;

    /**
     * @var LoopInterface|MockObject
     */
    private $loop;

    /**
     * @var ConnectorInterface|MockObject
     */
    private $connector;

    /**
     * @var Browser|MockObject
     */
    private $buzz;

    protected function setUp()
    {
        $this->loop      = Factory::create();
        $this->connector = $this->createMock(ConnectorInterface::class);
        $this->buzz      = $this->createMock(Browser::class);
        $this->buzz->expects(static::any())->method('withBase')->willReturn($this->buzz);

        $this->SUT = new ReactHttpClient([], $this->loop, $this->connector, $this->buzz);
    }

    public function testOptionsConstructor()
    {
        $sutOptions = (new ReactHttpClient([]))->getOptions();

        foreach (ReactHttpClient::DEFAULT_OPTIONS as $option => $value) {
            static::assertArrayHasKey($option, $sutOptions);
            static::assertSame($value, $sutOptions[$option]);
        }
    }

    public function testQueryProxiesToBuzzGet()
    {
        $query = 'foobar';

        $this->buzz
            ->expects(static::once())
            ->method('get')
            ->with("query?db=&q=$query", static::isType('array'))
            ->willReturn(new Promise(function () {
            }))
        ;

        $result = $this->SUT->query($query);

        static::assertInstanceOf(ExtendedPromiseInterface::class, $result);
    }

    public function testWriteProxiesToBuzzPost()
    {
        $payload = 'foobar';

        $this->buzz
            ->expects(static::once())
            ->method('post')
            ->with('write?db=', static::isType('array'), $payload)
            ->willReturn(new Promise(function () {
            }))
        ;

        $result = $this->SUT->write($payload);

        static::assertInstanceOf(ExtendedPromiseInterface::class, $result);
    }

    /**
     * @param array  $options
     * @param string $expectedUrl
     *
     * @dataProvider urlOptionsProvider
     */
    public function testUrlOptions(array $options, string $expectedUrl)
    {
        $this->buzz
            ->expects(static::once())
            ->method('withBase')
            ->with($expectedUrl)
            ->willReturn($this->buzz)
        ;

        new ReactHttpClient($options, $this->loop, $this->connector, $this->buzz);
    }

    public function urlOptionsProvider(): array
    {
        return [
            [
                [], 'http://localhost:8086',
            ],
            [
                [
                    'socket_options' => [
                        'tls' => true,
                    ],
                    'host' => 'foobar',
                    'port' => 666,
                ],
                'https://foobar:666',
            ],
        ];
    }

    /**
     * @param array       $options
     * @param string|null $expectedAuth
     *
     * @dataProvider authenticationOptionsProvider
     */
    public function testAuthenticationOptions(array $options, ?string $expectedAuth)
    {
        $this->SUT = new ReactHttpClient($options, $this->loop, $this->connector, $this->buzz);

        $this->buzz
            ->expects(static::once())
            ->method('get')
            ->with('query?db=&q=', static::callback(function ($headers) use ($expectedAuth) {
                if (empty($expectedAuth)) {
                    static::assertArrayNotHasKey('Authorization', $headers);
                } else {
                    static::assertEquals($expectedAuth, $headers['Authorization']);
                }

                return true;
            }))
            ->willReturn(new Promise(function () {
            }))
        ;

        $this->SUT->query('');
    }

    public function authenticationOptionsProvider()
    {
        return [
            [
                [],
                null,
            ],
            [
                [
                    'username' => 'foo',
                ],
                null,
            ],
            [
                [
                    'password' => 'foo',
                ],
                null,
            ],
            [
                [
                    'username' => 'foo',
                    'password' => 'bar',
                ],
                'Basic ' . base64_encode('foo:bar'),
            ],
        ];
    }

    public function testSelectDatabase()
    {
        $database = 'foobar';
        $this->SUT->selectDatabase($database);

        $this->buzz
            ->expects(static::once())
            ->method('get')
            ->with("query?db=$database&q=", static::isType('array'))
            ->willReturn(new Promise(function () {
            }))
        ;

        $this->SUT->query('');
    }

    public function testSelectedDatabaseCanBeOverriddenByParam()
    {
        $this->SUT->selectDatabase('foobar');

        $database = 'barbaz';

        $this->buzz
            ->expects(static::once())
            ->method('get')
            ->with("query?db=$database&q=", static::isType('array'))
            ->willReturn(new Promise(function () {
            }))
        ;

        $this->SUT->query('', ['db' => $database]);
    }

    public function testPingProxiesToBuzzHead()
    {
        $this->buzz
            ->expects(static::once())
            ->method('head')
            ->with('ping')
            ->willReturn(new Promise(function () {
            }))
        ;

        $result = $this->SUT->ping();

        static::assertInstanceOf(ExtendedPromiseInterface::class, $result);
    }

    /**
     * @param string $query
     * @param string $verb
     *
     * @dataProvider queryVerbProvider
     */
    public function testQueryTypeDeterminesHTTPVerb(string $query, string $verb)
    {
        $this->buzz
            ->expects(static::once())
            ->method($verb)
            ->with('query?db=&q=' . urlencode($query), static::isType('array'))
            ->willReturn(new Promise(function () {
            }))
        ;

        $this->SUT->query($query);
    }

    public function queryVerbProvider()
    {
        return [
            [ 'SELECT * FROM "mymeas"', 'GET' ],
            [ 'SELECT * INTO "newmeas" FROM "mymeas"', 'POST' ],
            [ 'CREATE DATABASE "mydb"', 'POST' ],
            [ 'CREATE RETENTION POLICY four_weeks ON mydb DURATION 4w REPLICATION 1;', 'POST' ],
            [ 'SELECT "water_level" INTO "h2o_feet_copy_1" FROM "h2o_feet" WHERE "location" = "new_york"', 'POST' ],
            [ 'SHOW CONTINUOUS QUERIES', 'GET' ],
            [ 'ALTER RETENTION POLICY "1h.cpu" ON "mydb" DEFAULT', 'POST' ],
            [ 'CREATE DATABASE "foo"', 'POST' ],
            [ 'CREATE RETENTION POLICY "10m.events" ON "somedb" DURATION 60m REPLICATION 2 DEFAULT', 'POST' ],
            [ 'CREATE SUBSCRIPTION "sub0" ON "mydb"."autogen" DESTINATIONS ALL "udp://example.com:9090"', 'POST' ],
            [ 'CREATE USER "jdoe" WITH PASSWORD "1337password"', 'POST'  ],
        ];
    }
}
