<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use React\EventLoop\LoopInterface;
use React\Promise\ExtendedPromiseInterface;

interface AsyncClient
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
        'nameserver' => '8.8.8.8',
    ];

    public function query(string $query, array $params = []): ExtendedPromiseInterface;
    public function write(string $payload, array $params = []): ExtendedPromiseInterface;

    public function selectDatabase(string $database): void;
    public function getOptions(): array;
    public function getLoop(): LoopInterface;
    public function run(): void;
}
