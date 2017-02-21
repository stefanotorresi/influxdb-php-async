<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDBAsync\Test\Functional;

use Thorr\InfluxDBAsync\GuzzleClient;

class GuzzleClientTestCase extends FunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->client = new GuzzleClient($this->options);
    }
}
