<?php
/**
 * Admin In-App Help Page (`tab=help`, accessed from top-right header action).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

defined( 'ABSPATH' ) || exit;

$wpicp_docs_url  = isset( $display['docs_url'] ) ? (string) $display['docs_url'] : 'https://wpcy.com/document';
$wpicp_home_url  = isset( $display['help_url'] ) ? (string) $display['help_url'] : 'https://wpcy.com/icp';
$wpicp_docs_host = (string) wp_parse_url( $wpicp_docs_url, PHP_URL_HOST );
if ( '' === $wpicp_docs_host ) {
	$wpicp_docs_host = 'wpcy.com';
}

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '使用帮助', 'wpicp' ),
	__( '了解备案同步、短代码调用与巡检规则。', 'wpicp' )
);
?>
<div class="wenpai-stack">
<?php
Wenpai_Admin_UI::card_open();
Wenpai_Admin_UI::help(
	array(
		'groups' => array(
			array(
				'title'       => __( '快速上手与全站双向同步', 'wpicp' ),
				'desc'        => __( '一处维护，核心与生态自动同步', 'wpicp' ),
				'icon'        => 'shield-check',
				'collapsible' => true,
				'open'        => true,
				'numbered'    => true,
				'steps'       => array(
					array(
						'title' => __( '填写基础备案与主体名称', 'wpicp' ),
						'body'  => __( '在「备案资质」页简单模式下填入工信部 ICP 备案号、公安联网备案号及主办单位法定全称。保存后自动双向同步至 WordPress「设置 → 常规」中的 ICP 备案号选项。', 'wpicp' ),
					),
					array(
						'title' => __( '选择零代码前台输出方式', 'wpicp' ),
						'body'  => __( '在「前台展示」页可直接打开「自动注入站点页脚」开关并选择单行、双行或含徽章版式；全站编辑区块主题可在页脚模板中插入「备案合规栏」或「信任徽章」区块。', 'wpicp' ),
					),
					array(
						'title' => __( '按需维护高级许可与电子证照', 'wpicp' ),
						'body'  => __( '在「备案资质」页切至「高级」模式可补充增值电信 EDI、生成式 AI 与算法备案；在「证照徽章」页管理官方信任徽标与电子营业执照亮照。', 'wpicp' ),
					),
				),
			),
			array(
				'title'       => __( '语义短代码与旧版兼容对照', 'wpicp' ),
				'desc'        => __( '兼容旧版 12 个短代码，老站无缝升级', 'wpicp' ),
				'icon'        => 'copy',
				'collapsible' => true,
				'open'        => false,
				'numbered'    => false,
				'steps'       => array(
					array(
						'title'     => __( '新一代统一短代码', 'wpicp' ),
						'body_html' => '<p><code>[wpicp]</code> ' . esc_html__( '输出完整合规公示栏；支持按字段单独调用：', 'wpicp' ) . '<code>[wpicp field="icp|mps|company|edi|aigc|algorithm|app|miniapp|report|badges|certificates"]</code>。</p>',
					),
					array(
						'title'     => __( '旧版 WPICP License 短代码兼容', 'wpicp' ),
						'body_html' => '<p>' . esc_html__( '完整保留全部 12 个历史短代码别名：', 'wpicp' ) . '<code>[wpicp_license]</code>、<code>[wpicp_wangan]</code>、<code>[wpicp_province]</code>、<code>[wpicp_p]</code>、<code>[wpicp_company]</code>、<code>[wpicp_email]</code>、<code>[wpicp_phone]</code>、<code>[wpicp_edi]</code>、<code>[wpicp_app]</code>、<code>[wpicp_miniapp]</code>、<code>[wpicp_certificates]</code>、<code>[wpicp_badges]</code>。</p>',
					),
				),
			),
			array(
				'title'       => __( '离线《广告法》极限词巡检机制', 'wpicp' ),
				'desc'        => __( '本地 DOM 解析，支持复合词豁免与自定义白名单', 'wpicp' ),
				'icon'        => 'search',
				'collapsible' => true,
				'open'        => false,
				'numbered'    => false,
				'steps'       => array(
					array(
						'title' => __( '三类风险词与智能白名单脱敏', 'wpicp' ),
						'body'  => __( '扫描核内置 45 条常见违禁表达（绝对化用语、无法证实承诺、违规权威背书），并先行屏蔽「第一时间」「第一步」「唯一标识」等 30 个日常安全复合词，消除误报。', 'wpicp' ),
					),
					array(
						'title' => __( '编辑器实时提醒与文派心思协同', 'wpicp' ),
						'body'  => __( '在古腾堡编辑器侧边栏与发布前检查面板中实时提示风险词及 3 个合规替代词（提示不阻断发布）；站点同时启用文派心思（WPMind）时，可一键调用 AI 生成合规改写句。', 'wpicp' ),
					),
				),
			),
			array(
				'title'       => __( '多站点继承、导入导出与重置说明', 'wpicp' ),
				'desc'        => __( '安全备份与恢复边界', 'wpicp' ),
				'icon'        => 'tools',
				'collapsible' => true,
				'open'        => false,
				'numbered'    => false,
				'steps'       => array(
					array(
						'title' => __( 'WordPress 多站点网络管理', 'wpicp' ),
						'body'  => __( '网络管理中心「设置 → 备案」可配置全网默认备案与徽章；开启「允许子站点覆盖网络配置」后，各子站可单独填写自身主体与备案号。', 'wpicp' ),
					),
					array(
						'title'     => __( '重置与文档查阅', 'wpicp' ),
						'body_html' => '<p>' . sprintf(
							/* translators: %s: documentation domain */
							esc_html__( '「工具」页中的重置操作仅将插件选项恢复为初始默认值，不会删除已上传的媒体库图片或文章内容。更多集成说明请查阅 %s 在线文档。', 'wpicp' ),
							'<a href="' . esc_url( $wpicp_docs_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $wpicp_docs_host ) . '</a>'
						) . '</p>',
					),
				),
			),
		),
		'links'  => array(
			array(
				'label' => __( '在线文档中心', 'wpicp' ),
				'href'  => $wpicp_docs_url,
			),
			array(
				'label' => __( '产品主页', 'wpicp' ),
				'href'  => $wpicp_home_url,
			),
		),
	)
);
Wenpai_Admin_UI::card_close();
?>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
