<?php
/**
 * Plugin Name:       WPICP
 * Plugin URI:        https://wpcy.com/icp
 * Description:       中国站点一站式备案资质公示、电子证照信任徽章与离线《广告法》内容合规巡检中枢。
 * Version:           4.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WenPai
 * Author URI:        https://wpcy.com/icp
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpicp
 * Domain Path:       /languages
 * Update URI:        https://updates.wenpai.net
 * Network:           true
 *
 * @package WenPai\ICP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPICP_VERSION' ) ) {
	define( 'WPICP_VERSION', '4.0.0' );
}
if ( ! defined( 'WPICP_PLUGIN_FILE' ) ) {
	define( 'WPICP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WPICP_PLUGIN_DIR' ) ) {
	define( 'WPICP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPICP_PLUGIN_URL' ) ) {
	define( 'WPICP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPICP_PLUGIN_BASENAME' ) ) {
	define( 'WPICP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Register vendored wenpai-admin-ui copy at file load time so plugins_loaded@1 winner election picks the newest kit.
$wpicp_kit_load = WPICP_PLUGIN_DIR . 'lib/wenpai-admin-ui/php/load.php';
if ( is_readable( $wpicp_kit_load ) ) {
	require_once $wpicp_kit_load;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'WenPai\\ICP\\';
		$len    = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
			return;
		}
		$relative = substr( $class_name, $len );
		$file     = WPICP_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

if ( ! class_exists( 'WPICP_Ad_Law', false ) ) {
	class_alias( \WenPai\ICP\Compliance\AdLawScanner::class, 'WPICP_Ad_Law' );
}

if ( ! function_exists( 'wpicp_scan_ad_law_html' ) ) {
	/**
	 * Public helper function for scanning HTML against Chinese Advertising Law triggers.
	 *
	 * @param string   $html      HTML fragment.
	 * @param string[] $allowlist Optional extra allowlisted phrases.
	 * @return array<int, array{term:string, category:string, selector:string, excerpt:string, suggestions:string[]}>
	 */
	function wpicp_scan_ad_law_html( $html, array $allowlist = array() ) {
		$merged = array_merge( \WenPai\ICP\Compliance\AdLawScanner::get_site_allowlist(), $allowlist );
		return \WenPai\ICP\Compliance\AdLawScanner::scan_html( (string) $html, $merged );
	}
}

register_activation_hook(
	__FILE__,
	static function ( $network_wide = false ) {
		\WenPai\ICP\Migration\LegacyMigrator::on_activate( (bool) $network_wide );
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'wpicp_daily_status_check' );
		wp_clear_scheduled_hook( 'wpicp_weekly_compliance_check' );
	}
);

add_action( 'plugins_loaded', array( \WenPai\ICP\Plugin::class, 'boot' ), 10 );
