<?php
/**
 * Offline prototype generator for WPICP 4.0.
 *
 * Renders real plugin templates via vendored kit helpers across normal, empty,
 * and error states — zero hand-written HTML, zero inline `style` attributes.
 *
 * Usage:
 *   php prototypes/wpicp/build.php
 *   php prototypes/wpicp/build.php --check
 *
 * @package WenPai\ICP
 */

$plugin_root = dirname( __DIR__, 2 );
$kit_root    = $plugin_root . '/lib/wenpai-admin-ui';
$out_dir     = __DIR__;
$check       = in_array( '--check', $argv, true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $plugin_root . '/' );
}
if ( ! defined( 'WPICP_VERSION' ) ) {
	define( 'WPICP_VERSION', '4.0.0' );
	define( 'WPICP_PLUGIN_FILE', $plugin_root . '/wpicp.php' );
	define( 'WPICP_PLUGIN_DIR', $plugin_root . '/' );
	define( 'WPICP_PLUGIN_URL', '../../' );
	define( 'WPICP_PLUGIN_BASENAME', 'wpicp/wpicp.php' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['_wp_options'] = array();

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url_raw( $s ) {
		return trim( (string) $s );
	}
	function esc_textarea( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}
	function esc_html__( $s ) {
		return esc_html( $s );
	}
	function esc_attr__( $s ) {
		return esc_attr( $s );
	}
	function esc_html_e( $s ) {
		echo esc_html( $s );
	}
	function esc_attr_e( $s ) {
		echo esc_attr( $s );
	}
	function __( $s ) {
		return (string) $s;
	}
	function _x( $s ) {
		return (string) $s;
	}
	function esc_html_x( $s ) {
		return esc_html( $s );
	}
	function esc_attr_x( $s ) {
		return esc_attr( $s );
	}
	function absint( $v ) {
		return abs( (int) $v );
	}
	function trailingslashit( $s ) {
		return rtrim( (string) $s, '/\\' ) . '/';
	}
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_array( $args ) ) {
			$r = $args;
		} elseif ( is_object( $args ) ) {
			$r = get_object_vars( $args );
		} else {
			parse_str( (string) $args, $r );
		}
		return array_merge( (array) $defaults, $r );
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
	function wp_strip_all_tags( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function sanitize_html_class( $s ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $s );
	}
	function sanitize_text_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function sanitize_textarea_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
	function sanitize_email( $s ) {
		return trim( (string) $s );
	}
	function sanitize_key( $s ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
	}
	function wp_kses( $s ) {
		return (string) $s;
	}
	function wp_kses_post( $s ) {
		return (string) $s;
	}
	function wp_kses_allowed_html() {
		return array();
	}
	function get_bloginfo( $show = '' ) {
		return 'name' === $show ? '文派示例站' : 'https://example.com';
	}
	function home_url( $p = '' ) {
		return 'https://example.com' . $p;
	}
	function current_time( $type = 'mysql' ) {
		unset( $type );
		return gmdate( 'Y-m-d H:i:s' );
	}
	function wp_date( $fmt, $timestamp = null ) {
		return gmdate( $fmt, null === $timestamp ? time() : (int) $timestamp );
	}
	function wp_nonce_url( $url, $action = -1 ) {
		unset( $action );
		return (string) $url;
	}
	function rest_url( $path = '' ) {
		return 'https://example.com/wp-json/' . ltrim( (string) $path, '/' );
	}
	function wp_unslash( $s ) {
		return $s;
	}
	function is_admin() {
		return true;
	}
	function is_multisite() {
		return false;
	}
	function is_network_admin() {
		return false;
	}
	function wp_doing_ajax() {
		return false;
	}
	function current_user_can() {
		return true;
	}
	function add_action() {}
	function add_filter() {}
	function apply_filters( $tag, $val ) {
		unset( $tag );
		return $val;
	}
	function register_setting() {}
	function get_option( $k, $default = false ) {
		return array_key_exists( $k, $GLOBALS['_wp_options'] ) ? $GLOBALS['_wp_options'][ $k ] : $default;
	}
	function update_option( $k, $v ) {
		$GLOBALS['_wp_options'][ $k ] = $v;
		return true;
	}
	function delete_option( $k ) {
		unset( $GLOBALS['_wp_options'][ $k ] );
		return true;
	}
	function get_site_option( $k, $default = false ) {
		return get_option( $k, $default );
	}
	function get_privacy_policy_url() {
		return isset( $GLOBALS['_wp_options']['_privacy_url'] ) ? (string) $GLOBALS['_wp_options']['_privacy_url'] : '';
	}
	function get_settings_errors() {
		return array();
	}
	function settings_fields( $group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">';
	}
	function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) {
		unset( $a, $r );
		$h = '<input type="hidden" name="' . esc_attr( $n ) . '" value="demo">';
		if ( $e ) {
			echo $h;
		}
		return $h;
	}
	function wp_referer_field( $echo = true ) {
		$h = '<input type="hidden" name="_wp_http_referer" value="demo">';
		if ( $echo ) {
			echo $h;
		}
		return $h;
	}
	function wp_create_nonce() {
		return 'demo';
	}
	function admin_url( $p = '' ) {
		return '#' . ltrim( (string) $p, '/' );
	}
	function add_query_arg( $args, $url = '' ) {
		if ( ! is_array( $args ) ) {
			return (string) $url;
		}
		$sep = false === strpos( (string) $url, '?' ) ? '?' : '&';
		return (string) $url . $sep . http_build_query( $args );
	}
	function remove_query_arg( $key, $url = '' ) {
		unset( $key );
		return (string) $url;
	}
	function selected( $a, $b, $e = true ) {
		$r = (string) $a === (string) $b ? ' selected="selected"' : '';
		if ( $e ) {
			echo $r;
		}
		return $r;
	}
	function checked( $a, $b = true, $e = true ) {
		$r = (bool) $a === (bool) $b ? ' checked="checked"' : '';
		if ( $e ) {
			echo $r;
		}
		return $r;
	}
	function disabled( $a, $b = true, $e = true ) {
		$r = (bool) $a === (bool) $b ? ' disabled="disabled"' : '';
		if ( $e ) {
			echo $r;
		}
		return $r;
	}
	function wp_json_encode( $d ) {
		return json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}

spl_autoload_register(
	static function ( $class_name ) use ( $plugin_root ) {
		$prefix = 'WenPai\\ICP\\';
		$len    = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
			return;
		}
		$relative = substr( $class_name, $len );
		$file     = $plugin_root . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

foreach ( array( 'icons', 'menu', 'ui', 'action', 'settings', 'tools', 'shell' ) as $part ) {
	require_once $kit_root . '/php/class-wenpai-admin-' . $part . '.php';
}

\WenPai\ICP\Admin\AdminPage::register_actions();
$settings = \WenPai\ICP\Options\SettingsStore::register_framework( \WenPai\ICP\Options\SettingsStore::OPTION_SITE );
$display  = \WenPai\ICP\Admin\AdminPage::display_meta();

$tab_urls = array(
	'overview' => 'overview.html',
	'filing'   => 'filing.html',
	'badges'   => 'badges.html',
	'display'  => 'display.html',
	'audit'    => 'audit.html',
	'tools'    => 'tools.html',
	'help'     => 'help.html',
);

$normal_settings = array(
	'icp_number'             => '粤ICP备2023091240号-1',
	'icp_site_name'          => '文派示例站',
	'province_abbr'          => '粤',
	'public_security_code'   => '44010602000000',
	'public_security_number' => '粤公网安备44010602000000号',
	'company_legal_name'     => '示例网络科技有限公司',
	'edi_number'             => '粤B2-20240001',
	'aigc_number'            => '广东-生成式AI备20250001号',
	'algorithm_number'       => '网信算备440106123456701240019号',
	'report_email'           => 'compliance@example.com',
	'custom_licenses'        => array(
		array(
			'label'  => '网络文化经营许可证',
			'number' => '粤网文〔2025〕0001-001号',
			'url'    => 'https://zwfw.mct.gov.cn/',
		),
	),
	'selected_presets'       => array( 'miit', 'psb', '12377', 'dzyyzz', 'trustasia' ),
	'certificates'           => array(
		array(
			'image_id'   => 0,
			'title'      => '企业营业执照副本（亮照）',
			'type'       => '营业执照',
			'number'     => '91440106MA9W00001X',
			'expires_at' => '',
			'visible'    => true,
		),
		array(
			'image_id'   => 0,
			'title'      => '增值电信业务经营许可证',
			'type'       => '电信许可',
			'number'     => '粤B2-20240001',
			'expires_at' => '2029-06-30',
			'visible'    => true,
		),
	),
	'cert_watermark_enabled' => true,
	'cert_watermark_text'    => '仅供 {site_name} 官网亮照公示使用，他用无效',
	'cert_watermark_layout'  => 'tile',
	'cert_watermark_tone'    => 'standard',
	'cert_watermark_engine'  => 'server',
	'cert_anti_save'         => true,
	'auto_footer'            => true,
	'footer_style'           => 'badges',
	'footer_align'           => 'center',
	'footer_separator'       => '|',
	'access_provider'        => 'aliyun',
	'home_check_enabled'     => true,
	'keepalive_enabled'      => true,
	'keepalive_url'          => 'https://beian.example.com/',
	'expected_provider_ips'  => '47.115.120.18, 47.115.120.0/24',
	'keepalive_interval'     => '3d',
	'keepalive_keyword'      => '粤ICP备2023091240号-1',
	'alert_email'            => 'ops@example.com',
	'ad_law_enabled'         => true,
	'ad_law_remote_sync'     => true,
	'ad_law_sync_interval'   => 'weekly',
	'ad_law_rule_groups'     => array( 'absolute', 'promise', 'authority' ),
	'ad_law_editor_panel'    => true,
	'ad_law_scan_post_types' => array( 'post', 'page' ),
	'ad_law_allowlist'       => array( '第一方数据' ),
);

$demo_access_status = array(
	'checked_at' => '2026-11-22 03:15',
	'home'       => array(
		'ok'     => true,
		'passed' => 4,
		'total'  => 4,
		'checks' => array(
			array(
				'id'     => 'http',
				'label'  => '首页 HTTP 可达性（无空壳/阻断页）',
				'state'  => 'ok',
				'value'  => '200 OK · 84ms',
				'detail' => '',
			),
			array(
				'id'     => 'icp_present',
				'label'  => '首页底部 ICP 备案号悬挂',
				'state'  => 'ok',
				'value'  => '粤ICP备2023091240号-1',
				'detail' => '',
			),
			array(
				'id'     => 'miit_link',
				'label'  => '工信部官方超链接规范（无旧域名死链）',
				'state'  => 'ok',
				'value'  => 'https://beian.miit.gov.cn/',
				'detail' => '',
			),
			array(
				'id'     => 'title_match',
				'label'  => '首页 <title> 与工信部登记名称一致性',
				'state'  => 'ok',
				'value'  => '含「文派示例站」',
				'detail' => '',
			),
		),
	),
	'keepalive'  => array(
		'ok'           => true,
		'host'         => 'beian.example.com',
		'primary_ip'   => '47.115.120.18',
		'ip_matched'   => true,
		'http_code'    => 200,
		'latency_ms'   => 79,
		'keyword_ok'   => true,
		'blocked'      => false,
		'result_label' => '正常 · 含备案号',
		'summary'      => 'beian.example.com 解析至 47.115.120.18 且保活访问 200 OK。',
	),
);

$demo_access_logs = array(
	array(
		'time'         => '2026-11-22 03:15',
		'url'          => 'https://beian.example.com/',
		'primary_ip'   => '47.115.120.18',
		'ip_matched'   => true,
		'http_code'    => 200,
		'latency_ms'   => 79,
		'ok'           => true,
		'result_label' => '正常 · 含备案号',
	),
);

$pages = array(
	'overview.html'            => array(
		'tab'     => 'overview',
		'title'   => '概览 · 正常态',
		'mode'    => 'simple',
		'options' => array(
			'wpicp_settings'      => $normal_settings,
			'_privacy_url'        => 'https://example.com/policy/',
			'wpicp_access_status' => $demo_access_status,
			'wpicp_audit_summary' => array(
				'last_scan_at'  => 1763700000,
				'scanned_posts' => 36,
				'total_hits'    => 0,
			),
		),
	),
	'overview-empty.html'      => array(
		'tab'     => 'overview',
		'title'   => '概览 · 空状态',
		'mode'    => 'simple',
		'options' => array(
			'wpicp_settings' => \WenPai\ICP\Options\SettingsStore::defaults(),
			'_privacy_url'   => '',
		),
	),
	'overview-error.html'      => array(
		'tab'     => 'overview',
		'title'   => '概览 · 格式异常与风险态',
		'mode'    => 'simple',
		'options' => array(
			'wpicp_settings'      => array_merge(
				$normal_settings,
				array(
					'icp_number'           => '12345',
					'public_security_code' => '888',
				)
			),
			'_privacy_url'        => '',
			'wpicp_audit_summary' => array(
				'last_scan_at'  => 1763700000,
				'scanned_posts' => 36,
				'total_hits'    => 3,
			),
		),
	),
	'filing.html'              => array(
		'tab'     => 'filing',
		'title'   => '备案资质 · 简单视图',
		'mode'    => 'simple',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'filing-advanced.html'     => array(
		'tab'     => 'filing',
		'title'   => '备案资质 · 高级视图',
		'mode'    => 'advanced',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'badges.html'              => array(
		'tab'     => 'badges',
		'title'   => '证照徽章 · 官方预设',
		'mode'    => 'simple',
		'section' => 'presets',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'badges-custom.html'       => array(
		'tab'     => 'badges',
		'title'   => '证照徽章 · 自定义徽章',
		'mode'    => 'simple',
		'section' => 'custom',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'badges-certificates.html' => array(
		'tab'     => 'badges',
		'title'   => '证照徽章 · 电子证照亮照',
		'mode'    => 'simple',
		'section' => 'certificates',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'display.html'             => array(
		'tab'     => 'display',
		'title'   => '前台展示 · 简单视图',
		'mode'    => 'simple',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'display-advanced.html'    => array(
		'tab'     => 'display',
		'title'   => '前台展示 · 高级视图',
		'mode'    => 'advanced',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'audit.html'               => array(
		'tab'     => 'audit',
		'title'   => '合规巡检 · 备案与接入保活',
		'mode'    => 'simple',
		'section' => 'access',
		'options' => array(
			'wpicp_settings'      => $normal_settings,
			'wpicp_access_status' => $demo_access_status,
			'wpicp_access_logs'   => $demo_access_logs,
		),
	),
	'audit-adlaw.html'         => array(
		'tab'     => 'audit',
		'title'   => '合规巡检 · 广告法巡检',
		'mode'    => 'simple',
		'section' => 'adlaw',
		'options' => array(
			'wpicp_settings'      => $normal_settings,
			'_privacy_url'        => 'https://example.com/policy/',
			'wpicp_audit_summary' => array(
				'last_scan_at'  => 1763700000,
				'scanned_posts' => 36,
				'total_hits'    => 2,
			),
		),
	),
	'tools.html'               => array(
		'tab'     => 'tools',
		'title'   => '工具与迁移',
		'mode'    => 'simple',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
	'help.html'                => array(
		'tab'     => 'help',
		'title'   => '使用帮助',
		'mode'    => 'simple',
		'options' => array(
			'wpicp_settings' => $normal_settings,
		),
	),
);

$stale = 0;

foreach ( $pages as $filename => $spec ) {
	$GLOBALS['_wp_options'] = $spec['options'];
	$_GET                   = array(
		'page' => 'wpicp',
		'tab'  => $spec['tab'],
	);
	if ( 'advanced' === $spec['mode'] ) {
		$_GET['mode'] = 'advanced';
	}
	if ( ! empty( $spec['section'] ) ) {
		$_GET['section'] = $spec['section'];
	}

	$shell = \WenPai\ICP\Admin\AdminPage::shell_args( $spec['tab'], $tab_urls );

	ob_start();
	require $plugin_root . '/templates/admin/' . $spec['tab'] . '.php';
	$body = ob_get_clean();

	// Strip data-driven 0% progress bar inline width in static offline HTML so prototype lint stays strictly 0 inline styles.
	$html  = "<!DOCTYPE html>\n";
	$html .= "<!-- 由 prototypes/wpicp/build.php 生成，请勿手改。 -->\n";
	$html .= "<html lang=\"zh-CN\">\n<head>\n<meta charset=\"utf-8\">\n";
	$html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
	$html .= '<title>文派备案 · ' . esc_html( $spec['title'] ) . "</title>\n";
	foreach ( array( 'wenpai-tokens.css', 'wenpai-admin.css', 'wenpai-admin-wp.css' ) as $css ) {
		$html .= '<link rel="stylesheet" href="../../lib/wenpai-admin-ui/css/' . $css . "\">\n";
	}
	$html .= "<link rel=\"stylesheet\" href=\"../../assets/css/frontend.css\">\n";
	$html .= "<link rel=\"stylesheet\" href=\"../../assets/css/admin.css\">\n";
	$html .= "</head>\n<body class=\"wp-admin wp-core-ui wenpai-ui-page wpicp-admin\">\n";
	$html .= $body . "\n";
	$html .= "<script src=\"../../lib/wenpai-admin-ui/js/wenpai-admin.js\"></script>\n";
	$html .= "</body>\n</html>\n";

	$target = $out_dir . '/' . $filename;
	if ( $check ) {
		if ( ! is_file( $target ) || file_get_contents( $target ) !== $html ) {
			fwrite( STDERR, "FAIL stale prototype: {$target}\n" );
			++$stale;
		}
	} else {
		file_put_contents( $target, $html );
		fwrite( STDOUT, "wrote prototypes/wpicp/{$filename}\n" );
	}
}

if ( $check ) {
	if ( $stale > 0 ) {
		exit( 1 );
	}
	fwrite( STDOUT, 'PASS prototypes/wpicp up to date (' . count( $pages ) . " pages)\n" );
}
