# WPICP（文派备案 4.0）

中国 WordPress 站点一站式备案资质公示、电子证照信任徽章与离线《广告法》内容合规巡检中枢。

## 核心特性

- **全渠道备案与主体真源**：覆盖工信部 ICP 备案号、公安联网备案（31 省简称 + 14 位备案码查验链接）、增值电信许可证（EDI）、生成式 AI 服务备案号、境内互联网算法备案号、APP/小程序备案号与自定义行业许可证。
- **核心与生态双向同步**：与 WordPress 简体中文核心 `zh_cn_l10n_icp_num`、`cn_icp` / `cn_ga` 实时双向同步，并提供 `wenpai_site_compliance`、`wenpai_ad_law_scan`、`wpcy_service_status`、`wenpai_plugin_display` 标准钩子。
- **现代化前台渲染**：原生古腾堡动态区块（`wpicp/compliance-bar`、`wpicp/trust-badges`）、免改代码页脚自动挂载、语义短代码 `[wpicp]` 及 12 个旧版 `wpicp_*` 短代码别名兼容，零内联样式。
- **离线《广告法》巡检**：内置 45 条风险表达与 30 条安全复合词豁免，支持后台全站分批巡检与编辑器实时提醒。
- **旧版无损迁移**：自动导入旧版 `wpicp-license` 全部 17 项配置并平滑停用旧包，零自定义数据库表。

## 本地检查与测试

```bash
bash scripts/check.sh
php tests/run-tests.php
```

## 许可协议

GPL-2.0-or-later · [https://wpcy.com/icp](https://wpcy.com/icp)
