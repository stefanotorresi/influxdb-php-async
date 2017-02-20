<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync;

use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;

abstract class AbstractAsyncClient implements AsyncClient
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var LoopInterface
     */
    private $loop;

    public function __construct(array $options = [], LoopInterface $loop = null)
    {
        $this->options = array_merge(static::DEFAULT_OPTIONS, $options);

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

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function run(): void
    {
        $this->loop->run();
    }
}
