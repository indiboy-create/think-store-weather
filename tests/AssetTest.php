<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkStoreWeather\Controller\AssetController;

class AssetTest extends TestCase
{
    public function testResolveExistingAsset(): void
    {
        $this->assertNotNull(AssetController::resolve('css/site.css'));
        $this->assertNotNull(AssetController::resolve('js/app.js'));
        $this->assertNotNull(AssetController::resolve('layui/layui.js'));
        $this->assertNotNull(AssetController::resolve('layui/css/layui.css'));
        $this->assertNotNull(AssetController::resolve('layui/font/iconfont.woff2'));
    }

    public function testResolveRejectsTraversal(): void
    {
        $this->assertNull(AssetController::resolve('../composer.json'));
        $this->assertNull(AssetController::resolve('layui/../../composer.json'));
        $this->assertNull(AssetController::resolve('/etc/passwd'));
        $this->assertNull(AssetController::resolve(''));
    }

    public function testResolveRejectsMissingFile(): void
    {
        $this->assertNull(AssetController::resolve('layui/not-exist.js'));
        $this->assertNull(AssetController::resolve('layui'));
    }

    public function testMimeMapping(): void
    {
        $this->assertSame('text/css; charset=utf-8', AssetController::mime('css/site.css'));
        $this->assertSame('application/javascript; charset=utf-8', AssetController::mime('js/app.js'));
        $this->assertSame('font/woff2', AssetController::mime('iconfont.woff2'));
        $this->assertSame('image/svg+xml', AssetController::mime('favicon.svg'));
        $this->assertSame('application/octet-stream', AssetController::mime('archive.bin'));
    }
}