# Changelog

遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## 0.1.0 - 未发布

首个版本，处于 `dev-main` 阶段。

### 新增

- 服务提供者 `Youvision\ThinkStoreWeather\Service`，支持 ThinkPHP 8 自动发现（`extra.think.services`）
- 天气模块：`/weather` 页面、`/weather/api/provinces` 省市联动、`/weather/api/weather` 原样转发远程天气 JSON
- 天气响应缓存 8 小时（`cache_ttl`），命中返回 `X-Weather-Cache: hit`
- 强制刷新接口 `/weather/api/refresh`，通过 `token` 校验，令牌配置在 `config/weather.php`
- 响应式天气页面：城市选择、实时天气、温度趋势 SVG、七日预报与原始 JSON 面板
- 城市电台播放器：`/weather/music` 输出包内 `zhengzhou.mp3`，支持 HTTP Range 与进度拖动
- 页面使用 `topthink/think-view` 模板引擎渲染 `theme/view/dashboard.html`，模板变量以 `{$var}` 注入
- 静态资源路由 `/weather/assets/<path>`：直接输出包内 Layui 2.13.9 与页面资源，无需发布到 public
- 路由可配置：`weather.route.enabled`、`prefix` 与 `middleware`，静态资源路径支持相对前缀
- 非法站点自动回退默认城市郑州（`YVItN`），并通过 `X-Weather-Fallback` 标记