<?php
/**
 * Structured settings store, multisite inheritance, and bidirectional core sync.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Options;

use WenPai\ICP\Compliance\AccessMonitor;
use WenPai\ICP\Compliance\WpmindReviewer;
use Wenpai_Admin_Settings;
use Wenpai_Admin_UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for `wpicp_settings` and `wpicp_network_settings`.
 */
class SettingsStore {

	const OPTION_SITE    = 'wpicp_settings';
	const OPTION_NETWORK = 'wpicp_network_settings';
	const SCHEMA_VERSION = 1;

	/**
	 * Re-entrancy lock preventing infinite recursion across bidirectional option hooks.
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * Whether bidirectional hooks have been attached.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Register option synchronization and multisite option bridge hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( self::$hooked || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$hooked = true;

		add_action( 'add_option_' . self::OPTION_SITE, array( __CLASS__, 'on_site_option_added' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION_SITE, array( __CLASS__, 'on_site_option_updated' ), 10, 2 );

		add_action( 'add_site_option_' . self::OPTION_NETWORK, array( __CLASS__, 'on_network_option_saved' ), 10, 2 );
		add_action( 'update_site_option_' . self::OPTION_NETWORK, array( __CLASS__, 'on_network_option_updated' ), 10, 3 );

		add_action( 'add_option_zh_cn_l10n_icp_num', array( __CLASS__, 'on_core_icp_added' ), 10, 2 );
		add_action( 'update_option_zh_cn_l10n_icp_num', array( __CLASS__, 'on_core_icp_updated' ), 10, 2 );

		add_action( 'add_option_cn_icp', array( __CLASS__, 'on_core_icp_added' ), 10, 2 );
		add_action( 'update_option_cn_icp', array( __CLASS__, 'on_core_icp_updated' ), 10, 2 );

		add_action( 'add_option_cn_ga', array( __CLASS__, 'on_core_mps_added' ), 10, 2 );
		add_action( 'update_option_cn_ga', array( __CLASS__, 'on_core_mps_updated' ), 10, 2 );

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			add_filter( 'pre_option_' . self::OPTION_NETWORK, array( __CLASS__, 'bridge_get_network_option' ) );
			add_filter( 'pre_update_option_' . self::OPTION_NETWORK, array( __CLASS__, 'bridge_update_network_option' ), 10, 2 );
		}
	}

	/**
	 * Default structured settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'icp_number'             => '',
			'icp_site_name'          => '',
			'province_abbr'          => '',
			'public_security_code'   => '',
			'public_security_number' => '',
			'company_legal_name'     => '',
			'edi_number'             => '',
			'aigc_number'            => '',
			'algorithm_number'       => '',
			'app_number'             => '',
			'miniapp_number'         => '',
			'report_email'           => '',
			'report_phone'           => '',
			'custom_licenses'        => array(),
			'selected_presets'       => array(),
			'certificates'           => array(),
			'certificate_click_mode' => 'lightbox',
			'certificates_page'      => '',
			'cert_watermark_enabled' => true,
			'cert_watermark_text'    => '仅供 {site_name} 官网亮照公示使用，他用无效',
			'cert_watermark_layout'  => 'tile',
			'cert_watermark_tone'    => 'standard',
			'cert_watermark_engine'  => 'server',
			'cert_anti_save'         => true,
			'badges'                 => array(),
			'auto_footer'            => false,
			'footer_style'           => 'standard',
			'footer_align'           => 'center',
			'footer_separator'       => '|',
			'footer_items'           => array(
				'company',
				'icp',
				'mps',
				'edi',
				'aigc',
				'algorithm',
				'report',
				'policy',
				'certificates',
			),
			'default_image_size'     => 'thumbnail',
			'custom_css_class'       => '',
			'allow_subsite_override' => true,
			'clean_on_uninstall'     => false,
			'access_provider'        => 'aliyun',
			'home_check_enabled'     => true,
			'keepalive_enabled'      => false,
			'keepalive_url'          => '',
			'expected_provider_ips'  => '',
			'keepalive_interval'     => '3d',
			'keepalive_keyword'      => '',
			'alert_email'            => '',
			'ad_law_enabled'         => true,
			'ad_law_remote_sync'     => true,
			'ad_law_sync_interval'   => 'weekly',
			'ad_law_rule_groups'     => array( 'absolute', 'promise', 'authority' ),
			'ad_law_editor_panel'    => true,
			'ad_law_scan_post_types' => array( 'post', 'page' ),
			'ad_law_allowlist'       => array(),
			'ai_engine'              => 'offline',
			'ai_review_enabled'      => false,
			'module_badges'          => false,
			'module_access'          => false,
			'module_adlaw'           => false,
		);
	}

	/**
	 * List of optional feature module setting keys.
	 *
	 * @return string[]
	 */
	public static function optional_modules() {
		return array( 'module_badges', 'module_access', 'module_adlaw' );
	}

	/**
	 * Whether a given optional feature module is currently enabled.
	 *
	 * @param string                    $module Module key (`module_badges`, `module_access`, `module_adlaw`).
	 * @param array<string, mixed>|null $cfg    Optional settings snapshot.
	 * @return bool
	 */
	public static function module_enabled( $module, ?array $cfg = null ) {
		if ( ! in_array( (string) $module, self::optional_modules(), true ) ) {
			return false;
		}
		$settings = is_array( $cfg ) ? $cfg : self::effective();
		return ! empty( $settings[ $module ] ) && '0' !== (string) $settings[ $module ];
	}

	/**
	 * Normalize raw settings array, computing `public_security_number` and cleaning fields.
	 *
	 * @param array<string, mixed> $raw Raw settings.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $raw ) {
		$merged = wp_parse_args( $raw, self::defaults() );

		$icp_info             = BadgeCatalog::parse_icp( (string) $merged['icp_number'] );
		$merged['icp_number'] = $icp_info['normalized'];
		$province_hint        = trim( (string) $merged['province_abbr'] );
		$mps_source           = '' !== trim( (string) $merged['public_security_code'] )
			? (string) $merged['public_security_code']
			: (string) $merged['public_security_number'];
		$mps_info             = BadgeCatalog::parse_mps( $mps_source, $province_hint );
		$resolved_province    = '' !== $mps_info['province']
			? $mps_info['province']
			: ( '' !== $province_hint ? $province_hint : $icp_info['province'] );

		if ( '' !== $resolved_province && ! array_key_exists( $resolved_province, BadgeCatalog::provinces() ) ) {
			$resolved_province = '';
		}

		$merged['province_abbr']          = $resolved_province;
		$merged['public_security_code']   = $mps_info['code'];
		$merged['public_security_number'] = '' !== $mps_info['code']
			? ( '' !== $resolved_province ? $resolved_province : '' ) . '公网安备' . $mps_info['code'] . '号'
			: '';

		$merged['icp_site_name']      = sanitize_text_field( (string) $merged['icp_site_name'] );
		$merged['company_legal_name'] = sanitize_text_field( (string) $merged['company_legal_name'] );
		$merged['edi_number']         = sanitize_text_field( (string) $merged['edi_number'] );
		$merged['aigc_number']        = sanitize_text_field( (string) $merged['aigc_number'] );
		$merged['algorithm_number']   = sanitize_text_field( (string) $merged['algorithm_number'] );
		$merged['app_number']         = sanitize_text_field( (string) $merged['app_number'] );
		$merged['miniapp_number']     = sanitize_text_field( (string) $merged['miniapp_number'] );
		$merged['report_email']       = function_exists( 'sanitize_email' )
			? sanitize_email( (string) $merged['report_email'] )
			: sanitize_text_field( (string) $merged['report_email'] );
		$merged['report_phone']       = sanitize_text_field( (string) $merged['report_phone'] );

		$custom_licenses = array();
		if ( is_array( $merged['custom_licenses'] ) ) {
			foreach ( $merged['custom_licenses'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$label  = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
				$number = isset( $item['number'] ) ? sanitize_text_field( (string) $item['number'] ) : '';
				$url    = isset( $item['url'] ) && '' !== trim( (string) $item['url'] )
					? esc_url_raw( BadgeCatalog::enforce_https( (string) $item['url'] ) )
					: '';
				if ( '' === $label && '' === $number ) {
					continue;
				}
				$custom_licenses[] = array(
					'label'  => $label,
					'number' => $number,
					'url'    => $url,
				);
			}
		}
		$merged['custom_licenses'] = $custom_licenses;

		$known_presets    = BadgeCatalog::presets( true );
		$selected_presets = array();
		if ( is_array( $merged['selected_presets'] ) ) {
			foreach ( $merged['selected_presets'] as $preset_key ) {
				if ( ! is_scalar( $preset_key ) ) {
					continue;
				}
				$clean_key = sanitize_key( (string) $preset_key );
				if ( isset( $known_presets[ $clean_key ] ) && ! in_array( $clean_key, $selected_presets, true ) ) {
					$selected_presets[] = $clean_key;
				}
			}
		}
		$merged['selected_presets'] = $selected_presets;

		$certificates = array();
		if ( is_array( $merged['certificates'] ) ) {
			foreach ( $merged['certificates'] as $cert ) {
				if ( ! is_array( $cert ) ) {
					continue;
				}
				$img_id  = isset( $cert['image_id'] ) ? absint( $cert['image_id'] ) : 0;
				$title   = isset( $cert['title'] ) ? sanitize_text_field( (string) $cert['title'] ) : '';
				$type    = isset( $cert['type'] ) ? sanitize_text_field( (string) $cert['type'] ) : '';
				$number  = isset( $cert['number'] ) ? sanitize_text_field( (string) $cert['number'] ) : '';
				$expires = isset( $cert['expires_at'] ) ? sanitize_text_field( (string) $cert['expires_at'] ) : '';
				$visible = ! array_key_exists( 'visible', $cert ) || ( ! empty( $cert['visible'] ) && '0' !== (string) $cert['visible'] );
				if ( 0 === $img_id && '' === $title ) {
					continue;
				}
				$certificates[] = array(
					'image_id'   => $img_id,
					'title'      => $title,
					'type'       => '' !== $type ? $type : __( '营业执照', 'wpicp' ),
					'number'     => $number,
					'expires_at' => $expires,
					'visible'    => $visible,
				);
			}
		}
		$merged['certificates'] = $certificates;

		$merged['certificate_click_mode'] = in_array( (string) $merged['certificate_click_mode'], array( 'lightbox', 'page' ), true )
			? (string) $merged['certificate_click_mode']
			: 'lightbox';
		$page_id                          = absint( $merged['certificates_page'] );
		$merged['certificates_page']      = $page_id > 0 ? $page_id : '';
		$merged['cert_watermark_enabled'] = ! empty( $merged['cert_watermark_enabled'] ) && '0' !== (string) $merged['cert_watermark_enabled'];
		$merged['cert_watermark_text']    = sanitize_text_field( (string) $merged['cert_watermark_text'] );
		$merged['cert_watermark_layout']  = in_array( (string) $merged['cert_watermark_layout'], array( 'tile', 'center', 'corner' ), true )
			? (string) $merged['cert_watermark_layout']
			: 'tile';
		$merged['cert_watermark_tone']    = in_array( (string) $merged['cert_watermark_tone'], array( 'light', 'standard', 'strong', 'red' ), true )
			? (string) $merged['cert_watermark_tone']
			: 'standard';
		$merged['cert_watermark_engine']  = in_array( (string) $merged['cert_watermark_engine'], array( 'server', 'css' ), true )
			? (string) $merged['cert_watermark_engine']
			: 'server';
		$merged['cert_anti_save']         = ! empty( $merged['cert_anti_save'] ) && '0' !== (string) $merged['cert_anti_save'];

		$badges = array();
		if ( is_array( $merged['badges'] ) ) {
			foreach ( $merged['badges'] as $badge ) {
				if ( ! is_array( $badge ) ) {
					continue;
				}
				$img_id = isset( $badge['image_id'] ) ? absint( $badge['image_id'] ) : 0;
				$title  = isset( $badge['title'] ) ? sanitize_text_field( (string) $badge['title'] ) : '';
				$url    = isset( $badge['url'] ) && '' !== trim( (string) $badge['url'] )
					? esc_url_raw( BadgeCatalog::enforce_https( (string) $badge['url'] ) )
					: '';
				if ( 0 === $img_id && '' === $url && '' === $title ) {
					continue;
				}
				$badges[] = array(
					'image_id' => $img_id,
					'title'    => $title,
					'url'      => $url,
				);
			}
		}
		$merged['badges'] = $badges;

		$merged['auto_footer']        = ! empty( $merged['auto_footer'] ) && '0' !== (string) $merged['auto_footer'];
		$merged['footer_style']       = in_array( (string) $merged['footer_style'], array( 'minimal', 'standard', 'badges' ), true )
			? (string) $merged['footer_style']
			: 'standard';
		$merged['footer_align']       = in_array( (string) $merged['footer_align'], array( 'center', 'left', 'between' ), true )
			? (string) $merged['footer_align']
			: 'center';
		$merged['footer_separator']   = in_array( (string) $merged['footer_separator'], array( '|', '·', 'space' ), true )
			? (string) $merged['footer_separator']
			: '|';
		$merged['default_image_size'] = 'full' === (string) $merged['default_image_size'] ? 'full' : 'thumbnail';

		$classes = array();
		foreach ( preg_split( '/\s+/', trim( (string) $merged['custom_css_class'] ) ) as $cls ) {
			$clean_cls = sanitize_html_class( (string) $cls );
			if ( '' !== $clean_cls ) {
				$classes[] = $clean_cls;
			}
		}
		$merged['custom_css_class'] = implode( ' ', $classes );

		$allowed_footer_items = array(
			'company',
			'icp',
			'mps',
			'edi',
			'aigc',
			'algorithm',
			'app',
			'miniapp',
			'custom_licenses',
			'report',
			'policy',
			'certificates',
		);
		$footer_items         = array();
		if ( is_array( $merged['footer_items'] ) ) {
			foreach ( $merged['footer_items'] as $item_key ) {
				$clean_item = sanitize_key( (string) $item_key );
				if ( in_array( $clean_item, $allowed_footer_items, true ) && ! in_array( $clean_item, $footer_items, true ) ) {
					$footer_items[] = $clean_item;
				}
			}
		}
		$merged['footer_items'] = $footer_items;

		$merged['allow_subsite_override'] = ! empty( $merged['allow_subsite_override'] ) && '0' !== (string) $merged['allow_subsite_override'];
		$merged['clean_on_uninstall']     = ! empty( $merged['clean_on_uninstall'] ) && '0' !== (string) $merged['clean_on_uninstall'];
		$merged['access_provider']        = in_array( (string) $merged['access_provider'], array( 'aliyun', 'tencent', 'huawei', 'baidu', 'volc', 'other' ), true )
			? (string) $merged['access_provider']
			: 'aliyun';
		$merged['home_check_enabled']     = ! empty( $merged['home_check_enabled'] ) && '0' !== (string) $merged['home_check_enabled'];
		$merged['keepalive_enabled']      = ! empty( $merged['keepalive_enabled'] ) && '0' !== (string) $merged['keepalive_enabled'];
		$merged['keepalive_url']          = '' !== trim( (string) $merged['keepalive_url'] )
			? esc_url_raw( BadgeCatalog::enforce_https( (string) $merged['keepalive_url'] ) )
			: '';
		$merged['expected_provider_ips']  = sanitize_text_field( (string) $merged['expected_provider_ips'] );
		$merged['keepalive_interval']     = in_array( (string) $merged['keepalive_interval'], array( '1d', '3d', '7d', '14d' ), true )
			? (string) $merged['keepalive_interval']
			: '3d';
		$merged['keepalive_keyword']      = sanitize_text_field( (string) $merged['keepalive_keyword'] );
		$merged['alert_email']            = function_exists( 'sanitize_email' )
			? sanitize_email( (string) $merged['alert_email'] )
			: sanitize_text_field( (string) $merged['alert_email'] );
		$merged['ad_law_enabled']         = ! empty( $merged['ad_law_enabled'] ) && '0' !== (string) $merged['ad_law_enabled'];
		$merged['ad_law_remote_sync']     = ! empty( $merged['ad_law_remote_sync'] ) && '0' !== (string) $merged['ad_law_remote_sync'];
		$merged['ad_law_sync_interval']   = in_array( (string) $merged['ad_law_sync_interval'], array( 'daily', 'weekly', 'manual' ), true )
			? (string) $merged['ad_law_sync_interval']
			: 'weekly';
		$allowed_groups                   = array( 'absolute', 'promise', 'authority', 'regulated' );
		$rule_groups                      = array();
		if ( is_array( $merged['ad_law_rule_groups'] ) ) {
			foreach ( $merged['ad_law_rule_groups'] as $grp ) {
				$clean_grp = sanitize_key( (string) $grp );
				if ( in_array( $clean_grp, $allowed_groups, true ) && ! in_array( $clean_grp, $rule_groups, true ) ) {
					$rule_groups[] = $clean_grp;
				}
			}
		}
		$merged['ad_law_rule_groups']  = $rule_groups;
		$merged['ad_law_editor_panel'] = ! empty( $merged['ad_law_editor_panel'] ) && '0' !== (string) $merged['ad_law_editor_panel'];

		$post_types = array();
		if ( is_array( $merged['ad_law_scan_post_types'] ) ) {
			foreach ( $merged['ad_law_scan_post_types'] as $pt ) {
				$clean_pt = sanitize_key( (string) $pt );
				if ( '' !== $clean_pt && ! in_array( $clean_pt, $post_types, true ) ) {
					$post_types[] = $clean_pt;
				}
			}
		}
		$merged['ad_law_scan_post_types'] = $post_types;

		$allowlist     = array();
		$raw_allowlist = $merged['ad_law_allowlist'];
		if ( is_string( $raw_allowlist ) ) {
			$raw_allowlist = preg_split( '/[\r\n,，]+/u', $raw_allowlist );
		}
		if ( is_array( $raw_allowlist ) ) {
			foreach ( $raw_allowlist as $term ) {
				if ( is_scalar( $term ) ) {
					$clean_term = sanitize_text_field( trim( (string) $term ) );
					if ( '' !== $clean_term && ! in_array( $clean_term, $allowlist, true ) ) {
						$allowlist[] = $clean_term;
					}
				}
			}
		}
		$merged['ad_law_allowlist'] = $allowlist;

		if ( array_key_exists( 'ai_engine', $raw ) ) {
			$ai_engine = 'wpmind' === (string) $raw['ai_engine'] ? 'wpmind' : 'offline';
		} elseif ( array_key_exists( 'ai_review_enabled', $raw ) ) {
			$ai_engine = ( ! empty( $raw['ai_review_enabled'] ) && '0' !== (string) $raw['ai_review_enabled'] ) ? 'wpmind' : 'offline';
		} else {
			$ai_engine = 'wpmind' === (string) $merged['ai_engine'] ? 'wpmind' : 'offline';
		}
		$merged['ai_engine']         = $ai_engine;
		$merged['ai_review_enabled'] = 'wpmind' === $ai_engine;

		if ( array_key_exists( 'module_badges', $raw ) ) {
			$merged['module_badges'] = ! empty( $raw['module_badges'] ) && '0' !== (string) $raw['module_badges'];
		} else {
			$merged['module_badges'] = ! empty( $merged['selected_presets'] )
				|| ! empty( $merged['badges'] )
				|| ! empty( $merged['certificates'] );
		}

		if ( array_key_exists( 'module_access', $raw ) ) {
			$merged['module_access'] = ! empty( $raw['module_access'] ) && '0' !== (string) $raw['module_access'];
		} else {
			$merged['module_access'] = ! empty( $merged['keepalive_enabled'] )
				|| '' !== trim( (string) $merged['keepalive_url'] );
		}

		if ( array_key_exists( 'module_adlaw', $raw ) ) {
			$merged['module_adlaw'] = ! empty( $raw['module_adlaw'] ) && '0' !== (string) $raw['module_adlaw'];
		} else {
			$merged['module_adlaw'] = ! empty( $merged['ad_law_allowlist'] );
		}

		return $merged;
	}

	/**
	 * Whether subsites are permitted to override network defaults on Multisite.
	 *
	 * @return bool
	 */
	public static function allows_subsite_override() {
		if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
			return true;
		}
		$net = function_exists( 'get_site_option' ) ? get_site_option( self::OPTION_NETWORK, null ) : null;
		if ( is_array( $net ) && array_key_exists( 'allow_subsite_override', $net ) ) {
			return ! empty( $net['allow_subsite_override'] ) && '0' !== (string) $net['allow_subsite_override'];
		}
		$legacy = function_exists( 'get_site_option' ) ? get_site_option( 'wpicp_allow_subsite_override', '1' ) : '1';
		return '0' !== (string) $legacy;
	}

	/**
	 * Return effective settings for the current blog/site context.
	 *
	 * @return array<string, mixed>
	 */
	public static function effective() {
		$is_ms = function_exists( 'is_multisite' ) && is_multisite();
		if ( $is_ms ) {
			$net_raw  = function_exists( 'get_site_option' ) ? get_site_option( self::OPTION_NETWORK, null ) : null;
			$net_norm = is_array( $net_raw ) ? self::normalize( $net_raw ) : self::fallback_from_legacy( true );

			if ( ! self::allows_subsite_override() ) {
				return $net_norm;
			}

			$site_raw = get_option( self::OPTION_SITE, null );
			if ( ! is_array( $site_raw ) ) {
				return $net_norm;
			}
			return self::normalize( array_merge( $net_norm, $site_raw ) );
		}

		$site_raw = get_option( self::OPTION_SITE, null );
		if ( ! is_array( $site_raw ) ) {
			return self::fallback_from_legacy( false );
		}
		return self::normalize( $site_raw );
	}

	/**
	 * Read fallback values from WordPress Core (`zh_cn_l10n_icp_num`, `cn_icp`, `cn_ga`)
	 * and legacy `wpicp_*` keys when `wpicp_settings` has not been saved yet.
	 *
	 * @param bool $network Whether reading network-level options.
	 * @return array<string, mixed>
	 */
	public static function fallback_from_legacy( $network = false ) {
		$getter = static function ( $key, $fallback = '' ) use ( $network ) {
			if ( $network && function_exists( 'get_site_option' ) ) {
				return get_site_option( $key, $fallback );
			}
			return get_option( $key, $fallback );
		};

		$icp = trim( (string) $getter( 'wpicp_license', '' ) );
		if ( '' === $icp && ! $network ) {
			$icp = trim( (string) get_option( 'zh_cn_l10n_icp_num', '' ) );
		}
		if ( '' === $icp && ! $network ) {
			$icp = trim( (string) get_option( 'cn_icp', '' ) );
		}

		$wangan = trim( (string) $getter( 'wpicp_wangan', '' ) );
		if ( '' === $wangan && ! $network ) {
			$wangan = trim( (string) get_option( 'cn_ga', '' ) );
		}

		return self::normalize(
			array(
				'icp_number'             => $icp,
				'province_abbr'          => (string) $getter( 'wpicp_province', '' ),
				'public_security_code'   => $wangan,
				'company_legal_name'     => (string) $getter( 'wpicp_company', '' ),
				'report_email'           => (string) $getter( 'wpicp_email', '' ),
				'report_phone'           => (string) $getter( 'wpicp_phone', '' ),
				'edi_number'             => (string) $getter( 'wpicp_edi', '' ),
				'app_number'             => (string) $getter( 'wpicp_app', '' ),
				'miniapp_number'         => (string) $getter( 'wpicp_miniapp', '' ),
				'certificates'           => $getter( 'wpicp_certificates', array() ),
				'certificates_page'      => $getter( 'wpicp_certificates_page', 0 ),
				'badges'                 => $getter( 'wpicp_badges', array() ),
				'selected_presets'       => $getter( 'wpicp_selected_presets', array() ),
				'allow_subsite_override' => '0' !== (string) ( function_exists( 'get_site_option' ) ? get_site_option( 'wpicp_allow_subsite_override', '1' ) : '1' ),
				'default_image_size'     => (string) $getter( 'wpicp_default_image_size', 'thumbnail' ),
				'custom_css_class'       => (string) $getter( 'wpicp_custom_css_class', '' ),
			)
		);
	}

	/**
	 * Persist site-level settings and mirror to WordPress Core & legacy option keys.
	 *
	 * @param array<string, mixed> $values Partial or complete settings map.
	 * @return array<string, mixed> Normalized stored settings.
	 */
	public static function save_site( array $values ) {
		if ( array_key_exists( 'ai_review_enabled', $values ) && ! array_key_exists( 'ai_engine', $values ) ) {
			$values['ai_engine'] = ( ! empty( $values['ai_review_enabled'] ) && '0' !== (string) $values['ai_review_enabled'] ) ? 'wpmind' : 'offline';
		} elseif ( array_key_exists( 'ai_engine', $values ) && ! array_key_exists( 'ai_review_enabled', $values ) ) {
			$values['ai_review_enabled'] = 'wpmind' === (string) $values['ai_engine'];
		}
		$current = get_option( self::OPTION_SITE, array() );
		$merged  = is_array( $current ) ? array_merge( $current, $values ) : $values;
		$clean   = self::normalize( $merged );
		update_option( self::OPTION_SITE, $clean );
		self::mirror_to_core_and_legacy( $clean, false );
		return $clean;
	}

	/**
	 * Persist network-level settings and mirror to legacy network option keys.
	 *
	 * @param array<string, mixed> $values Partial or complete settings map.
	 * @return array<string, mixed> Normalized stored settings.
	 */
	public static function save_network( array $values ) {
		if ( array_key_exists( 'ai_review_enabled', $values ) && ! array_key_exists( 'ai_engine', $values ) ) {
			$values['ai_engine'] = ( ! empty( $values['ai_review_enabled'] ) && '0' !== (string) $values['ai_review_enabled'] ) ? 'wpmind' : 'offline';
		} elseif ( array_key_exists( 'ai_engine', $values ) && ! array_key_exists( 'ai_review_enabled', $values ) ) {
			$values['ai_review_enabled'] = 'wpmind' === (string) $values['ai_engine'];
		}
		$current = function_exists( 'get_site_option' ) ? get_site_option( self::OPTION_NETWORK, array() ) : array();
		$merged  = is_array( $current ) ? array_merge( $current, $values ) : $values;
		$clean   = self::normalize( $merged );
		if ( function_exists( 'update_site_option' ) ) {
			update_site_option( self::OPTION_NETWORK, $clean );
		}
		self::mirror_to_core_and_legacy( $clean, true );
		return $clean;
	}

	/**
	 * Callback when `wpicp_settings` is added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Stored value.
	 * @return void
	 */
	public static function on_site_option_added( $option, $value ) {
		unset( $option );
		self::handle_site_saved( $value );
	}

	/**
	 * Callback when `wpicp_settings` is updated.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public static function on_site_option_updated( $old_value, $value ) {
		unset( $old_value );
		self::handle_site_saved( $value );
	}

	/**
	 * Normalize computed fields in `wpicp_settings` and mirror to core/legacy keys.
	 *
	 * @param mixed $value Newly saved option array.
	 * @return void
	 */
	private static function handle_site_saved( $value ) {
		if ( self::$syncing || ! is_array( $value ) ) {
			return;
		}
		self::$syncing = true;
		try {
			$schema_ver = isset( $value['_schema'] ) ? $value['_schema'] : null;
			$clean      = self::normalize( $value );
			if ( null !== $schema_ver ) {
				$clean['_schema'] = (int) $schema_ver;
			}
			if ( $clean !== $value ) {
				update_option( self::OPTION_SITE, $clean );
			}
			self::mirror_to_core_and_legacy( $clean, false );
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Callback when `wpicp_network_settings` is added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return void
	 */
	public static function on_network_option_saved( $option, $value ) {
		unset( $option );
		if ( self::$syncing || ! is_array( $value ) ) {
			return;
		}
		self::$syncing = true;
		try {
			$clean = self::normalize( $value );
			if ( $clean !== $value && function_exists( 'update_site_option' ) ) {
				update_site_option( self::OPTION_NETWORK, $clean );
			}
			self::mirror_to_core_and_legacy( $clean, true );
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Callback when `wpicp_network_settings` is updated.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $value     New value.
	 * @param mixed  $old_value Old value.
	 * @return void
	 */
	public static function on_network_option_updated( $option, $value, $old_value ) {
		unset( $old_value );
		self::on_network_option_saved( $option, $value );
	}

	/**
	 * Callback when `zh_cn_l10n_icp_num` or `cn_icp` is added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public static function on_core_icp_added( $option, $value ) {
		unset( $option );
		self::sync_from_core_icp( $value );
	}

	/**
	 * Callback when `zh_cn_l10n_icp_num` or `cn_icp` is updated.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public static function on_core_icp_updated( $old_value, $value ) {
		unset( $old_value );
		self::sync_from_core_icp( $value );
	}

	/**
	 * Sync an external change in `zh_cn_l10n_icp_num` / `cn_icp` into `wpicp_settings`.
	 *
	 * @param mixed $value New ICP string.
	 * @return void
	 */
	private static function sync_from_core_icp( $value ) {
		if ( self::$syncing || ! is_scalar( $value ) ) {
			return;
		}
		self::$syncing = true;
		try {
			$parsed             = BadgeCatalog::parse_icp( (string) $value );
			$icp                = $parsed['normalized'];
			$current            = get_option( self::OPTION_SITE, null );
			$base               = is_array( $current ) ? $current : self::fallback_from_legacy( false );
			$base['icp_number'] = $icp;
			if ( empty( $base['province_abbr'] ) && '' !== $parsed['province'] ) {
				$base['province_abbr'] = $parsed['province'];
			}
			$clean = self::normalize( $base );
			update_option( self::OPTION_SITE, $clean );
			update_option( 'zh_cn_l10n_icp_num', $icp );
			update_option( 'cn_icp', $icp );
			update_option( 'wpicp_license', $icp );
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Callback when `cn_ga` is added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return void
	 */
	public static function on_core_mps_added( $option, $value ) {
		unset( $option );
		self::sync_from_core_mps( $value );
	}

	/**
	 * Callback when `cn_ga` is updated.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public static function on_core_mps_updated( $old_value, $value ) {
		unset( $old_value );
		self::sync_from_core_mps( $value );
	}

	/**
	 * Sync an external change in `cn_ga` into `wpicp_settings`.
	 *
	 * @param mixed $value New MPS string or code.
	 * @return void
	 */
	private static function sync_from_core_mps( $value ) {
		if ( self::$syncing || ! is_scalar( $value ) ) {
			return;
		}
		self::$syncing = true;
		try {
			$current  = get_option( self::OPTION_SITE, null );
			$base     = is_array( $current ) ? $current : self::fallback_from_legacy( false );
			$province = isset( $base['province_abbr'] ) ? (string) $base['province_abbr'] : '';
			$parsed   = BadgeCatalog::parse_mps( (string) $value, $province );

			$base['public_security_code'] = $parsed['code'];
			if ( '' !== $parsed['province'] ) {
				$base['province_abbr'] = $parsed['province'];
			}
			$clean = self::normalize( $base );
			update_option( self::OPTION_SITE, $clean );
			update_option( 'cn_ga', $clean['public_security_number'] );
			update_option( 'wpicp_wangan', $clean['public_security_code'] );
			if ( '' !== $clean['province_abbr'] ) {
				update_option( 'wpicp_province', $clean['province_abbr'] );
			}
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Mirror normalized settings to WordPress Core (`zh_cn_l10n_icp_num`, `cn_icp`, `cn_ga`)
	 * and legacy `wpicp_*` keys so legacy themes and suite plugins stay consistent.
	 *
	 * @param array<string, mixed> $clean   Normalized settings.
	 * @param bool                 $network Whether writing network-level mirrors.
	 * @return void
	 */
	public static function mirror_to_core_and_legacy( array $clean, $network = false ) {
		$was_syncing   = self::$syncing;
		self::$syncing = true;
		try {
			if ( $network && function_exists( 'update_site_option' ) ) {
				update_site_option( 'wpicp_license', (string) $clean['icp_number'] );
				update_site_option( 'wpicp_wangan', (string) $clean['public_security_code'] );
				update_site_option( 'wpicp_province', (string) $clean['province_abbr'] );
				update_site_option( 'wpicp_company', (string) $clean['company_legal_name'] );
				update_site_option( 'wpicp_email', (string) $clean['report_email'] );
				update_site_option( 'wpicp_phone', (string) $clean['report_phone'] );
				update_site_option( 'wpicp_edi', (string) $clean['edi_number'] );
				update_site_option( 'wpicp_app', (string) $clean['app_number'] );
				update_site_option( 'wpicp_miniapp', (string) $clean['miniapp_number'] );
				update_site_option( 'wpicp_allow_subsite_override', ! empty( $clean['allow_subsite_override'] ) ? '1' : '0' );
				update_site_option( 'wpicp_default_image_size', (string) $clean['default_image_size'] );
				update_site_option( 'wpicp_custom_css_class', (string) $clean['custom_css_class'] );
				update_site_option( 'wpicp_selected_presets', (array) $clean['selected_presets'] );
				return;
			}

			update_option( 'zh_cn_l10n_icp_num', (string) $clean['icp_number'] );
			update_option( 'cn_icp', (string) $clean['icp_number'] );
			update_option( 'cn_ga', (string) $clean['public_security_number'] );

			update_option( 'wpicp_license', (string) $clean['icp_number'] );
			update_option( 'wpicp_wangan', (string) $clean['public_security_code'] );
			update_option( 'wpicp_province', (string) $clean['province_abbr'] );
			update_option( 'wpicp_company', (string) $clean['company_legal_name'] );
			update_option( 'wpicp_email', (string) $clean['report_email'] );
			update_option( 'wpicp_phone', (string) $clean['report_phone'] );
			update_option( 'wpicp_edi', (string) $clean['edi_number'] );
			update_option( 'wpicp_app', (string) $clean['app_number'] );
			update_option( 'wpicp_miniapp', (string) $clean['miniapp_number'] );
			update_option( 'wpicp_default_image_size', (string) $clean['default_image_size'] );
			update_option( 'wpicp_custom_css_class', (string) $clean['custom_css_class'] );
			update_option( 'wpicp_selected_presets', (array) $clean['selected_presets'] );
			update_option( 'wpicp_certificates', (array) $clean['certificates'] );
			update_option( 'wpicp_certificates_page', absint( $clean['certificates_page'] ) );
			update_option( 'wpicp_badges', (array) $clean['badges'] );
		} finally {
			self::$syncing = $was_syncing;
		}
	}

	/**
	 * Bridge `get_option( 'wpicp_network_settings' )` to `get_site_option()` on Multisite
	 * so `Wenpai_Admin_Settings` and `Wenpai_Admin_Tools` operate on network storage.
	 *
	 * @return mixed
	 */
	public static function bridge_get_network_option() {
		$val = get_site_option( self::OPTION_NETWORK, array() );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * Bridge `update_option( 'wpicp_network_settings', $value )` to `update_site_option()`.
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Previous value.
	 * @return mixed Returns `$old_value` so blog-1 `wp_options` is not written.
	 */
	public static function bridge_update_network_option( $value, $old_value ) {
		if ( is_array( $value ) ) {
			update_site_option( self::OPTION_NETWORK, $value );
		}
		return $old_value;
	}

	/**
	 * Build and register the declarative `Wenpai_Admin_Settings` schema instance.
	 *
	 * @param string $option_name Option key (`wpicp_settings` or `wpicp_network_settings`).
	 * @return Wenpai_Admin_Settings
	 */
	public static function register_framework( $option_name = self::OPTION_SITE ) {
		$existing = Wenpai_Admin_Settings::get_instance( $option_name );
		if ( $existing instanceof Wenpai_Admin_Settings ) {
			return $existing;
		}

		$is_network_opt = self::OPTION_NETWORK === $option_name;
		$defaults       = self::defaults();

		$preset_choices = array();
		foreach ( BadgeCatalog::presets( true ) as $key => $preset ) {
			$img_url                = BadgeCatalog::image_url( $preset['file'] );
			$preset_choices[ $key ] = array(
				'label'      => $preset['title'],
				'meta'       => $preset['group'],
				'media_html' => '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $preset['title'] ) . '" width="88" height="32">',
			);
		}

		$post_types_cb = static function () {
			if ( function_exists( 'get_post_types' ) ) {
				$objs = get_post_types( array( 'public' => true ), 'objects' );
				$out  = array();
				foreach ( (array) $objs as $slug => $obj ) {
					if ( 'attachment' === $slug ) {
						continue;
					}
					$label        = is_object( $obj ) && isset( $obj->labels->singular_name )
						? (string) $obj->labels->singular_name
						: (string) $slug;
					$out[ $slug ] = $label;
				}
				if ( array() !== $out ) {
					return $out;
				}
			}
			return array(
				'post' => __( '文章', 'wpicp' ),
				'page' => __( '页面', 'wpicp' ),
			);
		};

		$fields = array(
			'icp_number'             => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['icp_number'],
				'label'       => __( '工信部 ICP 备案号', 'wpicp' ),
				'placeholder' => '粤ICP备2023091240号-1',
				'desc'        => __( '自动识别主体号与网站序号，跳转工信部公示页。', 'wpicp' ),
				'sanitize'    => static function ( $v ) {
					$parsed = BadgeCatalog::parse_icp( is_scalar( $v ) ? (string) $v : '' );
					return $parsed['normalized'];
				},
			),
			'icp_site_name'          => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['icp_site_name'],
				'label'       => __( '工信部登记网站名称', 'wpicp' ),
				'placeholder' => __( '文派示例站', 'wpicp' ),
				'desc'        => __( '用于首页合规自检核对网页标题是否与备案名称一致。', 'wpicp' ),
			),
			'province_abbr'          => array(
				'type'    => 'select',
				'tab'     => 'filing',
				'default' => $defaults['province_abbr'],
				'label'   => __( '公安联网备案省份', 'wpicp' ),
				'desc'    => __( '选择备案属地简称，留空时按备案号自动识别。', 'wpicp' ),
				'choices' => BadgeCatalog::province_choices(),
			),
			'public_security_code'   => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['public_security_code'],
				'label'       => __( '公安机关联网备案号', 'wpicp' ),
				'placeholder' => '44010602000000',
				'desc'        => __( '支持填纯数字或完整网安备号，自动提取编码。', 'wpicp' ),
				'sanitize'    => static function ( $v ) {
					$parsed = BadgeCatalog::parse_mps( is_scalar( $v ) ? (string) $v : '' );
					return $parsed['code'];
				},
			),
			'company_legal_name'     => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['company_legal_name'],
				'label'       => __( '主办单位法定全称', 'wpicp' ),
				'placeholder' => __( '示例科技有限公司', 'wpicp' ),
				'desc'        => __( '与营业执照及工信部备案主体名称保持一致。', 'wpicp' ),
			),
			'edi_number'             => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['edi_number'],
				'label'       => __( '增值电信业务经营许可证', 'wpicp' ),
				'placeholder' => '粤B2-20240001',
				'desc'        => __( '经营性站点填写的 ICP 证或 EDI 许可证编号。', 'wpicp' ),
			),
			'aigc_number'            => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['aigc_number'],
				'label'       => __( '生成式人工智能服务备案号', 'wpicp' ),
				'placeholder' => '广东-生成式AI备20250001号',
				'desc'        => __( '接入大模型或生成式 AI 服务的网信办备案编号。', 'wpicp' ),
			),
			'algorithm_number'       => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['algorithm_number'],
				'label'       => __( '境内互联网算法备案号', 'wpicp' ),
				'placeholder' => '网信算备440106123456701240019号',
				'desc'        => __( '互联网信息服务算法推荐管理系统的公示备案号。', 'wpicp' ),
			),
			'app_number'             => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['app_number'],
				'label'       => __( '移动应用 APP 备案号', 'wpicp' ),
				'placeholder' => '粤ICP备2023091240号-2A',
				'desc'        => __( '配套移动客户端在工信部登记的 APP 备案号。', 'wpicp' ),
			),
			'miniapp_number'         => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['miniapp_number'],
				'label'       => __( '小程序备案号', 'wpicp' ),
				'placeholder' => '粤ICP备2023091240号-3X',
				'desc'        => __( '微信、支付宝或抖音等平台小程序备案编号。', 'wpicp' ),
			),
			'report_email'           => array(
				'type'        => 'email',
				'tab'         => 'filing',
				'default'     => $defaults['report_email'],
				'label'       => __( '不良信息举报邮箱', 'wpicp' ),
				'placeholder' => 'report@example.com',
				'desc'        => __( '用于页脚公示违法和不良信息受理邮箱。', 'wpicp' ),
			),
			'report_phone'           => array(
				'type'        => 'text',
				'tab'         => 'filing',
				'default'     => $defaults['report_phone'],
				'label'       => __( '投诉与举报电话', 'wpicp' ),
				'placeholder' => '400-000-0000',
				'desc'        => __( '用于页脚公示客户投诉或违法信息举报热线。', 'wpicp' ),
			),
			'custom_licenses'        => array(
				'type'     => 'repeater',
				'tab'      => 'filing',
				'layout'   => 'stack',
				'max_rows' => 12,
				'default'  => $defaults['custom_licenses'],
				'label'    => __( '扩展行业行政许可证', 'wpicp' ),
				'desc'     => __( '可添加网络文化、广播电视或出版物等行业许可证。', 'wpicp' ),
				'fields'   => array(
					'label'  => array(
						'type'        => 'text',
						'label'       => __( '证照名称', 'wpicp' ),
						'placeholder' => __( '网络文化经营许可证', 'wpicp' ),
						'default'     => '',
					),
					'number' => array(
						'type'        => 'text',
						'label'       => __( '许可证编号', 'wpicp' ),
						'placeholder' => '粤网文〔2025〕0001-001号',
						'default'     => '',
					),
					'url'    => array(
						'type'        => 'url',
						'label'       => __( '查验链接（选填）', 'wpicp' ),
						'placeholder' => 'https://',
						'default'     => '',
					),
				),
			),
			'selected_presets'       => array(
				'type'         => 'multicheck',
				'tab'          => 'badges',
				'layout'       => 'stack',
				'presentation' => 'catalog',
				'default'      => $defaults['selected_presets'],
				'label'        => __( '官方监管与信任预设徽章', 'wpicp' ),
				'desc'         => __( '勾选需要展示的权威机构徽标，外链均采用 HTTPS。', 'wpicp' ),
				'choices'      => $preset_choices,
			),
			'certificates'           => array(
				'type'     => 'repeater',
				'tab'      => 'badges',
				'layout'   => 'stack',
				'max_rows' => 100,
				'default'  => $defaults['certificates'],
				'label'    => __( '电子证照亮照列表', 'wpicp' ),
				'desc'     => __( '从媒体库选择营业执照或许可证扫描件用于前台亮照。', 'wpicp' ),
				'fields'   => array(
					'image_id'   => array(
						'type'    => 'media',
						'label'   => __( '证照扫描图片', 'wpicp' ),
						'default' => 0,
					),
					'title'      => array(
						'type'        => 'text',
						'label'       => __( '证照标题', 'wpicp' ),
						'placeholder' => __( '营业执照电子亮照', 'wpicp' ),
						'default'     => '',
					),
					'type'       => array(
						'type'    => 'text',
						'label'   => __( '证照分类', 'wpicp' ),
						'default' => __( '营业执照', 'wpicp' ),
					),
					'number'     => array(
						'type'    => 'text',
						'label'   => __( '证照编号', 'wpicp' ),
						'default' => '',
					),
					'expires_at' => array(
						'type'    => 'text',
						'label'   => __( '有效期', 'wpicp' ),
						'default' => '',
					),
					'visible'    => array(
						'type'    => 'toggle',
						'label'   => __( '前台显示', 'wpicp' ),
						'default' => true,
					),
				),
			),
			'cert_watermark_enabled' => array(
				'type'    => 'toggle',
				'tab'     => 'badges',
				'default' => $defaults['cert_watermark_enabled'],
				'label'   => __( '启用亮照输出水印', 'wpicp' ),
				'desc'    => __( '对营业执照与行政许可证复印件自动叠加防盗水印，防止原图被盗用冒充。', 'wpicp' ),
			),
			'cert_watermark_text'    => array(
				'type'        => 'text',
				'tab'         => 'badges',
				'default'     => $defaults['cert_watermark_text'],
				'label'       => __( '水印文字模板', 'wpicp' ),
				'placeholder' => __( '仅供 {site_name} 官网亮照公示使用，他用无效', 'wpicp' ),
				'desc'        => __( '支持变量：{site_name} 站点名、{domain} 域名、{date} 当日日期。', 'wpicp' ),
				'depends_on'  => array(
					'field' => 'cert_watermark_enabled',
					'value' => true,
				),
			),
			'cert_watermark_layout'  => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'badges',
				'default'      => $defaults['cert_watermark_layout'],
				'label'        => __( '水印排布样式', 'wpicp' ),
				'choices'      => array(
					'tile'   => __( '斜向平铺', 'wpicp' ),
					'center' => __( '居中大字', 'wpicp' ),
					'corner' => __( '右下压印', 'wpicp' ),
				),
				'depends_on'   => array(
					'field' => 'cert_watermark_enabled',
					'value' => true,
				),
			),
			'cert_watermark_tone'    => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'badges',
				'default'      => $defaults['cert_watermark_tone'],
				'label'        => __( '水印浓度与色彩', 'wpicp' ),
				'choices'      => array(
					'light'    => __( '浅灰 16%', 'wpicp' ),
					'standard' => __( '标准 24%', 'wpicp' ),
					'strong'   => __( '深灰 38%', 'wpicp' ),
					'red'      => __( '防盗红章', 'wpicp' ),
				),
				'depends_on'   => array(
					'field' => 'cert_watermark_enabled',
					'value' => true,
				),
			),
			'cert_watermark_engine'  => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'badges',
				'default'      => $defaults['cert_watermark_engine'],
				'label'        => __( '水印合成方式', 'wpicp' ),
				'desc'         => __( '服务端合成通过 GD 动态输出带水印副本并缓存，右键另存同样带水印。', 'wpicp' ),
				'choices'      => array(
					'server' => __( '服务端真图合成（防扒原图）', 'wpicp' ),
					'css'    => __( '前端图层叠加（零负载）', 'wpicp' ),
				),
				'depends_on'   => array(
					'field' => 'cert_watermark_enabled',
					'value' => true,
				),
			),
			'cert_anti_save'         => array(
				'type'    => 'toggle',
				'tab'     => 'badges',
				'default' => $defaults['cert_anti_save'],
				'label'   => __( '禁用证照右键另存与拖拽', 'wpicp' ),
				'desc'    => __( '在亮照画廊与灯箱大图模式中禁用图片右键菜单与拖拽下载。', 'wpicp' ),
			),
			'certificate_click_mode' => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'badges',
				'default'      => $defaults['certificate_click_mode'],
				'label'        => __( '证照缩略图点击方式', 'wpicp' ),
				'desc'         => __( '选择点击证照后原地灯箱查看或跳转亮照详情页。', 'wpicp' ),
				'choices'      => array(
					'lightbox' => __( '灯箱放大查看', 'wpicp' ),
					'page'     => __( '跳转证照详情页', 'wpicp' ),
				),
			),
			'certificates_page'      => array(
				'type'      => 'entity',
				'post_type' => 'page',
				'tab'       => 'badges',
				'default'   => $defaults['certificates_page'],
				'label'     => __( '电子证照公示合集页', 'wpicp' ),
				'desc'      => __( '绑定含「文派证照公示合集」区块或短代码的公开页面，页脚「证照公示」入口将自动指向该页。', 'wpicp' ),
			),
			'badges'                 => array(
				'type'     => 'repeater',
				'tab'      => 'badges',
				'layout'   => 'stack',
				'max_rows' => 20,
				'default'  => $defaults['badges'],
				'label'    => __( '自定义合作与认证徽章', 'wpicp' ),
				'desc'     => __( '上传行业协会、云安全或合作伙伴认证图标及链接。', 'wpicp' ),
				'fields'   => array(
					'image_id' => array(
						'type'    => 'media',
						'label'   => __( '徽章图标', 'wpicp' ),
						'default' => 0,
					),
					'title'    => array(
						'type'        => 'text',
						'label'       => __( '徽章名称', 'wpicp' ),
						'placeholder' => __( '安全认证标识', 'wpicp' ),
						'default'     => '',
					),
					'url'      => array(
						'type'        => 'url',
						'label'       => __( '跳转链接', 'wpicp' ),
						'placeholder' => 'https://',
						'default'     => '',
					),
				),
			),
			'auto_footer'            => array(
				'type'    => 'toggle',
				'tab'     => 'display',
				'default' => $defaults['auto_footer'],
				'label'   => __( '自动注入站点页脚', 'wpicp' ),
				'desc'    => __( '免改主题模板，自动在页脚输出合规公示栏。', 'wpicp' ),
			),
			'footer_style'           => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'display',
				'default'      => $defaults['footer_style'],
				'label'        => __( '页脚合规栏版式', 'wpicp' ),
				'desc'         => __( '跟随宿主主题文字颜色，自动适配浅色与深色页脚。', 'wpicp' ),
				'choices'      => array(
					'minimal'  => __( '极简单行', 'wpicp' ),
					'standard' => __( '标准双行', 'wpicp' ),
					'badges'   => __( '含信任徽章', 'wpicp' ),
				),
				'depends_on'   => array(
					'field' => 'auto_footer',
					'value' => true,
				),
			),
			'footer_align'           => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'display',
				'default'      => $defaults['footer_align'],
				'label'        => __( '合规栏对齐方式', 'wpicp' ),
				'choices'      => array(
					'center'  => __( '居中对齐', 'wpicp' ),
					'left'    => __( '靠左对齐', 'wpicp' ),
					'between' => __( '两端分散', 'wpicp' ),
				),
				'depends_on'   => array(
					'field' => 'auto_footer',
					'value' => true,
				),
			),
			'footer_separator'       => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'display',
				'default'      => $defaults['footer_separator'],
				'label'        => __( '条目分隔符', 'wpicp' ),
				'choices'      => array(
					'|'     => __( '竖线 |', 'wpicp' ),
					'·'     => __( '圆点 ·', 'wpicp' ),
					'space' => __( '纯留白', 'wpicp' ),
				),
			),
			'footer_items'           => array(
				'type'         => 'multicheck',
				'presentation' => 'checkgroup',
				'tab'          => 'display',
				'default'      => $defaults['footer_items'],
				'label'        => __( '合规栏默认显示项', 'wpicp' ),
				'desc'         => __( '仅实际填写了内容的项目会在前台输出。', 'wpicp' ),
				'choices'      => array(
					'company'         => __( '主办单位名称', 'wpicp' ),
					'icp'             => __( '工信部 ICP 备案号', 'wpicp' ),
					'mps'             => __( '公安机关联网备案号', 'wpicp' ),
					'edi'             => __( '增值电信业务经营许可证', 'wpicp' ),
					'aigc'            => __( '生成式 AI 服务备案号', 'wpicp' ),
					'algorithm'       => __( '境内互联网算法备案号', 'wpicp' ),
					'app'             => __( '移动应用 APP 备案号', 'wpicp' ),
					'miniapp'         => __( '小程序备案号', 'wpicp' ),
					'custom_licenses' => __( '扩展行业行政许可证', 'wpicp' ),
					'report'          => __( '举报邮箱与投诉电话', 'wpicp' ),
					'policy'          => __( '站点合规政策声明链接', 'wpicp' ),
					'certificates'    => __( '电子证照公示合集页入口', 'wpicp' ),
				),
			),
			'default_image_size'     => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'badges',
				'default'      => $defaults['default_image_size'],
				'label'        => __( '证照与徽章默认图幅', 'wpicp' ),
				'choices'      => array(
					'thumbnail' => __( '缩略图', 'wpicp' ),
					'full'      => __( '原始尺寸', 'wpicp' ),
				),
			),
			'custom_css_class'       => array(
				'type'        => 'text',
				'tab'         => 'display',
				'default'     => $defaults['custom_css_class'],
				'label'       => __( '附加 CSS 类名', 'wpicp' ),
				'placeholder' => 'site-footer-compliance',
				'desc'        => __( '附加到前台短代码与区块外层容器的自定义类名。', 'wpicp' ),
			),
			'allow_subsite_override' => array(
				'type'    => 'toggle',
				'tab'     => 'display',
				'default' => $defaults['allow_subsite_override'],
				'label'   => __( '允许子站点覆盖网络配置', 'wpicp' ),
				'desc'    => __( '关闭后全网子站点强制继承网络管理端的备案配置。', 'wpicp' ),
			),
			'clean_on_uninstall'     => array(
				'type'    => 'toggle',
				'tab'     => 'tools',
				'default' => $defaults['clean_on_uninstall'],
				'label'   => __( '卸载时清理插件配置', 'wpicp' ),
				'desc'    => __( '仅在开启此项并删除插件时移除本插件保存的设置。', 'wpicp' ),
			),
			'access_provider'        => array(
				'type'    => 'select',
				'tab'     => 'audit_access',
				'default' => $defaults['access_provider'],
				'label'   => __( '备案接入服务商', 'wpicp' ),
				'desc'    => __( '标记当前域名 ICP 备案所在的中国内地云服务商。', 'wpicp' ),
				'choices' => AccessMonitor::provider_choices(),
			),
			'home_check_enabled'     => array(
				'type'    => 'toggle',
				'tab'     => 'audit_access',
				'default' => $defaults['home_check_enabled'],
				'label'   => __( '启用首页备案定时自检', 'wpicp' ),
				'desc'    => __( '每日检查首页可达性、底部 ICP 备案号、beian.miit.gov.cn 链接及标题一致性。', 'wpicp' ),
			),
			'keepalive_enabled'      => array(
				'type'    => 'toggle',
				'tab'     => 'audit_access',
				'default' => $defaults['keepalive_enabled'],
				'label'   => __( '启用接入商子域名保活访问', 'wpicp' ),
				'desc'    => __( '按天或按周低频请求解析至原接入商中国内地服务器 IP 的子域名，核对 DNS 并产生合规访问记录。', 'wpicp' ),
			),
			'keepalive_url'          => array(
				'type'        => 'url',
				'tab'         => 'audit_access',
				'default'     => $defaults['keepalive_url'],
				'label'       => __( '保活子域名地址', 'wpicp' ),
				'placeholder' => 'https://beian.example.com/',
				'desc'        => __( '将该子域名解析到原接入商中国内地服务器并放置含备案号的合规落地页。', 'wpicp' ),
				'depends_on'  => array(
					'field' => 'keepalive_enabled',
					'value' => true,
				),
			),
			'expected_provider_ips'  => array(
				'type'        => 'text',
				'tab'         => 'audit_access',
				'default'     => $defaults['expected_provider_ips'],
				'label'       => __( '预期接入商服务器 IP / CIDR', 'wpicp' ),
				'placeholder' => '47.115.120.18, 47.115.120.0/24',
				'desc'        => __( '定期校验 A / AAAA / CNAME 最终落地 IP，脱离该集合即标记接入不符风险。', 'wpicp' ),
				'depends_on'  => array(
					'field' => 'keepalive_enabled',
					'value' => true,
				),
			),
			'keepalive_interval'     => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'audit_access',
				'default'      => $defaults['keepalive_interval'],
				'label'        => __( '定时访问与探测频率', 'wpicp' ),
				'desc'         => __( '按天或按周低频访问即可维持接入活跃与日志，避免触发 WAF 防护。', 'wpicp' ),
				'choices'      => array(
					'1d'  => __( '每天 1 次', 'wpicp' ),
					'3d'  => __( '每 3 天 1 次', 'wpicp' ),
					'7d'  => __( '每周 1 次', 'wpicp' ),
					'14d' => __( '每 2 周 1 次', 'wpicp' ),
				),
				'depends_on'   => array(
					'field' => 'keepalive_enabled',
					'value' => true,
				),
			),
			'keepalive_keyword'      => array(
				'type'        => 'text',
				'tab'         => 'audit_access',
				'default'     => $defaults['keepalive_keyword'],
				'label'       => __( '响应体校验关键词', 'wpicp' ),
				'placeholder' => __( '留空自动使用 ICP 备案号', 'wpicp' ),
				'desc'        => __( '状态码 200 且正文含该关键词、不含阻断/取消接入提示词才判通过。', 'wpicp' ),
				'depends_on'  => array(
					'field' => 'keepalive_enabled',
					'value' => true,
				),
			),
			'alert_email'            => array(
				'type'        => 'email',
				'tab'         => 'audit_access',
				'default'     => $defaults['alert_email'],
				'label'       => __( '异常告警接收邮箱', 'wpicp' ),
				'placeholder' => 'ops@example.com',
				'desc'        => __( '连续 2 次检测异常时发送提醒，预留 5 个工作日整改缓冲期。', 'wpicp' ),
			),
			'ad_law_enabled'         => array(
				'type'    => 'toggle',
				'tab'     => 'audit_adlaw',
				'default' => $defaults['ad_law_enabled'],
				'label'   => __( '启用《广告法》极限词巡检', 'wpicp' ),
				'desc'    => __( '本地毫秒级检测违禁词，内容不上传外部接口，向生态插件开放巡检钩子。', 'wpicp' ),
			),
			'ad_law_remote_sync'     => array(
				'type'       => 'toggle',
				'tab'        => 'audit_adlaw',
				'default'    => $defaults['ad_law_remote_sync'],
				'label'      => __( '启用无言（WordYeah）远程词库', 'wpicp' ),
				'desc'       => __( '从 api.wpcy.com 单向拉取无言（WordYeah）维护的最新极限词与安全复合词清单至本地缓存。', 'wpicp' ),
				'depends_on' => array(
					'field' => 'ad_law_enabled',
					'value' => true,
				),
			),
			'ai_engine'              => array(
				'type'       => 'choice',
				'tab'        => 'audit_adlaw',
				'layout'     => 'stack',
				'default'    => $defaults['ai_engine'],
				'label'      => __( '极限词复核方式', 'wpicp' ),
				'desc'       => __( '默认仅用本地词库扫描；启用文派心思后可在离线命中时结合语境甄别客观陈述与误报。', 'wpicp' ),
				'choices'    => static function () {
					$choices = array(
						'offline' => array(
							'label' => __( '纯离线词库', 'wpicp' ),
							'desc'  => __( '本地毫秒级匹配无言词库，文章内容不上传外部接口。', 'wpicp' ),
							'icon'  => 'shield-check',
						),
					);
					$gate    = WpmindReviewer::choice_gate();
					if ( empty( $gate['hidden'] ) ) {
						$choices['wpmind'] = array(
							'label'    => __( '文派心思语义复核', 'wpicp' ),
							'desc'     => __( '离线命中后由文派心思判断语境剔除误报，不可用时自动退回离线结果。', 'wpicp' ),
							'icon'     => 'sparkle',
							'hint'     => (string) ( $gate['hint'] ?? '' ),
							'disabled' => ! empty( $gate['disabled'] ),
						);
					}
					return $choices;
				},
				'depends_on' => array(
					'field' => 'ad_law_enabled',
					'value' => true,
				),
			),
			'ai_engine_status'       => array(
				'type'       => 'status',
				'tab'        => 'audit_adlaw',
				'label'      => __( '文派心思通道', 'wpicp' ),
				'status'     => static function () {
					return WpmindReviewer::status_payload();
				},
				'depends_on' => array(
					'field' => 'ad_law_enabled',
					'value' => true,
				),
			),
			'ad_law_sync_interval'   => array(
				'type'         => 'radio',
				'presentation' => 'seg',
				'tab'          => 'audit_adlaw',
				'default'      => $defaults['ad_law_sync_interval'],
				'label'        => __( '远程词库更新频率', 'wpicp' ),
				'desc'         => __( '离线或网络受限时自动回退至插件内置基础词库，不影响本地扫描。', 'wpicp' ),
				'choices'      => array(
					'daily'  => __( '每天同步', 'wpicp' ),
					'weekly' => __( '每周同步', 'wpicp' ),
					'manual' => __( '仅手动同步', 'wpicp' ),
				),
				'depends_on'   => array(
					'field' => 'ad_law_remote_sync',
					'value' => true,
				),
			),
			'ad_law_rule_groups'     => array(
				'type'       => 'multicheck',
				'tab'        => 'audit_adlaw',
				'default'    => $defaults['ad_law_rule_groups'],
				'label'      => __( '无言词库启用分组', 'wpicp' ),
				'desc'       => __( '按站点所属行业勾选需要巡检的规则分类，减少跨行业误报。', 'wpicp' ),
				'choices'    => array(
					'absolute'  => __( '绝对化用语（最/第一/顶级）', 'wpicp' ),
					'promise'   => __( '无法证实承诺（永久/100%/包治）', 'wpicp' ),
					'authority' => __( '违规权威背书（国家级/特供/驰名）', 'wpicp' ),
					'regulated' => __( '医疗与金融行业敏感词', 'wpicp' ),
				),
				'depends_on' => array(
					'field' => 'ad_law_enabled',
					'value' => true,
				),
			),
			'ad_law_editor_panel'    => array(
				'type'       => 'toggle',
				'tab'        => 'audit_adlaw',
				'default'    => $defaults['ad_law_editor_panel'],
				'label'      => __( '编辑器实时巡检与发布前提示', 'wpicp' ),
				'desc'       => __( '写作时标出极限词风险与替换建议，不强制阻断发布。', 'wpicp' ),
				'depends_on' => array(
					'field' => 'ad_law_enabled',
					'value' => true,
				),
			),
			'ad_law_scan_post_types' => array(
				'type'       => 'multiselect',
				'tab'        => 'audit_adlaw',
				'default'    => $defaults['ad_law_scan_post_types'],
				'label'      => __( '全站巡检内容类型', 'wpicp' ),
				'desc'       => __( '选择全站批量巡检扫描的公开内容类型。', 'wpicp' ),
				'choices'    => $post_types_cb,
				'depends_on' => array(
					'field' => 'ad_law_enabled',
					'value' => true,
				),
			),
			'ad_law_allowlist'       => array(
				'type'       => 'tags',
				'tab'        => 'audit_adlaw',
				'default'    => $defaults['ad_law_allowlist'],
				'label'      => __( '站点自定义豁免词白名单', 'wpicp' ),
				'desc'       => __( '内置 30 条安全复合词，可在此补充品牌专有豁免短语。', 'wpicp' ),
				'depends_on' => array(
					'field' => 'ad_law_enabled',
					'value' => true,
				),
			),
			'module_badges'          => array(
				'type'    => 'toggle',
				'tab'     => 'modules',
				'default' => $defaults['module_badges'],
				'label'   => __( '证照与徽章', 'wpicp' ),
				'desc'    => __( '开启后显示「证照」标签页，支持官方预设徽标、电子证照防盗水印亮照与前台合集页。', 'wpicp' ),
			),
			'module_access'          => array(
				'type'    => 'toggle',
				'tab'     => 'modules',
				'default' => $defaults['module_access'],
				'label'   => __( '备案与接入保活', 'wpicp' ),
				'desc'    => __( '开启后显示「巡检」入口，提供首页备案合规定时自检与接入商子域名低频保活探测。', 'wpicp' ),
			),
			'module_adlaw'           => array(
				'type'    => 'toggle',
				'tab'     => 'modules',
				'default' => $defaults['module_adlaw'],
				'label'   => __( '广告法极限词巡检', 'wpicp' ),
				'desc'    => __( '开启后显示「巡检」入口，提供编辑器发布前风险提示、全站扫描与无言远程词库同步。', 'wpicp' ),
			),
		);

		return Wenpai_Admin_Settings::register(
			$option_name,
			$fields,
			array(
				'layout'  => 'row',
				'version' => self::SCHEMA_VERSION,
				'tools'   => array(
					'plugin'         => 'wpicp',
					'plugin_version' => defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0',
					'capability'     => $is_network_opt ? 'manage_network_options' : 'manage_options',
				),
			)
		);
	}
}
