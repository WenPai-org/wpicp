# 文派备案（WPICP 4.0）后台原型

产品：`wpicp`（文派备案）
档位：A（`tiers.yml` → `wpicp: A`）
构建方式：由 `php prototypes/wpicp/build.php` 直接渲染真实插件模板与 kit helper，零手写 HTML，零内联 `style`。

## 顶栏按需 6 标签页与状态覆盖（单屏紧凑布局，拒绝超长单页）

- [x] `overview.html` / `overview-empty.html` / `overview-error.html` — **概览**（正常 / 空 / 错三态，双栏 `.wenpai-split--equal` + 实时合规栏三态零跳动预览）
- [x] `filing.html` / `filing-advanced.html` — **备案资质**（简单视图仅 4 个核心字段一屏到底；高级视图通过 `subnav` 分流「基础备案与举报」与「增值电信与算法备案」）
- [x] `badges.html` — **证照徽章**（通过 `subnav` 分流「官方与自定义徽章」与「电子证照亮照」）
- [x] `display.html` — **前台展示**（通过 `subnav` 分流「页脚自动输出与版式 + 实时预览」与「显示条目与高级策略」）
- [x] `audit.html` — **合规巡检**（通过 `subnav` 分流「极限词全站扫描」与「巡检规则与完备度核查」）
- [x] `tools.html` — **工具**（通过 `subnav` 分流「备份导出与重置」与「旧版迁移与卸载策略」）
- [x] `help.html` — **帮助**（顶栏右侧入口）

## 产品自留 CSS（`assets/css/admin.css` + `assets/css/frontend.css`）

- `.wpicp-preview`：前台合规栏预览舞台
- `.wpicp-audit`：全站《广告法》巡检进度与摘录高亮
- `.wpicp-bar` / `.wpicp-badges` / `.wpicp-certificates`：前台零内联样式合规栏、徽章与 `<dialog>` 灯箱画廊
