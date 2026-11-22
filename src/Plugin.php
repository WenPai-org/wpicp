<?php
/**
 * Main WPICP 4.0 plugin controller and WenPai suite contract provider.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP;

use WenPai\ICP\Admin\AdminPage;
use WenPai\ICP\Admin\EditorAudit;
use WenPai\ICP\Blocks\ComplianceBlocks;
use WenPai\ICP\Compliance\AccessMonitor;
use WenPai\ICP\Compliance\AdLawScanner;
use WenPai\ICP\Compliance\SiteHealthCheck;
use WenPai\ICP\Compliance\WordYeahClient;
use WenPai\ICP\Compliance\WpmindReviewer;
use WenPai\ICP\Frontend\Renderer;
use WenPai\ICP\Media\CertificateWatermark;
use WenPai\ICP\Migration\LegacyMigrator;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps all WPICP subsystems and registers the 4 WenPai ecosystem filter contracts.
 */
final class Plugin {

	/**
	 * Whether the plugin has already booted.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Boot WPICP subsystems on `plugins_loaded`.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		if ( ! class_exists( 'Wenpai_Admin_Settings', false ) && function_exists( 'wenpai_admin_ui_boot' ) ) {
			wenpai_admin_ui_boot();
			if ( function_exists( 'wenpai_admin_ui_complete' ) ) {
				wenpai_admin_ui_complete();
			}
		}

		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		}

		SettingsStore::register_hooks();
		LegacyMigrator::register_hooks();
		AdLawScanner::register();
		WordYeahClient::register_hooks();
		AccessMonitor::register_hooks();
		CertificateWatermark::register_hooks();
		SiteHealthCheck::register_hooks();
		Renderer::register_hooks();
		ComplianceBlocks::register_hooks();
		AuditController::register_hooks();
		EditorAudit::register_hooks();
		AdminPage::register_hooks();

		self::register_suite_contracts();

		if ( defined( 'WPICP_PLUGIN_BASENAME' ) && defined( 'WPICP_VERSION' ) ) {
			new Updater( WPICP_PLUGIN_BASENAME, WPICP_VERSION );
		}
	}

	/**
	 * Load translations on `init` (avoiding WP 6.7+ early-translation JIT notices).
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		if ( function_exists( 'load_plugin_textdomain' ) && defined( 'WPICP_PLUGIN_BASENAME' ) ) {
			load_plugin_textdomain( 'wpicp', false, dirname( WPICP_PLUGIN_BASENAME ) . '/languages' );
		}
	}

	/**
	 * Register suite-wide filter contracts consumed by WP-China-Yes 4.0 and Bisheng HTML.
	 *
	 * Contracts:
	 *  1. `wenpai_plugin_display`         (consumed in `AdminPage::display_meta()`)
	 *  2. `wpcy_service_status`           (populates `$status['compliance']` for WPCY Services tab)
	 *  3. `wenpai_site_compliance`        (single source of truth for Bisheng HTML & themes)
	 *  4. `wenpai_ad_law_scan`            (registered in `AdLawScanner::register()`)
	 *  5. `wpmind_ecosystem_integrations` (registers active semantic review integration in WPMind)
	 *
	 * @return void
	 */
	public static function register_suite_contracts() {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite contract.
		add_filter( 'wpcy_service_status', array( __CLASS__, 'filter_wpcy_service_status' ), 10, 2 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite contract.
		add_filter( 'wenpai_site_compliance', array( __CLASS__, 'filter_site_compliance' ), 10, 1 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite contract.
		add_filter( 'wpmind_ecosystem_integrations', array( __CLASS__, 'filter_wpmind_ecosystem_integrations' ), 10, 2 );
	}

	/**
	 * Populate `$status['compliance']` for WP-China-Yes 4.0 Services catalog.
	 *
	 * @param array<string, mixed> $status   Existing service status map.
	 * @param array<string, mixed> $settings WPCY settings snapshot (unused).
	 * @return array<string, mixed>
	 */
	public static function filter_wpcy_service_status( $status, $settings = array() ) {
		unset( $settings );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		$cfg        = SettingsStore::effective();
		$audit      = SiteHealthCheck::audit( $cfg );
		$manage_url = AdminPage::tab_url( 'filing' );

		$parts = array();
		if ( '' !== $cfg['icp_number'] ) {
			$parts[] = (string) $cfg['icp_number'];
		}
		if ( '' !== $cfg['public_security_number'] ) {
			$parts[] = (string) $cfg['public_security_number'];
		}

		if ( ! empty( $parts ) ) {
			$summary = implode( ' · ', $parts );
			$pill    = 'bad' === $audit['overall_state'] ? __( '格式待核', 'wpicp' ) : __( '已公示', 'wpicp' );
			$state   = 'bad' === $audit['overall_state'] ? 'warn' : 'ok';
		} else {
			$summary = __( '已启用文派备案，待填写 ICP 备案与主体资质', 'wpicp' );
			$pill    = __( '待填写', 'wpicp' );
			$state   = 'warn';
		}

		$status['compliance'] = array(
			'summary'      => $summary,
			'pill'         => $pill,
			'state'        => $state,
			'action_label' => __( '配置', 'wpicp' ),
			'action_url'   => $manage_url,
		);

		return $status;
	}

	/**
	 * Provide authoritative site compliance metadata for `wenpai_site_compliance`.
	 *
	 * Matches `BishengHtml_Wenpai_Suite::site_compliance()` and WPICP 4.0 handoff §4.3.
	 *
	 * @param array<string, mixed> $data Existing compliance array.
	 * @return array<string, mixed>
	 */
	public static function filter_site_compliance( $data = array() ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$cfg         = SettingsStore::effective();
		$privacy_url = function_exists( 'get_privacy_policy_url' )
			? esc_url_raw( (string) get_privacy_policy_url() )
			: '';

		return array_merge(
			$data,
			array(
				'company_legal_name'     => (string) $cfg['company_legal_name'],
				'icp_number'             => (string) $cfg['icp_number'],
				'public_security_number' => (string) $cfg['public_security_number'],
				'public_security_code'   => (string) $cfg['public_security_code'],
				'province_abbr'          => (string) $cfg['province_abbr'],
				'edi_number'             => (string) $cfg['edi_number'],
				'aigc_number'            => (string) $cfg['aigc_number'],
				'algorithm_number'       => (string) $cfg['algorithm_number'],
				'app_number'             => (string) $cfg['app_number'],
				'miniapp_number'         => (string) $cfg['miniapp_number'],
				'report_email'           => (string) $cfg['report_email'],
				'report_phone'           => (string) $cfg['report_phone'],
				'privacy_url'            => $privacy_url,
				'source'                 => 'wpicp',
				'manage_url'             => AdminPage::tab_url( 'filing' ),
				'wpicp_active'           => true,
			)
		);
	}

	/**
	 * Register WPICP's active semantic review entry in WPMind's ecosystem integrations list.
	 *
	 * @param array<int, array<string, mixed>>        $integrations Existing integration entries.
	 * @param array<string, array<string, int|float>> $by_caller    Optional per-caller stats keyed by slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_wpmind_ecosystem_integrations( $integrations, $by_caller = array() ) {
		if ( ! is_array( $integrations ) ) {
			$integrations = array();
		}

		if ( ! WpmindReviewer::is_enabled() ) {
			$filtered = array();
			foreach ( $integrations as $item ) {
				if ( is_array( $item ) && isset( $item['slug'] ) && 'wpicp' === (string) $item['slug'] && empty( $item['requests'] ) ) {
					continue;
				}
				$filtered[] = $item;
			}
			return array_values( $filtered );
		}

		$display = AdminPage::display_meta();
		$stats   = is_array( $by_caller ) && isset( $by_caller['wpicp'] ) && is_array( $by_caller['wpicp'] )
			? $by_caller['wpicp']
			: array(
				'requests' => 0,
				'tokens'   => 0,
			);

		$entry = array(
			'slug'         => 'wpicp',
			'name'         => sprintf(
				/* translators: %s: plugin brand name */
				__( '%s（WPICP）', 'wpicp' ),
				$display['brand']
			),
			'desc'         => __( '广告法极限词巡检已开启文派心思语义复核，结合语境剔除明显误报。', 'wpicp' ),
			'feature'      => __( '广告法语义复核', 'wpicp' ),
			'icon'         => 'lock',
			'version'      => defined( 'WPICP_VERSION' ) ? (string) WPICP_VERSION : '4.0.0',
			'settings_url' => AdminPage::tab_url( 'audit', false, array( 'section' => 'adlaw' ) ),
			'requests'     => (int) ( $stats['requests'] ?? 0 ),
			'tokens'       => (int) ( $stats['tokens'] ?? 0 ),
			'active'       => true,
		);

		$replaced = false;
		foreach ( $integrations as $idx => $item ) {
			if ( is_array( $item ) && isset( $item['slug'] ) && 'wpicp' === (string) $item['slug'] ) {
				$integrations[ $idx ] = array_merge( $item, $entry );
				$replaced             = true;
				break;
			}
		}

		if ( ! $replaced ) {
			$integrations[] = $entry;
		}

		return array_values( $integrations );
	}
}
