<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkStoreWeather\Support\RefreshDenied;
use Youvision\ThinkStoreWeather\Support\Station;
use Youvision\ThinkStoreWeather\Weather;

class WeatherTest extends TestCase
{
    public function testQueryReturnsRawBodyForWhitelistedStation(): void
    {
        $weather = new Weather(
            'https://example.test/weather?stationid=%s',
            3,
            28800,
            '',
            static fn (): ?string => '{"code":0,"msg":"success"}'
        );

        $result = $weather->query(Station::DEFAULT_CODE);

        $this->assertSame('{"code":0,"msg":"success"}', $result['body']);
        $this->assertSame(Station::DEFAULT_CODE, $result['stationid']);
        $this->assertFalse($result['fallback']);
        $this->assertSame('miss', $result['cache']);
    }

    public function testUnknownStationFallsBackToDefaultStation(): void
    {
        $weather = new Weather(
            'https://example.test/weather?stationid=%s',
            3,
            28800,
            '',
            static fn (): ?string => '{"code":0}'
        );

        $result = $weather->query('INVALID_CODE');

        $this->assertTrue($result['fallback']);
        $this->assertSame(Station::DEFAULT_CODE, $result['stationid']);
    }

    public function testRefreshWithoutTokenIsDenied(): void
    {
        $weather = new Weather(
            'https://example.test/weather?stationid=%s',
            3,
            28800,
            'secret-token',
            static fn (): ?string => '{"code":0}'
        );

        $this->expectException(RefreshDenied::class);
        $weather->query(Station::DEFAULT_CODE, true);
    }

    public function testRefreshWithTokenBypassesCache(): void
    {
        $weather = new Weather(
            'https://example.test/weather?stationid=%s',
            3,
            28800,
            'secret-token',
            static fn (): ?string => '{"code":0}'
        );

        $result = $weather->query(Station::DEFAULT_CODE, true, 'secret-token');

        $this->assertSame('refresh', $result['cache']);
    }

    public function testQueryWithoutEndpointThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('天气接口地址未配置');

        (new Weather())->query(Station::DEFAULT_CODE);
    }

    public function testProvincesReturnsTree(): void
    {
        $this->assertNotEmpty((new Weather())->provinces());
    }
}