<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDB\Test\Functional;

use Thorr\InfluxDB\ReactHttpClient;

class ReactClientTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->client = new ReactHttpClient($this->options, $this->loop);
    }
}
