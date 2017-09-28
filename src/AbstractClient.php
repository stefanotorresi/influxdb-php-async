<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDB;

use React\EventLoop\LoopInterface;

abstract class AbstractClient implements AsyncClient
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var LoopInterface
     */
    protected $loop;

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
