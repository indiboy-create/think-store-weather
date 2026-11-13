<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkStoreWeather\Support\WeatherCache;

class WeatherCacheTest extends TestCase
{
    public function testKeyUsesStationPrefix(): void
    {
        $cache = new WeatherCache('weather:test:', 28800);

        $this->assertSame('weather:test:YVItN', $cache->key('YVItN'));
    }

    public function testGetDegradesGracefullyWhenCacheUnavailable(): void
    {
        $cache = new WeatherCache('weather:test:', 28800);

        $this->assertNull($cache->get('YVItN'));
    }

    public function testSetDoesNotThrowWhenCacheUnavailable(): void
    {
        $cache = new WeatherCache('weather:test:', 28800);

        $cache->set('YVItN', '{"code":0}');

        $this->assertNull($cache->get('YVItN'));
    }
}