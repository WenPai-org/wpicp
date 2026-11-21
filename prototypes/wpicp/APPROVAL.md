# 后台界面原型批准

产品：`wpicp`（文派备案 4.0）
版本 / 任务：`feat/wpicp-4.0`（2026-11-21 开工包正式重设）
原型路径：`prototypes/wpicp/`

状态：**已按 2026-11-21 开工包定案实施**。

## 核验页面

- [x] 概览（正常 / 空 / 错三态：`overview.html`、`overview-empty.html`、`overview-error.html`）
- [x] 设置 · 简单（`settings.html`）
- [x] 设置 · 高级（`settings-advanced.html`）
- [x] 合规巡检与工具（`audit.html`）
- [x] 帮助（`help.html`）

## 守恒边界

- 插件头 `Plugin Name: WPICP`、`Text Domain: wpicp`、`Update URI: https://updates.wenpai.net`
- 零自定义数据库表；旧版 `wpicp-license` 17 项配置无损迁入与双向核心同步

批准依据：`docs/plans/wpicp-4.0/2026-11-21-wpicp-4.0-agent-handoff.md`
日期：2026-11-21
