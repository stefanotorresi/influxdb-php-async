<?php
/**
 * @license See the file LICENSE for copying permission
 */

declare(strict_types = 1);

namespace Thorr\InfluxDB;

function escapeTag(string $string): string
{
    return str_replace([',', ' ', '='], ['\,', '\ ', '\='], $string);
}

function escapeFieldValue(string $string): string
{
    return str_replace('"', '\"', $string);
}

function escapeMeasurement(string $string): string
{
    return str_replace([',', ' '], ['\,', '\ '], $string);
}

function escapeQueryParam(string $string): string
{
    return str_replace('\'', '\\\'', $string);
}
