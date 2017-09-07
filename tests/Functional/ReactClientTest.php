<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync\Test\Functional;

use Thorr\InfluxDBAsync\ReactHttpClient;

class ReactClientTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->client = new ReactHttpClient($this->options, $this->loop);
    }
}
