<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkStoreWeather\Support\AudioRange;

class AudioRangeTest extends TestCase
{
    public function testReturnsNullWithoutRange(): void
    {
        $this->assertNull(AudioRange::parse(null, 1000));
        $this->assertNull(AudioRange::parse('', 1000));
        $this->assertNull(AudioRange::parse('   ', 1000));
    }

    public function testParseClosedRange(): void
    {
        $this->assertSame(
            ['start' => 0, 'end' => 99, 'partial' => true],
            AudioRange::parse('bytes=0-99', 1000)
        );
    }

    public function testParseOpenEndedRange(): void
    {
        $this->assertSame(
            ['start' => 100, 'end' => 999, 'partial' => true],
            AudioRange::parse('bytes=100-', 1000)
        );
    }

    public function testParseSuffixRange(): void
    {
        $this->assertSame(
            ['start' => 900, 'end' => 999, 'partial' => true],
            AudioRange::parse('bytes=-100', 1000)
        );
    }

    public function testClampsEndToFileSize(): void
    {
        $this->assertSame(
            ['start' => 900, 'end' => 999, 'partial' => true],
            AudioRange::parse('bytes=900-5000', 1000)
        );
    }

    public function testRejectsInvalidOrOutOfBoundsRange(): void
    {
        $this->assertNull(AudioRange::parse('bytes=abc', 1000));
        $this->assertNull(AudioRange::parse('bytes=-', 1000));
        $this->assertNull(AudioRange::parse('bytes=2000-3000', 1000));
        $this->assertNull(AudioRange::parse('bytes=300-200', 1000));
    }
}