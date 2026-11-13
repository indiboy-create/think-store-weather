<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Youvision\ThinkStoreWeather\Support\Station;
use Youvision\ThinkStoreWeather\Support\WeatherClient;

class WeatherClientTest extends TestCase
{
    public function testQueryReturnsRawBodyForWhitelistedStation(): void
    {
        $captured = null;
        $weather  = new WeatherClient('https://example.test/weather?stationid=%s', 3, function (string $url) use (&$captured): ?string {
            $captured = $url;

            return '{"code":0,"msg":"success","data":{"real":{}}}';
        });

        $result = $weather->query(Station::DEFAULT_CODE);

        $this->assertSame('{"code":0,"msg":"success","data":{"real":{}}}', $result['body']);
        $this->assertSame(Station::DEFAULT_CODE, $result['stationid']);
        $this->assertFalse($result['fallback']);
        $this->assertSame('https://example.test/weather?stationid=' . Station::DEFAULT_CODE, $captured);
    }

    public function testUnknownStationFallsBackToDefaultStation(): void
    {
        $captured = null;
        $weather  = new WeatherClient('https://example.test/weather?stationid=%s', 3, function (string $url) use (&$captured): ?string {
            $captured = $url;

            return '{"code":0,"msg":"success"}';
        });

        $result = $weather->query('INVALID_CODE');

        $this->assertTrue($result['fallback']);
        $this->assertSame('INVALID_CODE', $result['requested']);
        $this->assertSame(Station::DEFAULT_CODE, $result['stationid']);
        $this->assertSame('https://example.test/weather?stationid=' . Station::DEFAULT_CODE, $captured);
    }

    public function testQueryThrowsWhenUpstreamReturnsNothing(): void
    {
        $weather = new WeatherClient('https://example.test/weather?stationid=%s', 3, static fn (): ?string => null);

        $this->expectException(RuntimeException::class);
        $weather->query(Station::DEFAULT_CODE);
    }
}