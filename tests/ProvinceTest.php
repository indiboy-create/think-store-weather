<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkStoreWeather\Support\Province;
use Youvision\ThinkStoreWeather\Support\Station;

class ProvinceTest extends TestCase
{
    public function testTreeIsNotEmpty(): void
    {
        $this->assertNotEmpty(Province::tree());
    }

    public function testFindProvinceByTitle(): void
    {
        $province = Province::findProvince('河南省');

        $this->assertNotNull($province);
        $this->assertSame(Station::DEFAULT_PROVINCE, $province['title']);
        $this->assertNotEmpty($province['children']);
    }

    public function testLocateDefaultStation(): void
    {
        $location = Province::locate(Station::DEFAULT_CODE);

        $this->assertNotNull($location);
        $this->assertSame('河南省', $location['province']['title']);
        $this->assertSame(Station::DEFAULT_CITY, $location['city']['title']);
    }

    public function testLocateReturnsNullForUnknownStation(): void
    {
        $this->assertNull(Province::locate('NOT_A_STATION'));
    }
}