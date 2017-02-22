<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync\Test;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromise;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;
use Thorr\InfluxDBAsync\GuzzleClient;

class GuzzleClientTest extends TestCase
{
    /**
     * @var GuzzleClient
     */
    private $SUT;

    /**
     * @var Guzzle|MockObject
     */
    private $guzzle;

    /**
     * @var LoopInterface|MockObject
     */
    private $loop;

    protected function setUp()
    {
        $this->loop   = $this->createMock(LoopInterface::class);
        $this->guzzle = $this->createMock(Guzzle::class);

        $this->SUT = new GuzzleClient([], $this->loop, $this->guzzle);
    }

    public function testOptionsConstructor()
    {
        $sutOptions = (new GuzzleClient([]))->getOptions();

        foreach (GuzzleClient::DEFAULT_OPTIONS as $option => $value) {
            static::assertArrayHasKey($option, $sutOptions);
            static::assertSame($value, $sutOptions[$option]);
        }
    }

    public function testQueryProxiesToGuzzleRequestAsync()
    {
        $guzzlePromise = $this->createMock(GuzzlePromise::class);
        $query         = 'foobar';

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('GET', "query?db=&q=$query", static::isType('array'))
            ->willReturn($guzzlePromise)
        ;

        $result = $this->SUT->query($query);

        static::assertInstanceOf(ExtendedPromiseInterface::class, $result);
    }

    public function testWriteProxiesToGuzzleRequestAsync()
    {
        $guzzlePromise = $this->createMock(GuzzlePromise::class);
        $payload       = 'foobar';

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('POST', 'write?db=', static::callback(function ($guzzleConfig) use ($payload) {
                static::assertInternalType('array', $guzzleConfig);
                static::assertArrayHasKey('body', $guzzleConfig);
                static::assertEquals($guzzleConfig['body'], $payload);

                return true;
            }))
            ->willReturn($guzzlePromise)
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
        $this->SUT = new GuzzleClient($options, $this->loop, $this->guzzle);

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('GET', 'query?db=&q=', static::callback(function ($guzzleConfig) use ($expectedUrl) {
                static::assertEquals($expectedUrl, $guzzleConfig['base_uri']);

                return true;
            }))
            ->willReturn($this->createMock(GuzzlePromise::class))
        ;

        $this->SUT->query('');
    }

    public function urlOptionsProvider()
    {
        return [
            [
                [], 'http://localhost:8086',
            ],
            [
                [
                    'ssl'  => 'true',
                    'host' => 'foobar',
                    'port' => 666,
                ],
                'https://foobar:666',
            ],
        ];
    }

    /**
     * @param array $options
     * @param array $expectedAuth
     *
     * @dataProvider authenticationOptionsProvider
     */
    public function testAuthenticationOptions(array $options, array $expectedAuth)
    {
        $this->SUT = new GuzzleClient($options, $this->loop, $this->guzzle);

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('GET', 'query?db=&q=', static::callback(function ($guzzleConfig) use ($expectedAuth) {
                if (empty($expectedAuth)) {
                    static::assertArrayNotHasKey('auth', $guzzleConfig);
                } else {
                    static::assertEquals($expectedAuth, $guzzleConfig['auth']);
                }

                return true;
            }))
            ->willReturn($this->createMock(GuzzlePromise::class))
        ;

        $this->SUT->query('');
    }

    public function authenticationOptionsProvider()
    {
        return [
            [
                [], [],
            ],
            [
                [
                    'username' => 'foo',
                ],
                [],
            ],
            [
                [
                    'password' => 'foo',
                ],
                [],
            ],
            [
                [
                    'username' => 'foo',
                    'password' => 'bar',
                ],
                [ 'foo', 'bar' ],
            ],
        ];
    }

    public function testTimeoutOption()
    {
        $options = [
            'timeout' => 666,
        ];

        $this->SUT = new GuzzleClient($options, $this->loop, $this->guzzle);

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('GET', 'query?db=&q=', static::callback(function ($guzzleConfig) use ($options) {
                static::assertSame($options['timeout'], $guzzleConfig['timeout']);

                return true;
            }))
            ->willReturn($this->createMock(GuzzlePromise::class))
        ;

        $this->SUT->query('');
    }

    public function testVerifySSLOption()
    {
        $options = [
            'verifySSL' => true,
        ];

        $this->SUT = new GuzzleClient($options, $this->loop, $this->guzzle);

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('GET', 'query?db=&q=', static::callback(function ($guzzleConfig) use ($options) {
                static::assertSame($options['verifySSL'], $guzzleConfig['verify']);

                return true;
            }))
            ->willReturn($this->createMock(GuzzlePromise::class))
        ;

        $this->SUT->query('');
    }

    public function testSelectDatabase()
    {
        $database = 'foobar';
        $this->SUT->selectDatabase($database);

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('GET', "query?db=$database&q=", static::isType('array'))
            ->willReturn($this->createMock(GuzzlePromise::class))
        ;

        $this->SUT->query('');
    }

    public function testSelectedDatabaseCanBeOverriddenByParam()
    {
        $this->SUT->selectDatabase('foobar');

        $database = 'barbaz';

        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('GET', "query?db=$database&q=", static::isType('array'))
            ->willReturn($this->createMock(GuzzlePromise::class))
        ;

        $this->SUT->query('', ['db' => $database]);
    }

    public function testPingProxiesToGuzzleRequestAsync()
    {
        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with('HEAD', 'ping', static::isType('array'))
            ->willReturn($this->createMock(GuzzlePromise::class))
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
        $this->guzzle
            ->expects(static::once())
            ->method('requestAsync')
            ->with(strtoupper($verb), 'query?db=&q=' . urlencode($query), static::isType('array'))
            ->willReturn($this->createMock(ExtendedPromiseInterface::class))
        ;

        $this->SUT->query($query);
    }

    public function queryVerbProvider()
    {
        return [
            [ 'SELECT * FROM "mymeas"', 'get' ],
            [ 'SELECT * INTO "newmeas" FROM "mymeas"', 'post' ],
            [ 'CREATE DATABASE "mydb"', 'post' ],
            [ 'CREATE RETENTION POLICY four_weeks ON mydb DURATION 4w REPLICATION 1;', 'post' ],
            [ 'SELECT "water_level" INTO "h2o_feet_copy_1" FROM "h2o_feet" WHERE "location" = "new_york"', 'post' ],
            [ 'SHOW CONTINUOUS QUERIES', 'get' ],
            [ 'ALTER RETENTION POLICY "1h.cpu" ON "mydb" DEFAULT', 'post' ],
            [ 'CREATE DATABASE "foo"', 'post' ],
            [ 'CREATE RETENTION POLICY "10m.events" ON "somedb" DURATION 60m REPLICATION 2 DEFAULT', 'post' ],
            [ 'CREATE SUBSCRIPTION "sub0" ON "mydb"."autogen" DESTINATIONS ALL "udp://example.com:9090"', 'post' ],
            [ 'CREATE USER "jdoe" WITH PASSWORD "1337password"', 'post'  ],
        ];
    }
}
