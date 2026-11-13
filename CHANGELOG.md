# Changelog

遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## 0.1.0 - 未发布

首个版本，处于 `dev-main` 阶段。

### 新增
- 服务提供者 `Youvision\ThinkStoreWeather\Service`，支持 ThinkPHP 8 自动发现（`extra.think.services`）
- 核心服务 `Youvision\ThinkStoreWeather\Weather`，绑定容器别名 `weather`，可脱离容器实例化
- 天气模块页面与接口：`/store/think-store-weather/index`、`api/provinces`、`api/weather`
- 天气响应缓存 8 小时（`cache_ttl`），命中返回 `X-Weather-Cache: hit`
- 强制刷新接口 `/store/think-store-weather/api/refresh`，通过 `token` 校验，令牌配置在 `config/weather.php`
- 上游地址与 Referer 统一由 `config/weather.php` 注入，源码不硬编码远程域名
- 上游请求失败时返回可诊断原因（SSL 证书、超时、HTTP 状态），不再统一为模糊提示
- 响应式天气页面：城市选择、实时天气、温度趋势 SVG、七日预报与原始 JSON 面板
- 城市电台播放器：`/store/think-store-weather/music` 输出包内 `zhengzhou.mp3`，支持 HTTP Range 与进度拖动
- 页面使用 `topthink/think-view` 模板引擎渲染 `theme/view/dashboard.html`，模板变量以 `{$var}` 注入
- 静态资源路由 `/store/assets/<path>`：直接输出包内 Layui 2.13.9 与页面资源，无需发布到 public；页面样式与脚本分类到 `assets/css/site.css` 与 `assets/js/app.js`
- 路由可配置：`weather.route.enabled`、`prefix`、`segment` 与 `middleware`
- 配置发布：`composer.json` 的 `extra.think.config.weather` 声明默认配置
- 商店接入：`composer.json` 的 `extra.store.home` 声明发布包首页，供 think-store 读取
- 非法站点自动回退默认城市郑州（`YVItN`），并通过 `X-Weather-Fallback` 标记
- `composer verify` 独立校验脚本，覆盖配置、路由、资源与核心服务行为