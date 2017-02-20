<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use React\Promise\ExtendedPromiseInterface;

interface Client
{
    public function query(string $query, array $params = []): ExtendedPromiseInterface;
    public function write(string $payload, array $params = []): ExtendedPromiseInterface;
}
