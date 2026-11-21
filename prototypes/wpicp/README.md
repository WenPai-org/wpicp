# 文派备案（WPICP 4.0）后台原型

产品：`wpicp`（文派备案）
档位：A（`tiers.yml` → `wpicp: A`）
构建方式：由 `php prototypes/wpicp/build.php` 直接渲染真实插件模板与 kit helper，零手写 HTML，零内联 `style`。

## 覆盖页面与状态

- [x] `overview.html` — 概览 · 正常已配置态（双栏 `.wenpai-split--equal` + 实时合规栏三态零跳动预览）
- [x] `overview-empty.html` — 概览 · 空状态（`Wenpai_Admin_UI::next` 首次引导）
- [x] `overview-error.html` — 概览 · 格式异常与风险告警态
- [x] `settings.html` — 设置 · 简单视图（`layout => row` 宽屏左右两栏）
- [x] `settings-advanced.html` — 设置 · 高级视图（展开 EDI、生成式 AI、算法备案、扩展许可证与证照管理）
- [x] `audit.html` — 合规巡检与工具（离线《广告法》分批巡检、白名单、旧版重同步、导入导出与重置确认门）
- [x] `help.html` — 页内帮助（顶栏右侧入口，不占第四个主 Tab）

## 产品自留 CSS（`assets/css/admin.css` + `assets/css/frontend.css`）

- `.wpicp-preview`：概览页前台合规栏预览舞台
- `.wpicp-audit`：全站《广告法》巡检进度与摘录高亮
- `.wpicp-bar` / `.wpicp-badges` / `.wpicp-certificates`：前台零内联样式合规栏、徽章与 `<dialog>` 灯箱画廊
