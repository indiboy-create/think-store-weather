<?php

declare(strict_types=1);

namespace Youvision\Lizi;

use think\Service as BaseService;
use Youvision\Lizi\Command\Serve;
use Youvision\Lizi\Middleware\Trace;

/**
 * 服务提供者。
 *
 * register() 只做绑定与配置合并，不产生 IO；
 * boot() 才注册命令与中间件。
 */
class Service extends BaseService
{
    public function register(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) include __DIR__ . '/../config/lizi.php';

        /** @var array<string, mixed> $current */
        $current = (array) $this->app->config->get('lizi', []);

        // 项目侧配置优先，包内默认值只负责补齐缺失的键
        $this->app->config->set(['lizi' => array_replace_recursive($defaults, $current)]);
    }

    public function boot(): void
    {
        // 开发命令只在 debug 模式下暴露，避免生产环境多出一个可执行入口
        if ($this->app->isDebug()) {
            $this->commands([Serve::class]);
        }

        if ($this->app->config->get('lizi.trace.enable', false)) {
            $this->app->middleware->add(Trace::class);
        }
    }
}
