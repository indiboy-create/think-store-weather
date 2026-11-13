<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkStoreWeather\Support\RefreshToken;

class RefreshTokenTest extends TestCase
{
    public function testMatchesConfiguredToken(): void
    {
        $this->assertTrue(RefreshToken::matches('secret-token', 'secret-token'));
    }

    public function testRejectsWrongToken(): void
    {
        $this->assertFalse(RefreshToken::matches('secret-token', 'another-token'));
    }

    public function testRejectsEmptyExpectedToken(): void
    {
        $this->assertFalse(RefreshToken::matches('secret-token', ''));
    }

    public function testRejectsEmptyGivenToken(): void
    {
        $this->assertFalse(RefreshToken::matches('', 'secret-token'));
    }
}