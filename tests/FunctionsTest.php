<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use function Thorr\InfluxDB\escapeFieldValue;
use function Thorr\InfluxDB\escapeMeasurement;
use function Thorr\InfluxDB\escapeQueryParam;
use function Thorr\InfluxDB\escapeTag;

class FunctionsTest extends TestCase
{
    /**
     * @dataProvider influxTagProvider
     */
    public function testEscapeTag(string $input, string $expectedOutput): void
    {
        assertSame($expectedOutput, escapeTag($input));
    }

    public function influxTagProvider(): array
    {
        return [
            [ 'foo bar', 'foo\\ bar' ],
            [ 'foo,bar', 'foo\\,bar' ],
            [ 'foo=bar', 'foo\\=bar' ],
            [ 'foo = bar', 'foo\\ \\=\\ bar' ],
            [ 'foo = bar,baz', 'foo\\ \\=\\ bar\\,baz' ],
        ];
    }

    /**
     * @dataProvider influxFieldValueProvider
     */
    public function testEscapeFieldValue(string $input, string $expectedOutput): void
    {
        assertSame($expectedOutput, escapeFieldValue($input));
    }

    public function influxFieldValueProvider()
    {
        return [
            [ 'foo"bar', 'foo\"bar' ],
            [ 'foo"bar"baz', 'foo\"bar\"baz' ],
        ];
    }

    /**
     * @dataProvider influxMeasurementProvider
     */
    public function testEscapeMeasurement(string $input, string $expectedOutput): void
    {
        assertSame($expectedOutput, escapeMeasurement($input));
    }

    public function influxMeasurementProvider()
    {
        return [
            [ 'foo bar', 'foo\ bar' ],
            [ 'foo,bar', 'foo\,bar' ],
            [ 'foo, bar', 'foo\,\ bar' ],
        ];
    }

    /**
     * @dataProvider influxQueryParamProvider
     */
    public function testEscapeQueryParam(string $input, string $expectedOutput): void
    {
        assertSame($expectedOutput, escapeQueryParam($input));
    }

    public function influxQueryParamProvider()
    {
        return [
            [ "foo'bar", "foo\\'bar" ],
            [ "foo'bar'", "foo\\'bar\\'" ],
        ];
    }
}
