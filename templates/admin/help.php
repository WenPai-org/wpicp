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
	__( '了解备案同步、接入商子域名保活、电子证照水印与无言（WordYeah）合规巡检。', 'wpicp' )
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
						'title' => __( '填写基础备案与网站登记名称', 'wpicp' ),
						'body'  => __( '在「备案资质」页填入工信部 ICP 备案号、工信部登记网站名称、公安联网备案号及主办单位法定全称。保存后自动双向同步至 WordPress「设置 → 常规」中的 ICP 备案号选项。', 'wpicp' ),
					),
					array(
						'title' => __( '在顶部工作台配置页脚合规栏', 'wpicp' ),
						'body'  => __( '在「前台展示」页顶部预览工作台实时对比浅/深色页脚效果，按需开启自动页脚注入并选择极简单行、标准双行或含信任徽章版式；区块主题可直接插入「备案合规栏」或「信任徽章」区块。', 'wpicp' ),
					),
					array(
						'title' => __( '管理电子证照复印件与输出水印', 'wpicp' ),
						'body'  => __( '在「证照徽章 → 电子证照亮照」集中管理营业执照与行政许可证扫描件，可登记分类、编号与有效期（到期前 30 天提醒），并开启服务端水印合成与防右键另存保护。', 'wpicp' ),
					),
				),
			),
			array(
				'title'       => __( '接入商合规保活与无言（WordYeah）远程词库', 'wpicp' ),
				'desc'        => __( '按天/周低频保活探测与 api.wpcy.com 单向词库同步', 'wpicp' ),
				'icon'        => 'cloud',
				'collapsible' => true,
				'open'        => false,
				'numbered'    => false,
				'steps'       => array(
					array(
						'title' => __( '首页备案自检与接入商子域名保活', 'wpicp' ),
						'body'  => __( '每日自动检查首页可达性、底部备案号、beian.miit.gov.cn 官方链接与标题一致性。采用多云或 CDN 时，可导出单文件合规保活页部署至原备案接入商内地服务器，由插件按天或按周低频探测 DNS 归属与 HTTP 200 状态。', 'wpicp' ),
					),
					array(
						'title' => __( '无言（WordYeah）合规词库服务', 'wpicp' ),
						'body'  => __( '《广告法》极限词库与安全复合词由合规品牌无言（WordYeah）持续维护，插件通过 https://api.wpcy.com/wordyeah/v1/ad-law-rules.json 单向拉取规则 JSON 至本地缓存，不回传任何站点文章内容；网络受限时自动回退至内置基础词库。', 'wpicp' ),
					),
				),
			),
			array(
				'title'       => __( '古腾堡原生区块与传统短代码对照', 'wpicp' ),
				'desc'        => __( '推荐新站优先使用 4 个原生区块，同时 100% 兼容旧版 12 个短代码', 'wpicp' ),
				'icon'        => 'copy',
				'collapsible' => true,
				'open'        => false,
				'numbered'    => false,
				'steps'       => array(
					array(
						'title'     => __( '4 个原生古腾堡动态区块（推荐）', 'wpicp' ),
						'body_html' => '<p>' . esc_html__( '在区块编辑器或全站编辑（FSE）中直接搜索「文派」即可插入：', 'wpicp' )
							. '<code>文派合规公示栏 (wpicp/compliance-bar)</code>、'
							. '<code>文派信任徽章 (wpicp/trust-badges)</code>、'
							. '<code>文派证照公示合集 (wpicp/certificates-directory)</code>、'
							. '<code>文派单条备案信息 (wpicp/filing-field)</code>。'
							. esc_html__( '旧短代码区块亦支持在编辑器中一键转换为对应原生区块。', 'wpicp' ) . '</p>',
					),
					array(
						'title'     => __( '统一短代码与旧版 WPICP License 兼容', 'wpicp' ),
						'body_html' => '<p><code>[wpicp]</code> ' . esc_html__( '输出完整合规栏；', 'wpicp' ) . '<code>[wpicp_certificates layout="directory"]</code> ' . esc_html__( '输出带分类筛选的证照公示合集；完整保留全部 12 个历史短代码：', 'wpicp' ) . '<code>[wpicp_license]</code>、<code>[wpicp_wangan]</code>、<code>[wpicp_province]</code>、<code>[wpicp_p]</code>、<code>[wpicp_company]</code>、<code>[wpicp_email]</code>、<code>[wpicp_phone]</code>、<code>[wpicp_edi]</code>、<code>[wpicp_app]</code>、<code>[wpicp_miniapp]</code>、<code>[wpicp_certificates]</code>、<code>[wpicp_badges]</code>。</p>',
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
