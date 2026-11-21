# WPICP（文派备案 4.0）

中国 WordPress 站点一站式备案资质公示、电子证照信任徽章与离线《广告法》内容合规巡检中枢。

## 核心特性

- **全渠道备案与主体真源**：集中维护工信部 ICP 备案号、公安联网备案（31 省简称 + 14 位备案码查验链接）、增值电信业务经营许可证（EDI）、生成式人工智能服务备案号、境内互联网算法备案号、APP/小程序备案号、自定义行业许可证与举报联系方式。
- **核心与生态双向同步**：与 WordPress 简体中文核心 `zh_cn_l10n_icp_num` 及文派语言包 `cn_icp` / `cn_ga` 实时双向同步，并提供 `wenpai_site_compliance`、`wenpai_ad_law_scan`、`wpcy_service_status`、`wenpai_plugin_display` 标准钩子。
- **按需分屏的 4.0 后台界面**：接入 `wenpai-admin-ui`（`v0.13.7`），按职责拆分为 **概览 / 备案资质 / 证照徽章 / 前台展示 / 合规巡检 / 工具** 6 个紧凑顶栏标签页与右侧帮助页，支持简单/高级切换、页内二级导航与带校验令牌的分页保存。
- **零内联样式前台渲染**：提供 `wpicp/compliance-bar` 与 `wpicp/trust-badges` 两个原生古腾堡动态区块、页脚自动挂载（极简文字单行 / 标准图文双行 / 完整信任徽章栏）、语义短代码 `[wpicp]` 及 12 个旧版 `wpicp_*` 短代码别名兼容。
- **离线《广告法》极限词巡检**：内置 45 条常见风险词与 30 条日常安全复合词豁免，支持后台全站分批巡检、古腾堡发布前与侧栏实时提醒，以及文派心思（WPMind）协同改写。
- **旧版无损平滑迁移**：自动导入旧版 `wpicp-license` 全部 17 项配置并停用旧插件，保留旧书签 `302` 跳转，零自定义数据库表。

## 短代码与区块

- 统一短代码：`[wpicp]`（支持 `preset="minimal|standard|badges"`、`align="center|left|between"`、`separator="||·|space"`）
- 单字段调用：`[wpicp field="icp|mps|company|edi|aigc|algorithm|app|miniapp|report|badges|certificates"]`
- 兼容旧短代码：`[wpicp_license]`、`[wpicp_wangan]`、`[wpicp_province]`、`[wpicp_p]`、`[wpicp_company]`、`[wpicp_email]`、`[wpicp_phone]`、`[wpicp_edi]`、`[wpicp_app]`、`[wpicp_miniapp]`、`[wpicp_certificates]`、`[wpicp_badges]`
- 古腾堡动态区块：`wpicp/compliance-bar`、`wpicp/trust-badges`

## 本地检查与测试

```bash
bash scripts/check.sh
php tests/run-tests.php
```

## 许可协议

GPL-2.0-or-later · [https://wpcy.com/icp](https://wpcy.com/icp)
