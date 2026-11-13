<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather;

use think\App;
use think\view\driver\Think;

/**
 * 包内视图渲染器。
 *
 * 固定使用 think-view 的 Think 驱动，模板目录指向包内 theme/view，
 * 不依赖宿主项目的 view_path、view_suffix 等配置；模板缓存使用宿主
 * runtime 目录并加独立前缀，避免与其他应用的缓存互相覆盖。
 *
 * think-view 的 fetch() 直接输出内容，因此用输出缓冲捕获并返回字符串。
 */
class View
{
    protected string $path;

    protected ?Think $driver = null;

    public function __construct(protected App $app)
    {
        $this->path = dirname(__DIR__) . '/theme/view/';
    }

    /**
     * 渲染模板并返回 HTML。
     *
     * @param array<string, mixed> $vars
     */
    public function render(string $template, array $vars = []): string
    {
        $level = ob_get_level();

        ob_start();

        try {
            $this->driver()->fetch($this->templateFile($template), $vars);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * 解析模板文件，避免 think-view 按控制器名推导路径。
     */
    protected function templateFile(string $template): string
    {
        $file = $this->path . ltrim($template, '/\\');

        if (pathinfo($file, PATHINFO_EXTENSION) === '') {
            $file .= '.html';
        }

        return $file;
    }

    protected function driver(): Think
    {
        return $this->driver ??= new Think($this->app, [
            'view_path'    => $this->path,
            'view_suffix'  => 'html',
            'cache_path'   => $this->app->getRuntimePath() . 'temp' . DIRECTORY_SEPARATOR,
            'cache_prefix' => 'weather_',
        ]);
    }
}