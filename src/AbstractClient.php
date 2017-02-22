<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;

abstract class AbstractClient implements AsyncClient
{
    const DEFAULT_OPTIONS = [
        'host'       => 'localhost',
        'port'       => 8086,
        'database'   => '',
        'username'   => '',
        'password'   => '',
        'ssl'        => false,
        'verifySSL'  => false,
        'timeout'    => 0,
    ];

    /**
     * These options are meant to be overridden by specific implementations.
     * The main use case for this is the `nameserver` option, which is only relevant for implementations using
     * react/socket-client, which performs domain name resolution in userland.
     *
     * @var array
     */
    protected static $clientOptions = [];

    /**
     * @var array
     */
    private $options;

    /**
     * @var LoopInterface
     */
    protected $loop;

    public function __construct(array $options = [], LoopInterface $loop = null)
    {
        $this->options = array_merge(static::DEFAULT_OPTIONS, static::$clientOptions, $options);

        if (! $loop) {
            $loop = LoopFactory::create();
        }

        $this->loop = $loop;
    }

    public function selectDatabase(string $database): void
    {
        $this->options['database'] = $database;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function run(): void
    {
        $this->loop->run();
    }

    protected function createBaseUri(): string
    {
        $options = $this->getOptions();
        $scheme  = 'http' . ($options['ssl'] ? 's' : '');

        return sprintf('%s://%s:%d', $scheme, $options['host'], $options['port']);
    }

    protected function createQueryUrl(string $query, array $params): string
    {
        $params      = $this->applyDatabaseParam($params);
        $params['q'] = $query;

        return 'query?' . http_build_query($params);
    }

    protected function createWriteUrl(array $params): string
    {
        $params = $this->applyDatabaseParam($params);

        return 'write?' . http_build_query($params);
    }

    protected function applyDatabaseParam(array $params): array
    {
        $params['db'] = $params['db'] ?? $this->getOptions()['database'];

        return $params;
    }

    protected function detectQueryMethod(string $query): string
    {
        return
            preg_match('/^(?:ALTER|CREATE|DELETE|DROP|GRANT|KILL|REVOKE)\b|(:?SELECT\b.*\bINTO\b)/', $query) === 1
            ? 'POST' : 'GET'
        ;
    }
}
