<?php
/**
 * Lossless migration from `wpicp-license` 1.x and cleanup of 2025 draft tables.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Migration;

use WenPai\ICP\Options\BadgeCatalog;
use WenPai\ICP\Options\SettingsStore;
use Wenpai_Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates all 17 legacy `wpicp-license` options, smoothly deactivates the old plugin,
 * redirects legacy admin bookmarks, and detects/cleans deprecated 2025 draft tables.
 */
class LegacyMigrator {

	const LEGACY_PLUGIN_FILE = 'wpicp-license/wpicp-license.php';
	const NOTICE_OPTION      = 'wpicp_legacy_takeover_notice';

	/**
	 * All 17 standalone option keys used by `wpicp-license` 1.8.x.
	 *
	 * @var string[]
	 */
	const LEGACY_KEYS = array(
		'wpicp_license',
		'wpicp_wangan',
		'wpicp_province',
		'wpicp_company',
		'wpicp_email',
		'wpicp_phone',
		'wpicp_edi',
		'wpicp_app',
		'wpicp_miniapp',
		'wpicp_certificates',
		'wpicp_certificates_page',
		'wpicp_badges',
		'wpicp_selected_presets',
		'wpicp_allow_subsite_override',
		'wpicp_default_image_size',
		'wpicp_custom_css_class',
		'wpicp_tab_visibility',
	);

	/**
	 * Deprecated custom table suffixes created by the abandoned 2025 draft.
	 *
	 * @var string[]
	 */
	const DEPRECATED_TABLE_SUFFIXES = array(
		'wpicp_records',
		'wpicp_sensitive_words',
		'wpicp_real_name_info',
		'wpicp_record_history',
		'wpicp_status',
	);

	/**
	 * Register runtime migration, auto-takeover, and bookmark redirect hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ), 5 );
		add_action( 'admin_page_access_denied', array( __CLASS__, 'maybe_redirect_legacy_page' ), 1 );
	}

	/**
	 * Run migration check, legacy plugin takeover, and legacy URL redirect in admin.
	 *
	 * @return void
	 */
	public static function on_admin_init() {
		self::maybe_migrate( false );
		self::maybe_takeover_legacy_plugin( false );
		self::maybe_redirect_legacy_page();
	}

	/**
	 * Run on `register_activation_hook`.
	 *
	 * @param bool $network_wide Whether activated network-wide on Multisite.
	 * @return void
	 */
	public static function on_activate( $network_wide = false ) {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( 'wpicp_daily_status_check' );
			wp_clear_scheduled_hook( 'wpicp_weekly_compliance_check' );
		}
		self::maybe_migrate( false );
		self::maybe_takeover_legacy_plugin( (bool) $network_wide );
	}

	/**
	 * Check whether any legacy `wpicp-license` or WordPress Core ICP option exists.
	 *
	 * @param bool $network Whether checking network-level options.
	 * @return bool
	 */
	public static function has_legacy_sources( $network = false ) {
		foreach ( self::LEGACY_KEYS as $key ) {
			$val = $network && function_exists( 'get_site_option' )
				? get_site_option( $key, null )
				: get_option( $key, null );
			if ( null !== $val && false !== $val && '' !== $val && array() !== $val ) {
				return true;
			}
		}
		if ( ! $network ) {
			foreach ( array( 'zh_cn_l10n_icp_num', 'cn_icp', 'cn_ga', 'wpicp_options' ) as $extra_key ) {
				$val = get_option( $extra_key, null );
				if ( null !== $val && false !== $val && '' !== $val && array() !== $val ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Migrate legacy options into `wpicp_settings` (and `wpicp_network_settings` on Multisite).
	 *
	 * @param bool $force When true, re-reads legacy options and fills/refreshes structured settings.
	 * @return bool True if migration wrote settings.
	 */
	public static function maybe_migrate( $force = false ) {
		$wrote = false;

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
			$existing_net = get_site_option( SettingsStore::OPTION_NETWORK, false );
			if ( $force || ! is_array( $existing_net ) ) {
				$imported_net = self::collect_legacy_payload( true );
				$merged_net   = is_array( $existing_net )
					? self::merge_preserving_modern( $existing_net, $imported_net )
					: $imported_net;
				SettingsStore::save_network( $merged_net );
				$wrote = true;
			}
		}

		$existing_site = get_option( SettingsStore::OPTION_SITE, false );
		if ( $force || ! is_array( $existing_site ) ) {
			$imported_site = self::collect_legacy_payload( false );
			$merged_site   = is_array( $existing_site )
				? self::merge_preserving_modern( $existing_site, $imported_site )
				: $imported_site;
			SettingsStore::save_site( $merged_site );
			update_option( 'wpicp_migrated_at', time(), false );
			$wrote = true;
		}

		return $wrote;
	}

	/**
	 * Build a structured settings payload from all 17 legacy `wpicp-license` keys,
	 * WordPress Core `zh_cn_l10n_icp_num` / `cn_icp` / `cn_ga`, and 2025 draft `wpicp_options`.
	 *
	 * @param bool $network Whether reading network options.
	 * @return array<string, mixed>
	 */
	public static function collect_legacy_payload( $network = false ) {
		$read = static function ( $key, $fallback = '' ) use ( $network ) {
			if ( $network && function_exists( 'get_site_option' ) ) {
				return get_site_option( $key, $fallback );
			}
			return get_option( $key, $fallback );
		};

		$draft_2025 = ! $network ? get_option( 'wpicp_options', array() ) : array();
		if ( ! is_array( $draft_2025 ) ) {
			$draft_2025 = array();
		}

		$icp = trim( (string) $read( 'wpicp_license', '' ) );
		if ( '' === $icp && ! $network ) {
			$icp = trim( (string) get_option( 'zh_cn_l10n_icp_num', '' ) );
		}
		if ( '' === $icp && ! $network ) {
			$icp = trim( (string) get_option( 'cn_icp', '' ) );
		}
		if ( '' === $icp && ! empty( $draft_2025['icp_number'] ) ) {
			$icp = trim( (string) $draft_2025['icp_number'] );
		}

		$wangan = trim( (string) $read( 'wpicp_wangan', '' ) );
		if ( '' === $wangan && ! $network ) {
			$wangan = trim( (string) get_option( 'cn_ga', '' ) );
		}
		if ( '' === $wangan && ! empty( $draft_2025['police_number'] ) ) {
			$wangan = trim( (string) $draft_2025['police_number'] );
		}

		$province = trim( (string) $read( 'wpicp_province', '' ) );
		$mps_info = BadgeCatalog::parse_mps( $wangan, $province );
		$icp_info = BadgeCatalog::parse_icp( $icp );

		if ( '' === $province ) {
			$province = '' !== $mps_info['province'] ? $mps_info['province'] : $icp_info['province'];
		}

		$allow_override_raw = function_exists( 'get_site_option' )
			? get_site_option( 'wpicp_allow_subsite_override', '1' )
			: $read( 'wpicp_allow_subsite_override', '1' );

		$ad_law_enabled_raw = $read( 'wpicp_ad_law_enabled', '1' );
		$ad_law_allow_raw   = $read( 'wpicp_ad_law_allowlist', array() );

		// Read `wpicp_tab_visibility` (legacy key #17) so all 17 keys are inspected without loss.
		$legacy_tabs = $read( 'wpicp_tab_visibility', array() );
		unset( $legacy_tabs );

		return SettingsStore::normalize(
			array(
				'icp_number'             => $icp_info['normalized'],
				'province_abbr'          => $province,
				'public_security_code'   => $mps_info['code'],
				'company_legal_name'     => (string) $read( 'wpicp_company', '' ),
				'report_email'           => (string) $read( 'wpicp_email', '' ),
				'report_phone'           => (string) $read( 'wpicp_phone', '' ),
				'edi_number'             => (string) $read( 'wpicp_edi', '' ),
				'app_number'             => (string) $read( 'wpicp_app', '' ),
				'miniapp_number'         => (string) $read( 'wpicp_miniapp', '' ),
				'certificates'           => $read( 'wpicp_certificates', array() ),
				'certificates_page'      => absint( $read( 'wpicp_certificates_page', 0 ) ),
				'badges'                 => $read( 'wpicp_badges', array() ),
				'selected_presets'       => $read( 'wpicp_selected_presets', array() ),
				'allow_subsite_override' => '0' !== (string) $allow_override_raw,
				'default_image_size'     => (string) $read( 'wpicp_default_image_size', 'thumbnail' ),
				'custom_css_class'       => (string) $read( 'wpicp_custom_css_class', '' ),
				'ad_law_enabled'         => '0' !== (string) $ad_law_enabled_raw,
				'ad_law_allowlist'       => $ad_law_allow_raw,
			)
		);
	}

	/**
	 * Merge freshly imported legacy values into an existing 4.0 array without wiping
	 * modern-only fields (`aigc_number`, `algorithm_number`, `custom_licenses`, etc.).
	 *
	 * @param array<string, mixed> $existing Existing 4.0 settings.
	 * @param array<string, mixed> $imported Imported legacy settings.
	 * @return array<string, mixed>
	 */
	private static function merge_preserving_modern( array $existing, array $imported ) {
		$out        = $existing;
		$legacy_map = array(
			'icp_number',
			'province_abbr',
			'public_security_code',
			'public_security_number',
			'company_legal_name',
			'report_email',
			'report_phone',
			'edi_number',
			'app_number',
			'miniapp_number',
			'certificates',
			'certificates_page',
			'badges',
			'selected_presets',
			'allow_subsite_override',
			'default_image_size',
			'custom_css_class',
		);

		foreach ( $legacy_map as $field ) {
			if ( ! array_key_exists( $field, $imported ) ) {
				continue;
			}
			$new_val = $imported[ $field ];
			if ( is_bool( $new_val ) ) {
				$out[ $field ] = $new_val;
			} elseif ( is_array( $new_val ) && ! empty( $new_val ) ) {
				$out[ $field ] = $new_val;
			} elseif ( is_scalar( $new_val ) && '' !== trim( (string) $new_val ) && 0 !== $new_val ) {
				$out[ $field ] = $new_val;
			}
		}

		return $out;
	}

	/**
	 * Detect active `wpicp-license/wpicp-license.php`, migrate all data first,
	 * then deactivate it cleanly and queue a one-time notice.
	 *
	 * @param bool $network_wide Whether triggered from network activation.
	 * @return bool True if `wpicp-license` was active and deactivated.
	 */
	public static function maybe_takeover_legacy_plugin( $network_wide = false ) {
		if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
			$plugin_inc = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $plugin_inc ) ) {
				require_once $plugin_inc;
			}
		}
		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
			return false;
		}

		$site_active = is_plugin_active( self::LEGACY_PLUGIN_FILE );
		$net_active  = function_exists( 'is_multisite' )
			&& is_multisite()
			&& function_exists( 'is_plugin_active_for_network' )
			&& is_plugin_active_for_network( self::LEGACY_PLUGIN_FILE );

		if ( ! $site_active && ! $net_active ) {
			return false;
		}

		self::maybe_migrate( true );
		deactivate_plugins( self::LEGACY_PLUGIN_FILE, true, $net_active || $network_wide );

		if ( $net_active && function_exists( 'update_site_option' ) ) {
			update_site_option( self::NOTICE_OPTION, 1 );
		} else {
			update_option( self::NOTICE_OPTION, 1, false );
		}

		return true;
	}

	/**
	 * Consume and clear the one-time legacy takeover notice flag.
	 *
	 * @return bool
	 */
	public static function consume_takeover_notice() {
		if ( function_exists( 'is_network_admin' ) && is_network_admin() && function_exists( 'get_site_option' ) ) {
			$flag = get_site_option( self::NOTICE_OPTION, 0 );
			if ( $flag ) {
				delete_site_option( self::NOTICE_OPTION );
				return true;
			}
		}
		$flag = get_option( self::NOTICE_OPTION, 0 );
		if ( $flag ) {
			delete_option( self::NOTICE_OPTION );
			return true;
		}
		return false;
	}

	/**
	 * Redirect legacy `page=wpicp_license_settings` bookmarks to `page=wpicp`.
	 *
	 * @return void
	 */
	public static function maybe_redirect_legacy_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'wpicp_license_settings' !== $page ) {
			return;
		}

		$is_net = function_exists( 'is_network_admin' ) && is_network_admin();
		$cap    = $is_net ? 'manage_network_options' : 'manage_options';
		if ( function_exists( 'current_user_can' ) && ! current_user_can( $cap ) ) {
			return;
		}

		$target = $is_net && function_exists( 'network_admin_url' )
			? network_admin_url( 'settings.php?page=wpicp' )
			: ( class_exists( 'Wenpai_Admin_Menu', false )
				? Wenpai_Admin_Menu::url( 'wpicp' )
				: admin_url( 'options-general.php?page=wpicp' ) );

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $target, 302 );
			exit;
		}
	}

	/**
	 * Detect any leftover custom tables from the abandoned 2025 draft.
	 *
	 * @return array<int, array{name:string, suffix:string, rows:int}>
	 */
	public static function detect_deprecated_tables() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->prefix ) ) {
			return array();
		}

		$found = array();
		foreach ( self::DEPRECATED_TABLE_SUFFIXES as $suffix ) {
			$table  = $wpdb->prefix . $suffix;
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
			);
			if ( $exists === $table ) {
				// Table identifier comes strictly from `$wpdb->prefix` + hardcoded constant allowlist.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
				$found[] = array(
					'name'   => $table,
					'suffix' => $suffix,
					'rows'   => $count,
				);
			}
		}

		return $found;
	}

	/**
	 * Drop deprecated 2025 draft tables after explicit administrator confirmation.
	 *
	 * @return int Number of dropped tables.
	 */
	public static function drop_deprecated_tables() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->prefix ) ) {
			return 0;
		}

		$tables  = self::detect_deprecated_tables();
		$dropped = 0;

		foreach ( $tables as $info ) {
			$suffix = $info['suffix'];
			if ( ! in_array( $suffix, self::DEPRECATED_TABLE_SUFFIXES, true ) ) {
				continue;
			}
			$safe_table = $wpdb->prefix . $suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DROP TABLE IF EXISTS `{$safe_table}`" );
			++$dropped;
		}

		delete_option( 'wpicp_options' );
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'wpicp_sensitive_words' );
		}

		return $dropped;
	}
}
