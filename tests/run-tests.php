<?php
/**
 * Automated unit & integration test suite for WPICP 4.0.
 *
 * Covers:
 *  1. Ad-Law offline scanner (45 triggers, 30 safe compounds, allowlist, script/style stripping, attributes).
 *  2. ICP & MPS parsers + 100% HTTPS official preset badges.
 *  3. Legacy `wpicp-license` 17-option lossless migration & auto-deactivation.
 *  4. Bidirectional sync with `zh_cn_l10n_icp_num`, `cn_icp`, and `cn_ga` (re-entrancy safe).
 *  5. Multisite `allow_subsite_override` enforcement & inheritance.
 *  6. `[wpicp]` + 12 legacy shortcodes + zero inline `style` attributes.
 *  7. 4 WenPai suite filter contracts (`wenpai_plugin_display`, `wpcy_service_status`, `wenpai_site_compliance`, `wenpai_ad_law_scan`).
 *  8. Self-audit of `readme.txt` and `README.md` against Ad-Law dictionary.
 *
 * @package WenPai\ICP\Tests
 */

$plugin_root = dirname( __DIR__ );

define( 'ABSPATH', $plugin_root . '/' );
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['_test_options']        = array();
$GLOBALS['_test_site_options']   = array();
$GLOBALS['_test_filters']        = array();
$GLOBALS['_test_shortcodes']     = array();
$GLOBALS['_test_active_plugins'] = array();
$GLOBALS['_test_is_multisite']   = false;
$GLOBALS['_test_is_net_admin']   = false;

function add_filter( $tag, $cb, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['_test_filters'][ $tag ][ $priority ][] = array(
		'cb'   => $cb,
		'args' => $accepted_args,
	);
	return true;
}

function add_action( $tag, $cb, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $tag, $cb, $priority, $accepted_args );
}

function apply_filters( $tag, $value, ...$extra ) {
	if ( empty( $GLOBALS['_test_filters'][ $tag ] ) ) {
		return $value;
	}
	ksort( $GLOBALS['_test_filters'][ $tag ] );
	$all_args = array_merge( array( $value ), $extra );
	foreach ( $GLOBALS['_test_filters'][ $tag ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$slice        = array_slice( $all_args, 0, max( 1, (int) $entry['args'] ) );
			$value        = call_user_func_array( $entry['cb'], $slice );
			$all_args[0] = $value;
		}
	}
	return $value;
}

function do_action( $tag, ...$args ) {
	if ( empty( $GLOBALS['_test_filters'][ $tag ] ) ) {
		return;
	}
	ksort( $GLOBALS['_test_filters'][ $tag ] );
	foreach ( $GLOBALS['_test_filters'][ $tag ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$slice = array_slice( $args, 0, (int) $entry['args'] );
			call_user_func_array( $entry['cb'], $slice );
		}
	}
}

function has_filter( $tag ) {
	return ! empty( $GLOBALS['_test_filters'][ $tag ] );
}

function get_option( $key, $fallback = false ) {
	$pre = apply_filters( 'pre_option_' . $key, false, $key, $fallback );
	if ( false !== $pre ) {
		return $pre;
	}
	return array_key_exists( $key, $GLOBALS['_test_options'] ) ? $GLOBALS['_test_options'][ $key ] : $fallback;
}

function update_option( $key, $value, $autoload = null ) {
	unset( $autoload );
	$existed   = array_key_exists( $key, $GLOBALS['_test_options'] );
	$old_value = $existed ? $GLOBALS['_test_options'][ $key ] : false;
	$filtered  = apply_filters( 'pre_update_option_' . $key, $value, $old_value, $key );
	if ( $existed && $filtered === $old_value ) {
		return false;
	}
	$GLOBALS['_test_options'][ $key ] = $filtered;
	if ( $existed ) {
		do_action( 'update_option_' . $key, $old_value, $filtered, $key );
	} else {
		do_action( 'add_option_' . $key, $key, $filtered );
	}
	return true;
}

function add_option( $key, $value = '', $deprecated = '', $autoload = 'yes' ) {
	unset( $deprecated, $autoload );
	if ( array_key_exists( $key, $GLOBALS['_test_options'] ) ) {
		return false;
	}
	$GLOBALS['_test_options'][ $key ] = $value;
	do_action( 'add_option_' . $key, $key, $value );
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['_test_options'][ $key ] );
	return true;
}

function get_site_option( $key, $fallback = false ) {
	return array_key_exists( $key, $GLOBALS['_test_site_options'] ) ? $GLOBALS['_test_site_options'][ $key ] : $fallback;
}

function update_site_option( $key, $value ) {
	$existed                                = array_key_exists( $key, $GLOBALS['_test_site_options'] );
	$old                                    = $existed ? $GLOBALS['_test_site_options'][ $key ] : false;
	$GLOBALS['_test_site_options'][ $key ] = $value;
	if ( $existed ) {
		do_action( 'update_site_option_' . $key, $key, $value, $old );
	} else {
		do_action( 'add_site_option_' . $key, $key, $value );
	}
	return true;
}

function delete_site_option( $key ) {
	unset( $GLOBALS['_test_site_options'][ $key ] );
	return true;
}

function is_multisite() {
	return (bool) $GLOBALS['_test_is_multisite'];
}

function is_network_admin() {
	return (bool) $GLOBALS['_test_is_net_admin'];
}

function is_admin() {
	return true;
}

function wp_doing_ajax() {
	return false;
}

function current_user_can() {
	return true;
}

function is_plugin_active( $plugin ) {
	return in_array( $plugin, $GLOBALS['_test_active_plugins'], true );
}

function is_plugin_active_for_network( $plugin ) {
	return in_array( 'net:' . $plugin, $GLOBALS['_test_active_plugins'], true );
}

function deactivate_plugins( $plugins, $silent = false, $network_wide = null ) {
	unset( $silent, $network_wide );
	foreach ( (array) $plugins as $p ) {
		$GLOBALS['_test_active_plugins'] = array_values(
			array_diff( $GLOBALS['_test_active_plugins'], array( $p, 'net:' . $p ) )
		);
	}
}

function register_activation_hook() {}
function register_deactivation_hook() {}
function wp_clear_scheduled_hook() {}
function register_setting() {}
function load_plugin_textdomain() {}

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . '/';
}
function plugin_dir_url( $file ) {
	unset( $file );
	return 'https://example.com/wp-content/plugins/wpicp/';
}
function plugin_basename( $file ) {
	return 'wpicp/' . basename( $file );
}
function trailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' ) . '/';
}
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
function __( $s ) {
	return (string) $s;
}
function _x( $s ) {
	return (string) $s;
}
function esc_html__( $s ) {
	return esc_html( $s );
}
function esc_attr__( $s ) {
	return esc_attr( $s );
}
function absint( $v ) {
	return abs( (int) $v );
}
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_textarea_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_email( $s ) {
	return filter_var( trim( (string) $s ), FILTER_VALIDATE_EMAIL ) ? trim( (string) $s ) : '';
}
function sanitize_key( $s ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
}
function sanitize_html_class( $s ) {
	return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $s );
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( (array) $defaults, (array) $args );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}
function wp_unslash( $s ) {
	return $s;
}
function wp_kses_post( $s ) {
	return (string) $s;
}
function wp_kses( $s ) {
	return (string) $s;
}
function wp_kses_allowed_html() {
	return array();
}
function wp_json_encode( $d ) {
	return json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}
function wp_date( $f ) {
	return gmdate( $f );
}
function admin_url( $p = '' ) {
	return 'https://example.com/wp-admin/' . ltrim( (string) $p, '/' );
}
function network_admin_url( $p = '' ) {
	return 'https://example.com/wp-admin/network/' . ltrim( (string) $p, '/' );
}
function home_url( $p = '' ) {
	return 'https://example.com/' . ltrim( (string) $p, '/' );
}
function rest_url( $p = '' ) {
	return 'https://example.com/wp-json/' . ltrim( (string) $p, '/' );
}
function get_bloginfo( $show = '' ) {
	return 'name' === $show ? '文派示例站' : 'https://example.com';
}
function add_query_arg( $args, $url = '' ) {
	if ( ! is_array( $args ) || empty( $args ) ) {
		return (string) $url;
	}
	$sep = false === strpos( (string) $url, '?' ) ? '?' : '&';
	return (string) $url . $sep . http_build_query( $args );
}
function get_privacy_policy_url() {
	return 'https://example.com/policy/';
}
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	unset( $size, $icon );
	$cls = isset( $attr['class'] ) ? ' class="' . esc_attr( $attr['class'] ) . '"' : '';
	return '<img src="https://example.com/uploads/img-' . (int) $id . '.png"' . $cls . ' alt="">';
}
function wp_get_attachment_image_url( $id ) {
	return 'https://example.com/uploads/img-' . (int) $id . '-full.png';
}
function get_permalink( $id ) {
	return 'https://example.com/?page_id=' . (int) $id;
}

function add_shortcode( $tag, $cb ) {
	$GLOBALS['_test_shortcodes'][ $tag ] = $cb;
}
function shortcode_atts( $pairs, $atts ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
function do_test_shortcode( $tag, $atts = array() ) {
	if ( ! isset( $GLOBALS['_test_shortcodes'][ $tag ] ) ) {
		return '';
	}
	return call_user_func( $GLOBALS['_test_shortcodes'][ $tag ], $atts );
}

// Boot WPICP.
require_once $plugin_root . '/wpicp.php';
do_action( 'plugins_loaded' );
do_action( 'init' );

$passed = 0;
$failed = 0;

function wpicp_assert( $cond, $label ) {
	global $passed, $failed;
	if ( $cond ) {
		++$passed;
	} else {
		++$failed;
		fwrite( STDERR, "FAIL: {$label}\n" );
	}
}

// -----------------------------------------------------------------------------
// 1. Ad-Law Offline Scanner Tests
// -----------------------------------------------------------------------------
$dict = \WenPai\ICP\Compliance\AdLawScanner::dictionary();
wpicp_assert( 45 === count( $dict ), 'Built-in Ad-Law dictionary has 45 expressions' );
wpicp_assert( 30 === count( \WenPai\ICP\Compliance\AdLawScanner::safe_compounds() ), 'Built-in safe compounds list has 30 expressions' );

foreach ( $dict as $term => $meta ) {
	$hits = wpicp_scan_ad_law_html( '<p>测试文案：' . $term . '</p>' );
	wpicp_assert( 1 === count( $hits ) && $hits[0]['term'] === $term, "Detects trigger term: {$term}" );
}

foreach ( \WenPai\ICP\Compliance\AdLawScanner::safe_compounds() as $safe ) {
	$hits = wpicp_scan_ad_law_html( '<p>我们在' . $safe . '完成了部署。</p>' );
	wpicp_assert( 0 === count( $hits ), "Safe compound produces zero false positives: {$safe}" );
}

// Script / style / comment stripping + attribute scanning + longest-match-first.
$complex_html = '<!-- 永久免费 --><script>var x = "全网最低";</script><style>.best{content:"最佳";}</style>'
	. '<div id="hero" data-bs-id="cta-1"><img alt="全网第一的方案" src="x.png"><input placeholder="第一时间获取"></div>';
$complex_hits = apply_filters( 'wenpai_ad_law_scan', array(), $complex_html, array() );
wpicp_assert( 1 === count( $complex_hits ), 'Strips script/style/comments, skips safe compound in placeholder, and matches的全网第一 once' );
wpicp_assert( '全网第一' === $complex_hits[0]['term'], 'Longest-term-first matches 全网第一 without double-firing 第一' );
wpicp_assert( false !== strpos( $complex_hits[0]['selector'], '[alt]' ), 'Attribute hit selector carries [alt]' );

// Custom allowlist neutralizes term.
$allowed_hits = apply_filters( 'wenpai_ad_law_scan', array(), '<p>全网第一品牌</p>', array( '全网第一品牌' ) );
wpicp_assert( 0 === count( $allowed_hits ), 'Caller allowlist masks custom phrase' );

// -----------------------------------------------------------------------------
// 2. ICP & MPS Parsers + HTTPS Preset Badges
// -----------------------------------------------------------------------------
$icp_parsed = \WenPai\ICP\Options\BadgeCatalog::parse_icp( ' 粤ICP备2023091240号-3 ' );
wpicp_assert( $icp_parsed['valid'] && '粤' === $icp_parsed['province'] && '粤ICP备2023091240号' === $icp_parsed['subject'] && '3' === $icp_parsed['sequence'], 'Parses ICP subject and -3 sequence' );

$mps_parsed = \WenPai\ICP\Options\BadgeCatalog::parse_mps( '粤公网安备 44010602000000号' );
wpicp_assert( $mps_parsed['valid'] && '粤' === $mps_parsed['province'] && '44010602000000' === $mps_parsed['code'], 'Extracts province and 14-digit code from full Wangan string' );
wpicp_assert( 'https://beian.mps.gov.cn/#/query/webSearch?code=44010602000000' === $mps_parsed['url'], 'Builds MPS query URL' );

foreach ( \WenPai\ICP\Options\BadgeCatalog::presets( true ) as $pkey => $pdata ) {
	wpicp_assert( 0 === strpos( $pdata['url'], 'https://' ), "Preset {$pkey} URL uses HTTPS" );
	wpicp_assert( is_file( $plugin_root . '/assets/images/' . $pdata['file'] ), "Preset {$pkey} image exists on disk" );
}
wpicp_assert( is_file( $plugin_root . '/assets/images/gongan.png' ), 'gongan.png exists on disk' );

// -----------------------------------------------------------------------------
// 3. Legacy `wpicp-license` 17-Option Migration & Auto-Takeover
// -----------------------------------------------------------------------------
$GLOBALS['_test_options']        = array();
$GLOBALS['_test_active_plugins'] = array( 'wpicp-license/wpicp-license.php' );

$legacy_seed = array(
	'wpicp_license'                => '京ICP备2021008888号-2',
	'wpicp_wangan'                 => '11010802030294',
	'wpicp_province'               => '京',
	'wpicp_company'                => '文派（北京）网络科技有限公司',
	'wpicp_email'                  => 'report@wenpai.org',
	'wpicp_phone'                  => '010-88886666',
	'wpicp_edi'                    => '京B2-20240099',
	'wpicp_app'                    => '京ICP备2021008888号-3A',
	'wpicp_miniapp'                => '京ICP备2021008888号-4X',
	'wpicp_certificates'           => array( array( 'image_id' => 42, 'title' => '电子营业执照' ) ),
	'wpicp_certificates_page'      => 18,
	'wpicp_badges'                 => array( array( 'image_id' => 55, 'url' => 'http://partner.example.com' ) ),
	'wpicp_selected_presets'       => array( 'miit', '12377', 'szcert' ),
	'wpicp_allow_subsite_override' => '1',
	'wpicp_default_image_size'     => 'full',
	'wpicp_custom_css_class'       => 'legacy-footer-cls',
	'wpicp_tab_visibility'         => array( 'wpicp_license_tab' => '1' ),
);
foreach ( $legacy_seed as $k => $v ) {
	$GLOBALS['_test_options'][ $k ] = $v;
}

\WenPai\ICP\Migration\LegacyMigrator::on_activate( false );

wpicp_assert( ! is_plugin_active( 'wpicp-license/wpicp-license.php' ), 'Auto-deactivates wpicp-license/wpicp-license.php after migration' );
wpicp_assert( \WenPai\ICP\Migration\LegacyMigrator::consume_takeover_notice(), 'Queues one-time legacy takeover notice' );

$migrated = \WenPai\ICP\Options\SettingsStore::effective();
wpicp_assert( '京ICP备2021008888号-2' === $migrated['icp_number'], 'Migrates wpicp_license' );
wpicp_assert( '11010802030294' === $migrated['public_security_code'], 'Migrates wpicp_wangan' );
wpicp_assert( '京公网安备11010802030294号' === $migrated['public_security_number'], 'Computes public_security_number' );
wpicp_assert( '文派（北京）网络科技有限公司' === $migrated['company_legal_name'], 'Migrates wpicp_company' );
wpicp_assert( '京B2-20240099' === $migrated['edi_number'], 'Migrates wpicp_edi' );
wpicp_assert( '京ICP备2021008888号-3A' === $migrated['app_number'], 'Migrates wpicp_app' );
wpicp_assert( '京ICP备2021008888号-4X' === $migrated['miniapp_number'], 'Migrates wpicp_miniapp' );
wpicp_assert( 1 === count( $migrated['certificates'] ) && 42 === $migrated['certificates'][0]['image_id'], 'Migrates wpicp_certificates' );
wpicp_assert( 18 === $migrated['certificates_page'], 'Migrates wpicp_certificates_page' );
wpicp_assert( 'https://partner.example.com' === $migrated['badges'][0]['url'], 'Upgrades http:// badge URL to https://' );
wpicp_assert( in_array( 'szcert', $migrated['selected_presets'], true ), 'Preserves legacy preset key szcert' );
wpicp_assert( 'full' === $migrated['default_image_size'], 'Migrates wpicp_default_image_size' );
wpicp_assert( 'legacy-footer-cls' === $migrated['custom_css_class'], 'Migrates wpicp_custom_css_class' );

// -----------------------------------------------------------------------------
// 4. Bidirectional Sync (`zh_cn_l10n_icp_num`, `cn_icp`, `cn_ga`)
// -----------------------------------------------------------------------------
wpicp_assert( '京ICP备2021008888号-2' === get_option( 'zh_cn_l10n_icp_num' ), 'Mirrors ICP to zh_cn_l10n_icp_num on save' );

update_option( 'zh_cn_l10n_icp_num', '沪ICP备2024099999号-1' );
$after_core = \WenPai\ICP\Options\SettingsStore::effective();
wpicp_assert( '沪ICP备2024099999号-1' === $after_core['icp_number'], 'External change to zh_cn_l10n_icp_num syncs into wpicp_settings' );
wpicp_assert( '沪ICP备2024099999号-1' === get_option( 'wpicp_license' ), 'External change to zh_cn_l10n_icp_num updates legacy mirror wpicp_license' );
wpicp_assert( '沪ICP备2024099999号-1' === get_option( 'cn_icp' ), 'External change to zh_cn_l10n_icp_num updates cn_icp' );

update_option( 'cn_ga', '沪公网安备 31011502008888号' );
$after_ga = \WenPai\ICP\Options\SettingsStore::effective();
wpicp_assert( '沪' === $after_ga['province_abbr'] && '31011502008888' === $after_ga['public_security_code'], 'External change to cn_ga syncs province and code into wpicp_settings' );

// -----------------------------------------------------------------------------
// 5. Shortcodes (12 Legacy + `[wpicp]`) & Zero Inline Styles
// -----------------------------------------------------------------------------
\WenPai\ICP\Options\SettingsStore::save_site(
	array(
		'aigc_number'      => '上海-生成式AI备20250088号',
		'algorithm_number' => '网信算备310115123456701240019号',
	)
);

$bar_html     = do_test_shortcode( 'wpicp', array( 'preset' => 'badges' ) );
$icp_sc       = do_test_shortcode( 'wpicp_license' );
$wangan_sc    = do_test_shortcode( 'wpicp_wangan' );
$prov_sc      = do_test_shortcode( 'wpicp_province' );
$prov_abbr_sc = do_test_shortcode( 'wpicp_p' );
$comp_sc      = do_test_shortcode( 'wpicp_company' );
$email_sc     = do_test_shortcode( 'wpicp_email' );
$phone_sc     = do_test_shortcode( 'wpicp_phone' );
$edi_sc       = do_test_shortcode( 'wpicp_edi' );
$app_sc       = do_test_shortcode( 'wpicp_app' );
$mini_sc      = do_test_shortcode( 'wpicp_miniapp' );
$certs_sc     = do_test_shortcode( 'wpicp_certificates' );
$badges_sc    = do_test_shortcode( 'wpicp_badges' );
$badges_no_p  = do_test_shortcode( 'wpicp_badges', array( 'preset' => 'false' ) );

wpicp_assert( false !== strpos( $bar_html, '沪ICP备2024099999号-1' ) && false !== strpos( $bar_html, '上海-生成式AI备20250088号' ), '[wpicp] renders ICP and AIGC filing numbers' );
wpicp_assert( false !== strpos( $icp_sc, 'https://beian.miit.gov.cn/' ), '[wpicp_license] links to MIIT HTTPS' );
wpicp_assert( false !== strpos( $wangan_sc, 'gongan.png' ) && false !== strpos( $wangan_sc, 'beian.mps.gov.cn/#/query/webSearch?code=31011502008888' ), '[wpicp_wangan] uses gongan.png and MPS query URL' );
wpicp_assert( '上海' === $prov_sc && '沪' === $prov_abbr_sc, '[wpicp_province] and [wpicp_p] output province name and abbr' );
wpicp_assert( '文派（北京）网络科技有限公司' === $comp_sc, '[wpicp_company] outputs company name' );
wpicp_assert( 'report@wenpai.org' === $email_sc && '010-88886666' === $phone_sc, '[wpicp_email] and [wpicp_phone] output contact info' );
wpicp_assert( '京B2-20240099' === $edi_sc && '京ICP备2021008888号-3A' === $app_sc && '京ICP备2021008888号-4X' === $mini_sc, 'EDI/APP/MiniApp shortcodes output values' );
wpicp_assert( false !== strpos( $certs_sc, '<dialog' ) && false === strpos( $certs_sc, 'style=' ), '[wpicp_certificates] renders dialog lightbox with zero inline styles' );
wpicp_assert( false !== strpos( $badges_sc, '12377.jpg' ) && false === strpos( $badges_no_p, '12377.jpg' ), '[wpicp_badges] includes presets by default and excludes when preset="false"' );
wpicp_assert( false === strpos( $bar_html . $badges_sc, 'style=' ), 'All frontend outputs have zero inline style attributes' );

// -----------------------------------------------------------------------------
// 6. Multisite Inheritance (`allow_subsite_override`)
// -----------------------------------------------------------------------------
$GLOBALS['_test_is_multisite'] = true;
\WenPai\ICP\Options\SettingsStore::save_network(
	array(
		'icp_number'             => '粤ICP备2023000001号',
		'company_legal_name'     => '集团总部有限公司',
		'allow_subsite_override' => false,
	)
);
$ms_locked = \WenPai\ICP\Options\SettingsStore::effective();
wpicp_assert( '粤ICP备2023000001号' === $ms_locked['icp_number'], 'Multisite enforces network settings when allow_subsite_override is false' );

\WenPai\ICP\Options\SettingsStore::save_network( array( 'allow_subsite_override' => true ) );
$ms_override = \WenPai\ICP\Options\SettingsStore::effective();
wpicp_assert( '沪ICP备2024099999号-1' === $ms_override['icp_number'], 'Multisite allows subsite override when allow_subsite_override is true' );
$GLOBALS['_test_is_multisite'] = false;

// -----------------------------------------------------------------------------
// 7. 4 WenPai Suite Filter Contracts
// -----------------------------------------------------------------------------
$comp_contract = apply_filters( 'wenpai_site_compliance', array() );
wpicp_assert(
	true === $comp_contract['wpicp_active']
	&& 'wpicp' === $comp_contract['source']
	&& '沪ICP备2024099999号-1' === $comp_contract['icp_number']
	&& '沪公网安备31011502008888号' === $comp_contract['public_security_number']
	&& '上海-生成式AI备20250088号' === $comp_contract['aigc_number']
	&& '网信算备310115123456701240019号' === $comp_contract['algorithm_number'],
	'wenpai_site_compliance contract returns complete authoritative compliance fields'
);

$wpcy_status = apply_filters( 'wpcy_service_status', array(), array() );
wpicp_assert( isset( $wpcy_status['compliance'] ) && 'ok' === $wpcy_status['compliance']['state'], 'wpcy_service_status populates compliance entry' );

add_filter(
	'wenpai_plugin_display',
	static function ( $d, $slug ) {
		if ( 'wpicp' === $slug ) {
			$d['brand'] = '客户定制备案中心';
		}
		return $d;
	},
	20,
	2
);
$wl_meta = \WenPai\ICP\Admin\AdminPage::display_meta();
wpicp_assert( '客户定制备案中心' === $wl_meta['brand'], 'wenpai_plugin_display white-label contract overrides brand name' );

// -----------------------------------------------------------------------------
// 8. Self-Audit of `readme.txt` and `README.md`
// -----------------------------------------------------------------------------
$readme_txt_hits = wpicp_scan_ad_law_html( '<pre>' . esc_html( (string) file_get_contents( $plugin_root . '/readme.txt' ) ) . '</pre>' );
$readme_md_hits  = wpicp_scan_ad_law_html( '<pre>' . esc_html( (string) file_get_contents( $plugin_root . '/README.md' ) ) . '</pre>' );
wpicp_assert( 0 === count( $readme_txt_hits ), 'readme.txt contains zero prohibited Ad-Law superlatives' );
wpicp_assert( 0 === count( $readme_md_hits ), 'README.md contains zero prohibited Ad-Law superlatives' );

// -----------------------------------------------------------------------------
// 9. AccessMonitor, WordYeahClient, and CertificateWatermark
// -----------------------------------------------------------------------------
wpicp_assert( \WenPai\ICP\Compliance\AccessMonitor::ip_in_rule( '47.115.120.18', '47.115.120.18' ), 'Exact IPv4 match passes' );
wpicp_assert( \WenPai\ICP\Compliance\AccessMonitor::ip_in_rule( '47.115.120.88', '47.115.120.0/24' ), 'IPv4 CIDR /24 match passes' );
wpicp_assert( ! \WenPai\ICP\Compliance\AccessMonitor::ip_in_rule( '104.21.12.34', '47.115.120.0/24' ), 'Out-of-range IPv4 fails CIDR match' );
wpicp_assert( \WenPai\ICP\Compliance\AccessMonitor::ip_in_rule( '2408:4005:100::12', '2408:4005:100::/48' ), 'IPv6 CIDR /48 match passes' );

$home_cfg  = \WenPai\ICP\Options\SettingsStore::normalize(
	array(
		'icp_number'         => '粤ICP备2023091240号-1',
		'icp_site_name'      => '文派示例站',
		'company_legal_name' => '文派科技有限公司',
	)
);
$good_html = '<!doctype html><html><head><title>文派示例站 - 首页</title></head><body><footer><a href="https://beian.miit.gov.cn/">粤ICP备2023091240号-1</a></footer></body></html>';
$home_ok   = \WenPai\ICP\Compliance\AccessMonitor::inspect_homepage_html( $good_html, 200, 62, $home_cfg );
wpicp_assert( true === $home_ok['ok'] && 4 === $home_ok['passed'], 'Valid homepage HTML passes all 4 MIIT checks' );

$legacy_link_html = '<!doctype html><html><head><title>文派示例站</title></head><body><a href="http://www.miitbeian.gov.cn/">粤ICP备2023091240号-1</a></body></html>';
$home_bad_link    = \WenPai\ICP\Compliance\AccessMonitor::inspect_homepage_html( $legacy_link_html, 200, 70, $home_cfg );
wpicp_assert( false === $home_bad_link['ok'] && 'bad' === $home_bad_link['checks'][2]['state'], 'Deprecated miitbeian.gov.cn link fails miit_link check' );

$blocked_html = '<!doctype html><html><head><title>提示</title></head><body>该域名因未备案被阻断访问</body></html>';
$home_blocked = \WenPai\ICP\Compliance\AccessMonitor::inspect_homepage_html( $blocked_html, 200, 30, $home_cfg );
wpicp_assert( false === $home_blocked['ok'] && 'bad' === $home_blocked['checks'][0]['state'], 'Cloud block splash page fails HTTP reachability check' );

$keepalive_doc = \WenPai\ICP\Compliance\AccessMonitor::generate_keepalive_html( $home_cfg );
wpicp_assert(
	false !== strpos( $keepalive_doc, '<title>文派示例站</title>' )
	&& false !== strpos( $keepalive_doc, '粤ICP备2023091240号-1' )
	&& false !== strpos( $keepalive_doc, 'https://beian.miit.gov.cn/' ),
	'Generated standalone keepalive index.html contains site title, ICP number, and MIIT URL'
);

// WordYeah remote ruleset payload sanitization, merging, and group filtering.
$wy_parsed = \WenPai\ICP\Compliance\WordYeahClient::sanitize_payload(
	array(
		'version'        => '2026.11',
		'terms'          => array(
			'无言远程测试词' => array(
				'category'    => 'regulated',
				'suggestions' => array( '合规表述' ),
			),
		),
		'safe_compounds' => array( '远程安全词组' ),
	)
);
wpicp_assert( '2026.11' === $wy_parsed['version'] && isset( $wy_parsed['terms']['无言远程测试词'] ), 'WordYeahClient sanitizes remote JSON payload cleanly' );

update_option(
	\WenPai\ICP\Compliance\WordYeahClient::CACHE_OPTION,
	array(
		'version'        => $wy_parsed['version'],
		'synced_at'      => '2026-11-22 10:00',
		'etag'           => '"wy-2026-11"',
		'terms'          => $wy_parsed['terms'],
		'safe_compounds' => $wy_parsed['safe_compounds'],
		'source'         => 'wordyeah-remote',
	)
);
\WenPai\ICP\Options\SettingsStore::save_site(
	array(
		'ad_law_remote_sync' => true,
		'ad_law_rule_groups' => array( 'absolute', 'promise', 'authority' ),
	)
);
$hits_without_reg = wpicp_scan_ad_law_html( '<p>包含无言远程测试词的内容</p>' );
wpicp_assert( 0 === count( $hits_without_reg ), 'Regulated category remote term is skipped when regulated group is unchecked' );

\WenPai\ICP\Options\SettingsStore::save_site(
	array(
		'ad_law_rule_groups' => array( 'absolute', 'promise', 'authority', 'regulated' ),
	)
);
$hits_with_reg = wpicp_scan_ad_law_html( '<p>包含无言远程测试词的内容</p>' );
wpicp_assert( 1 === count( $hits_with_reg ) && 'regulated' === $hits_with_reg[0]['category'], 'Regulated category remote term triggers when regulated group is enabled' );

// CertificateWatermark template interpolation & frontend output URL/anti-save attrs.
$wm_resolved = \WenPai\ICP\Media\CertificateWatermark::resolve_text( '仅供 {site_name} ({domain}) 于 {date} 使用', $home_cfg );
wpicp_assert(
	false !== strpos( $wm_resolved, '文派示例站' ) && false !== strpos( $wm_resolved, 'example.com' ),
	'CertificateWatermark interpolates {site_name}, {domain}, and {date}'
);
\WenPai\ICP\Options\SettingsStore::save_site(
	array(
		'cert_watermark_enabled' => true,
		'cert_watermark_engine'  => 'server',
		'cert_anti_save'         => true,
	)
);
$wm_certs_html = do_test_shortcode( 'wpicp_certificates' );
wpicp_assert(
	false !== strpos( $wm_certs_html, 'wpicp/v1/certificate/42' )
	&& false !== strpos( $wm_certs_html, 'data-wpicp-anti-save="1"' ),
	'Certificate gallery routes image through server watermark REST endpoint and sets anti-save attribute'
);

// Public Certificates Directory & 4 Native Gutenberg Blocks.
\WenPai\ICP\Options\SettingsStore::save_site(
	array(
		'certificates' => array(
			array(
				'image_id'   => 42,
				'title'      => '企业营业执照副本',
				'type'       => '营业执照',
				'number'     => '91440106MA9W00001X',
				'expires_at' => '',
				'visible'    => true,
			),
			array(
				'image_id'   => 43,
				'title'      => '食品经营许可证',
				'type'       => '食品经营许可',
				'number'     => 'JY14401060008888',
				'expires_at' => '2029-12-31',
				'visible'    => true,
			),
			array(
				'image_id'   => 44,
				'title'      => '软件著作权登记证书',
				'type'       => '软件著作权',
				'number'     => '2025SR0123456',
				'expires_at' => '',
				'visible'    => true,
			),
		),
	)
);
$dir_html = \WenPai\ICP\Blocks\ComplianceBlocks::render_certificates_directory_block(
	array(
		'columns'     => 3,
		'showHeader'  => true,
		'showFilters' => true,
		'showBadges'  => true,
	)
);
wpicp_assert(
	false !== strpos( $dir_html, 'data-wpicp-cert-dir="1"' )
	&& false !== strpos( $dir_html, '食品经营许可' )
	&& false !== strpos( $dir_html, '软件著作权' )
	&& false !== strpos( $dir_html, 'data-wpicp-dir-prev' )
	&& false === strpos( $dir_html, 'style=' ),
	'Certificates directory block renders custom categories, filters, prev/next dialog, and zero inline styles'
);

$field_block_html = \WenPai\ICP\Blocks\ComplianceBlocks::render_filing_field_block(
	array(
		'field'  => 'icp',
		'prefix' => '备案号：',
	)
);
wpicp_assert(
	false !== strpos( $field_block_html, '备案号：' )
	&& false !== strpos( $field_block_html, 'https://beian.miit.gov.cn/' ),
	'Single filing-field block renders prefix and official MIIT link'
);

$bar_with_cert_link = do_test_shortcode( 'wpicp' );
wpicp_assert(
	false !== strpos( $bar_with_cert_link, '?page_id=18' ),
	'Compliance bar renders bound certificates directory page link when certificates_page is set'
);

if ( $failed > 0 ) {
	fwrite( STDERR, "FAILED: {$failed} failed, {$passed} passed\n" );
	exit( 1 );
}

echo "PASS: wpicp test suite ({$passed} assertions, 0 failed)\n";
