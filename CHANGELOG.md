# Changelog

遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## 0.1.0 - 未发布

首个版本，处于 `dev-main` 阶段。

### 新增

- 服务提供者 `Youvision\Lizi\Service`，支持 ThinkPHP 8 自动发现（`extra.think.services`）
- `php think lizi:serve` 命令：启动内置开发服务器，端口自动顺延
- `server/router.php`：静态资源直出、伪静态转发、路径穿越防护
- `Youvision\Lizi\Middleware\Trace`：响应头写入耗时与峰值内存
- `Youvision\Lizi\Support\Port`：不依赖框架的端口探测工具
