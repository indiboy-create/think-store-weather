<?php

declare(strict_types=1);

namespace Youvision\Lizi\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Youvision\Lizi\Support\Port;

class PortTest extends TestCase
{
    public function testNormalizeKeepsValidPort(): void
    {
        $this->assertSame(9877, Port::normalize(9877));
    }

    public function testNormalizeRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Port::normalize(0);
    }

    public function testNormalizeRejectsTooLargePort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Port::normalize(70000);
    }

    public function testFindSkipsOccupiedPort(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:9877', $errno, $errstr);

        if ($server === false) {
            $this->markTestSkipped('9877 端口不可用，跳过占用检测用例');
        }

        try {
            $port = Port::find('127.0.0.1', 9877, 5);

            $this->assertGreaterThan(9877, $port);
            $this->assertLessThanOrEqual(Port::MAX, $port);
        } finally {
            fclose($server);
        }
    }

    public function testFindThrowsWhenAllPortsBusy(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:9877', $errno, $errstr);

        if ($server === false) {
            $this->markTestSkipped('9877 端口不可用，跳过异常用例');
        }

        try {
            $this->expectException(\RuntimeException::class);

            // limit = 1，且 9877 已被占用，必定抛异常
            Port::find('127.0.0.1', 9877, 1);
        } finally {
            fclose($server);
        }
    }
}
