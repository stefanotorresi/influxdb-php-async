<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDB;

use React\Promise\ExtendedPromiseInterface as Promise;

interface AsyncClient
{
    public function query(string $query, array $params = []): Promise;
    public function write(string $payload, array $params = []): Promise;
    public function ping(): Promise;

    public function selectDatabase(string $database): void;
    public function getOptions(): array;
    public function run(): void;
}
