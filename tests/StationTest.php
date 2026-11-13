<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkStoreWeather\Support\Station;

class StationTest extends TestCase
{
    public function testDefaultStationIsWhitelisted(): void
    {
        $this->assertTrue(Station::isAllowed(Station::DEFAULT_CODE));
    }

    public function testUnknownStationFallsBackToDefault(): void
    {
        $this->assertSame(Station::DEFAULT_CODE, Station::normalize('NOT_A_STATION'));
    }

    public function testEmptyStationFallsBackToDefault(): void
    {
        $this->assertSame(Station::DEFAULT_CODE, Station::normalize('  '));
    }

    public function testWhitelistedStationIsKept(): void
    {
        $this->assertSame(Station::DEFAULT_CODE, Station::normalize(Station::DEFAULT_CODE));
    }

    public function testWhitelistContainsZhengzhou(): void
    {
        $this->assertArrayHasKey(Station::DEFAULT_CODE, Station::whitelist());
    }
}