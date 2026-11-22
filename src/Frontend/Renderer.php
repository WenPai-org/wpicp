<?php
/**
 * Frontend compliance bar, 12+1 shortcodes, trust badges, and certificate gallery renderer.
 *
 * Zero inline `style` attributes; all presentation uses clean semantic classes
 * consuming `currentColor`, `--wenpai-*`, or `--bs-*` tokens.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Frontend;

use WenPai\ICP\Media\CertificateWatermark;
use WenPai\ICP\Options\BadgeCatalog;
use WenPai\ICP\Options\SettingsStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders frontend shortcodes, auto-injected footer bar, and certificate lightbox.
 */
class Renderer {

	/**
	 * Whether frontend stylesheet/script has been enqueued this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Register shortcodes, frontend assets, and `wp_footer` auto-injection hook.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_shortcodes' ), 20 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
			add_action( 'wp_footer', array( __CLASS__, 'maybe_render_auto_footer' ), 20 );
		}
	}

	/**
	 * Register `[wpicp]` plus all 12 legacy `wpicp_*` shortcode aliases.
	 *
	 * @return void
	 */
	public static function register_shortcodes() {
		if ( ! function_exists( 'add_shortcode' ) ) {
			return;
		}

		add_shortcode( 'wpicp', array( __CLASS__, 'shortcode_wpicp' ) );

		// 12 legacy shortcode aliases from `wpicp-license` 1.x.
		add_shortcode( 'wpicp_license', array( __CLASS__, 'shortcode_license' ) );
		add_shortcode( 'wpicp_wangan', array( __CLASS__, 'shortcode_wangan' ) );
		add_shortcode( 'wpicp_province', array( __CLASS__, 'shortcode_province' ) );
		add_shortcode( 'wpicp_p', array( __CLASS__, 'shortcode_province_abbr' ) );
		add_shortcode( 'wpicp_company', array( __CLASS__, 'shortcode_company' ) );
		add_shortcode( 'wpicp_email', array( __CLASS__, 'shortcode_email' ) );
		add_shortcode( 'wpicp_phone', array( __CLASS__, 'shortcode_phone' ) );
		add_shortcode( 'wpicp_edi', array( __CLASS__, 'shortcode_edi' ) );
		add_shortcode( 'wpicp_app', array( __CLASS__, 'shortcode_app' ) );
		add_shortcode( 'wpicp_miniapp', array( __CLASS__, 'shortcode_miniapp' ) );
		add_shortcode( 'wpicp_certificates', array( __CLASS__, 'shortcode_certificates' ) );
		add_shortcode( 'wpicp_badges', array( __CLASS__, 'shortcode_badges' ) );
	}

	/**
	 * Register frontend CSS and lightweight certificate lightbox JS.
	 *
	 * @return void
	 */
	public static function register_assets() {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'WPICP_PLUGIN_URL' ) ) {
			return;
		}
		$ver = defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0';
		wp_register_style(
			'wpicp-frontend',
			WPICP_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$ver
		);
		wp_register_script(
			'wpicp-frontend',
			WPICP_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			$ver,
			true
		);

		$cfg = SettingsStore::effective();
		if ( ! empty( $cfg['auto_footer'] ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Enqueue frontend stylesheet on demand when any shortcode, block, or footer renders.
	 *
	 * @param bool $with_lightbox Whether to enqueue certificate lightbox JS.
	 * @return void
	 */
	public static function enqueue_assets( $with_lightbox = false ) {
		if ( ! self::$assets_enqueued && function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'wpicp-frontend' );
			self::$assets_enqueued = true;
		}
		if ( $with_lightbox && function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'wpicp-frontend' );
		}
	}

	/**
	 * Output compliance bar in `wp_footer` when `auto_footer` is enabled.
	 *
	 * @return void
	 */
	public static function maybe_render_auto_footer() {
		$cfg = SettingsStore::effective();
		if ( empty( $cfg['auto_footer'] ) ) {
			return;
		}
		$html = self::render_compliance_bar(
			array(
				'preset'    => $cfg['footer_style'],
				'align'     => $cfg['footer_align'],
				'separator' => $cfg['footer_separator'],
				'items'     => $cfg['footer_items'],
			),
			$cfg
		);
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render_compliance_bar().
		}
	}

	/**
	 * Semantic `[wpicp]` shortcode handler.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_wpicp( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'field'     => '',
				'preset'    => '',
				'align'     => '',
				'separator' => '',
				'theme'     => 'auto',
			),
			is_array( $atts ) ? $atts : array(),
			'wpicp'
		);

		$field = sanitize_key( (string) $atts['field'] );
		if ( '' === $field ) {
			return self::render_compliance_bar(
				array(
					'preset'    => (string) $atts['preset'],
					'align'     => (string) $atts['align'],
					'separator' => (string) $atts['separator'],
					'theme'     => (string) $atts['theme'],
				)
			);
		}

		$cfg = SettingsStore::effective();
		switch ( $field ) {
			case 'icp':
			case 'license':
				return self::shortcode_license();
			case 'mps':
			case 'wangan':
				return self::shortcode_wangan();
			case 'company':
				return self::shortcode_company();
			case 'edi':
				return self::shortcode_edi();
			case 'aigc':
				return '' !== $cfg['aigc_number'] ? esc_html( (string) $cfg['aigc_number'] ) : '';
			case 'algorithm':
				return '' !== $cfg['algorithm_number']
					? '<a class="wpicp-bar__link" href="https://beian.cac.gov.cn/" target="_blank" rel="nofollow noopener noreferrer">' . esc_html( (string) $cfg['algorithm_number'] ) . '</a>'
					: '';
			case 'app':
				return self::shortcode_app();
			case 'miniapp':
				return self::shortcode_miniapp();
			case 'email':
				return self::shortcode_email();
			case 'phone':
				return self::shortcode_phone();
			case 'report':
				return self::format_report_inline( $cfg );
			case 'province':
				return self::shortcode_province();
			case 'province_abbr':
				return self::shortcode_province_abbr();
			case 'badges':
				return self::shortcode_badges( array() );
			case 'certificates':
				return self::shortcode_certificates( array() );
			default:
				return '';
		}
	}

	/**
	 * Legacy `[wpicp_license]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_license() {
		$cfg = SettingsStore::effective();
		if ( '' === $cfg['icp_number'] ) {
			return '';
		}
		self::enqueue_assets();
		return '<a class="wpicp-bar__link" href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener noreferrer">' . esc_html( (string) $cfg['icp_number'] ) . '</a>';
	}

	/**
	 * Legacy `[wpicp_wangan]` shortcode (fixes broken `gongan.jpg` -> `gongan.png` and uses MPS HTTPS query URL).
	 *
	 * @return string
	 */
	public static function shortcode_wangan() {
		$cfg      = SettingsStore::effective();
		$mps_info = BadgeCatalog::parse_mps( (string) $cfg['public_security_code'], (string) $cfg['province_abbr'] );
		if ( '' === $mps_info['code'] ) {
			return '';
		}
		self::enqueue_assets();
		$icon_url = BadgeCatalog::image_url( 'gongan.png' );
		$icon     = '<img class="wpicp-bar__emblem" src="' . esc_url( $icon_url ) . '" alt="" width="16" height="16" loading="lazy">';
		return '<a class="wpicp-bar__link wpicp-bar__mps" href="' . esc_url( $mps_info['url'] ) . '" target="_blank" rel="nofollow noopener noreferrer">' . $icon . '<span>' . esc_html( $mps_info['formatted'] ) . '</span></a>';
	}

	/**
	 * Legacy `[wpicp_province]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_province() {
		$cfg       = SettingsStore::effective();
		$provinces = BadgeCatalog::provinces();
		$abbr      = (string) $cfg['province_abbr'];
		return isset( $provinces[ $abbr ] ) ? esc_html( $provinces[ $abbr ] ) : '';
	}

	/**
	 * Legacy `[wpicp_p]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_province_abbr() {
		$cfg = SettingsStore::effective();
		return '' !== $cfg['province_abbr'] ? esc_html( (string) $cfg['province_abbr'] ) : '';
	}

	/**
	 * Legacy `[wpicp_company]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_company() {
		$cfg = SettingsStore::effective();
		return '' !== $cfg['company_legal_name'] ? esc_html( (string) $cfg['company_legal_name'] ) : '';
	}

	/**
	 * Legacy `[wpicp_email]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_email() {
		$cfg = SettingsStore::effective();
		return '' !== $cfg['report_email'] ? esc_html( (string) $cfg['report_email'] ) : '';
	}

	/**
	 * Legacy `[wpicp_phone]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_phone() {
		$cfg = SettingsStore::effective();
		return '' !== $cfg['report_phone'] ? esc_html( (string) $cfg['report_phone'] ) : '';
	}

	/**
	 * Legacy `[wpicp_edi]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_edi() {
		$cfg = SettingsStore::effective();
		return '' !== $cfg['edi_number'] ? esc_html( (string) $cfg['edi_number'] ) : '';
	}

	/**
	 * Legacy `[wpicp_app]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_app() {
		$cfg = SettingsStore::effective();
		return '' !== $cfg['app_number'] ? esc_html( (string) $cfg['app_number'] ) : '';
	}

	/**
	 * Legacy `[wpicp_miniapp]` shortcode.
	 *
	 * @return string
	 */
	public static function shortcode_miniapp() {
		$cfg = SettingsStore::effective();
		return '' !== $cfg['miniapp_number'] ? esc_html( (string) $cfg['miniapp_number'] ) : '';
	}

	/**
	 * Legacy `[wpicp_certificates]` shortcode (zero inline styles, supports lightbox & gallery page).
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_certificates( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'full'  => 'false',
				'id'    => '',
				'ids'   => '',
				'align' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'wpicp_certificates'
		);

		$cfg   = SettingsStore::effective();
		$certs = is_array( $cfg['certificates'] ) ? array_values( $cfg['certificates'] ) : array();
		if ( empty( $certs ) ) {
			return '';
		}

		$certs = self::filter_indexed_items( $certs, (string) $atts['id'], (string) $atts['ids'] );
		if ( empty( $certs ) ) {
			return '';
		}

		$is_full    = 'true' === (string) $atts['full'] || '1' === (string) $atts['full'];
		$size       = $is_full ? 'full' : (string) $cfg['default_image_size'];
		$click_mode = (string) $cfg['certificate_click_mode'];
		$page_id    = absint( $cfg['certificates_page'] );
		$page_url   = ( $page_id > 0 && function_exists( 'get_permalink' ) ) ? (string) get_permalink( $page_id ) : '';
		$use_dialog = ! $is_full && ( 'lightbox' === $click_mode || '' === $page_url );

		$wm_on       = ! empty( $cfg['cert_watermark_enabled'] );
		$wm_css_only = $wm_on && 'css' === (string) $cfg['cert_watermark_engine'];
		$anti_save   = ! empty( $cfg['cert_anti_save'] );
		$wm_text     = $wm_on ? CertificateWatermark::resolve_text( '', $cfg ) : '';
		$wm_cls      = array( 'wpicp-certificates__wm' );
		if ( 'center' === (string) $cfg['cert_watermark_layout'] ) {
			$wm_cls[] = 'is-center';
		} elseif ( 'corner' === (string) $cfg['cert_watermark_layout'] ) {
			$wm_cls[] = 'is-corner';
		}
		if ( in_array( (string) $cfg['cert_watermark_tone'], array( 'light', 'strong', 'red' ), true ) ) {
			$wm_cls[] = 'is-' . (string) $cfg['cert_watermark_tone'];
		}

		self::enqueue_assets( $use_dialog || $anti_save );

		$classes = array( 'wpicp-certificates' );
		if ( 'left' === (string) $atts['align'] ) {
			$classes[] = 'wpicp-certificates--left';
		}
		if ( '' !== $cfg['custom_css_class'] ) {
			$classes[] = $cfg['custom_css_class'];
		}

		$anti_attr = $anti_save ? ' data-wpicp-anti-save="1"' : '';
		$out       = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $anti_attr . '>';
		foreach ( $certs as $idx => $cert ) {
			if ( array_key_exists( 'visible', $cert ) && ! $cert['visible'] ) {
				continue;
			}
			$img_id    = isset( $cert['image_id'] ) ? absint( $cert['image_id'] ) : 0;
			$title     = isset( $cert['title'] ) ? (string) $cert['title'] : '';
			$thumb_url = $img_id > 0 ? CertificateWatermark::image_url( $img_id, $size, $cfg ) : '';
			$full_url  = $img_id > 0 ? CertificateWatermark::image_url( $img_id, 'full', $cfg ) : '';
			$img_html  = '' !== $thumb_url
				? '<span class="wpicp-certificates__frame"><img class="wpicp-certificates__img" src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">'
					. ( $wm_css_only ? '<span class="' . esc_attr( implode( ' ', $wm_cls ) ) . '" aria-hidden="true">' . esc_html( $wm_text ) . '</span>' : '' )
					. '</span>'
				: '';

			if ( '' === $img_html && '' === $title ) {
				continue;
			}

			$caption = '' !== $title ? '<span class="wpicp-certificates__title">' . esc_html( $title ) . '</span>' : '';
			$inner   = $img_html . $caption;

			if ( $is_full ) {
				$out .= '<figure class="wpicp-certificates__item">' . $inner . '</figure>';
			} elseif ( $use_dialog && '' !== $full_url ) {
				$dialog_id = 'wpicp-cert-dialog-' . (int) $idx . '-' . $img_id;
				$out      .= '<div class="wpicp-certificates__item">';
				$out      .= '<button type="button" class="wpicp-certificates__trigger" data-wpicp-lightbox="' . esc_attr( $dialog_id ) . '">' . $inner . '</button>';
				$out      .= '<dialog class="wpicp-certificates__dialog" id="' . esc_attr( $dialog_id ) . '" aria-label="' . esc_attr( '' !== $title ? $title : __( '电子证照查看', 'wpicp' ) ) . '"' . $anti_attr . '>';
				$out      .= '<div class="wpicp-certificates__modal">';
				$out      .= '<span class="wpicp-certificates__frame"><img class="wpicp-certificates__full" src="' . esc_url( $full_url ) . '" alt="' . esc_attr( $title ) . '">'
					. ( $wm_css_only ? '<span class="' . esc_attr( implode( ' ', $wm_cls ) ) . '" aria-hidden="true">' . esc_html( $wm_text ) . '</span>' : '' )
					. '</span>';
				if ( '' !== $title ) {
					$out .= '<p class="wpicp-certificates__caption">' . esc_html( $title ) . '</p>';
				}
				$out .= '<button type="button" class="wpicp-certificates__close" data-wpicp-lightbox-close>' . esc_html__( '关闭', 'wpicp' ) . '</button>';
				$out .= '</div></dialog></div>';
			} elseif ( '' !== $page_url ) {
				$out .= '<div class="wpicp-certificates__item"><a class="wpicp-certificates__link" href="' . esc_url( $page_url ) . '" target="_blank" rel="noopener noreferrer">' . $inner . '</a></div>';
			} else {
				$out .= '<div class="wpicp-certificates__item">' . $inner . '</div>';
			}
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Legacy `[wpicp_badges]` shortcode (fixes inverted `preset` boolean bug while keeping 100% compatibility).
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_badges( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'id'     => '',
				'ids'    => '',
				'preset' => 'all',
				'align'  => '',
				'size'   => '',
			),
			is_array( $atts ) ? $atts : array(),
			'wpicp_badges'
		);

		return self::render_badges_list(
			array(
				'id'              => (string) $atts['id'],
				'ids'             => (string) $atts['ids'],
				'include_presets' => ! in_array( strtolower( (string) $atts['preset'] ), array( 'false', '0', 'no' ), true ),
				'align'           => (string) $atts['align'],
				'size'            => (string) $atts['size'],
			)
		);
	}

	/**
	 * Render trust badges list from custom uploaded badges + selected official HTTPS presets.
	 *
	 * @param array<string, mixed>      $args     Render options.
	 * @param array<string, mixed>|null $settings Optional settings override.
	 * @return string
	 */
	public static function render_badges_list( array $args = array(), ?array $settings = null ) {
		$cfg             = is_array( $settings ) ? SettingsStore::normalize( $settings ) : SettingsStore::effective();
		$include_presets = ! array_key_exists( 'include_presets', $args ) || ! empty( $args['include_presets'] );
		$size            = ! empty( $args['size'] ) && in_array( (string) $args['size'], array( 'thumbnail', 'full' ), true )
			? (string) $args['size']
			: (string) $cfg['default_image_size'];

		$all_badges = array();

		foreach ( (array) $cfg['badges'] as $badge ) {
			if ( ! is_array( $badge ) ) {
				continue;
			}
			$all_badges[] = array(
				'image_id'     => isset( $badge['image_id'] ) ? absint( $badge['image_id'] ) : 0,
				'preset_image' => '',
				'title'        => isset( $badge['title'] ) ? (string) $badge['title'] : '',
				'url'          => isset( $badge['url'] ) ? BadgeCatalog::enforce_https( (string) $badge['url'] ) : '',
			);
		}

		if ( $include_presets ) {
			$presets = BadgeCatalog::presets( true );
			foreach ( (array) $cfg['selected_presets'] as $key ) {
				if ( isset( $presets[ $key ] ) ) {
					$all_badges[] = array(
						'image_id'     => 0,
						'preset_image' => BadgeCatalog::image_url( $presets[ $key ]['file'] ),
						'title'        => $presets[ $key ]['title'],
						'url'          => $presets[ $key ]['url'],
					);
				}
			}
		}

		if ( empty( $all_badges ) ) {
			return '';
		}

		$id_filter  = isset( $args['id'] ) ? (string) $args['id'] : '';
		$ids_filter = isset( $args['ids'] ) ? (string) $args['ids'] : '';
		$all_badges = self::filter_indexed_items( $all_badges, $id_filter, $ids_filter );
		if ( empty( $all_badges ) ) {
			return '';
		}

		self::enqueue_assets();

		$classes = array( 'wpicp-badges' );
		if ( isset( $args['align'] ) && 'left' === (string) $args['align'] ) {
			$classes[] = 'wpicp-badges--left';
		}
		if ( '' !== $cfg['custom_css_class'] ) {
			$classes[] = $cfg['custom_css_class'];
		}

		$rendered_items = array();
		foreach ( $all_badges as $item ) {
			$title = (string) $item['title'];
			if ( '' !== $item['preset_image'] ) {
				$img = '<img class="wpicp-badges__img" src="' . esc_url( $item['preset_image'] ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">';
			} elseif ( $item['image_id'] > 0 && function_exists( 'wp_get_attachment_image' ) ) {
				$img = wp_get_attachment_image(
					$item['image_id'],
					$size,
					false,
					array(
						'class' => 'wpicp-badges__img',
						'alt'   => $title,
					)
				);
			} else {
				$img = '';
			}

			if ( '' === $img ) {
				continue;
			}

			if ( '' !== $item['url'] ) {
				$rendered_items[] = '<a class="wpicp-badges__item" href="' . esc_url( $item['url'] ) . '" target="_blank" rel="nofollow noopener noreferrer" title="' . esc_attr( $title ) . '">' . $img . '</a>';
			} else {
				$rendered_items[] = '<span class="wpicp-badges__item" title="' . esc_attr( $title ) . '">' . $img . '</span>';
			}
		}

		if ( empty( $rendered_items ) ) {
			return '';
		}

		return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' . implode( '', $rendered_items ) . '</div>';
	}

	/**
	 * Render the modern compliance bar used by `auto_footer`, `[wpicp]`, `wpicp/compliance-bar`, and admin preview.
	 *
	 * @param array<string, mixed>      $args     Bar display options (`preset`, `align`, `separator`, `theme`, `items`, `demo_fallback`).
	 * @param array<string, mixed>|null $settings Optional settings snapshot.
	 * @return string
	 */
	public static function render_compliance_bar( array $args = array(), ?array $settings = null ) {
		$cfg = is_array( $settings ) ? SettingsStore::normalize( $settings ) : SettingsStore::effective();

		$preset    = ! empty( $args['preset'] ) && in_array( (string) $args['preset'], array( 'minimal', 'standard', 'badges' ), true )
			? (string) $args['preset']
			: (string) $cfg['footer_style'];
		$align     = ! empty( $args['align'] ) && in_array( (string) $args['align'], array( 'center', 'left', 'between' ), true )
			? (string) $args['align']
			: (string) $cfg['footer_align'];
		$separator = ! empty( $args['separator'] ) && in_array( (string) $args['separator'], array( '|', '·', 'space' ), true )
			? (string) $args['separator']
			: (string) $cfg['footer_separator'];
		$theme     = ! empty( $args['theme'] ) && in_array( (string) $args['theme'], array( 'auto', 'light', 'dark' ), true )
			? (string) $args['theme']
			: 'auto';

		$enabled_items = isset( $args['items'] ) && is_array( $args['items'] )
			? $args['items']
			: (array) $cfg['footer_items'];

		if ( ! empty( $args['demo_fallback'] ) && '' === $cfg['icp_number'] && '' === $cfg['public_security_code'] && '' === $cfg['company_legal_name'] ) {
			$cfg['company_legal_name']   = __( '示例科技有限公司', 'wpicp' );
			$cfg['icp_number']           = '粤ICP备2023091240号-1';
			$cfg['province_abbr']        = '粤';
			$cfg['public_security_code'] = '44010602000000';
			if ( empty( $cfg['selected_presets'] ) ) {
				$cfg['selected_presets'] = array( 'miit', 'psb', '12377' );
			}
		}

		$primary_line   = array();
		$secondary_line = array();

		if ( in_array( 'company', $enabled_items, true ) && '' !== $cfg['company_legal_name'] ) {
			$year           = function_exists( 'wp_date' ) ? wp_date( 'Y' ) : gmdate( 'Y' );
			$primary_line[] = '<span class="wpicp-bar__item">© ' . esc_html( $year . ' ' . $cfg['company_legal_name'] ) . '</span>';
		}

		if ( in_array( 'icp', $enabled_items, true ) && '' !== $cfg['icp_number'] ) {
			$primary_line[] = '<span class="wpicp-bar__item"><a class="wpicp-bar__link" href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow noopener noreferrer">' . esc_html( (string) $cfg['icp_number'] ) . '</a></span>';
		}

		if ( in_array( 'mps', $enabled_items, true ) && '' !== $cfg['public_security_code'] ) {
			$mps_info = BadgeCatalog::parse_mps( (string) $cfg['public_security_code'], (string) $cfg['province_abbr'] );
			if ( '' !== $mps_info['code'] ) {
				$emblem         = 'minimal' === $preset
					? ''
					: '<img class="wpicp-bar__emblem" src="' . esc_url( BadgeCatalog::image_url( 'gongan.png' ) ) . '" alt="" width="16" height="16" loading="lazy">';
				$primary_line[] = '<span class="wpicp-bar__item"><a class="wpicp-bar__link wpicp-bar__mps" href="' . esc_url( $mps_info['url'] ) . '" target="_blank" rel="nofollow noopener noreferrer">' . $emblem . '<span>' . esc_html( $mps_info['formatted'] ) . '</span></a></span>';
			}
		}

		if ( in_array( 'policy', $enabled_items, true ) && function_exists( 'get_privacy_policy_url' ) ) {
			$policy_url = trim( (string) get_privacy_policy_url() );
			if ( '' !== $policy_url ) {
				$policy_title   = __( '合规声明', 'wpicp' );
				$policy_page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
				if ( $policy_page_id > 0 && function_exists( 'get_the_title' ) ) {
					$fetched = trim( (string) get_the_title( $policy_page_id ) );
					if ( '' !== $fetched ) {
						$policy_title = $fetched;
					}
				}
				$primary_line[] = '<span class="wpicp-bar__item"><a class="wpicp-bar__link" href="' . esc_url( $policy_url ) . '">' . esc_html( $policy_title ) . '</a></span>';
			}
		}

		if ( 'minimal' !== $preset ) {
			if ( in_array( 'edi', $enabled_items, true ) && '' !== $cfg['edi_number'] ) {
				$secondary_line[] = '<span class="wpicp-bar__item">' . esc_html__( '增值电信业务经营许可证：', 'wpicp' ) . esc_html( (string) $cfg['edi_number'] ) . '</span>';
			}
			if ( in_array( 'aigc', $enabled_items, true ) && '' !== $cfg['aigc_number'] ) {
				$secondary_line[] = '<span class="wpicp-bar__item">' . esc_html__( '生成式人工智能服务备案：', 'wpicp' ) . esc_html( (string) $cfg['aigc_number'] ) . '</span>';
			}
			if ( in_array( 'algorithm', $enabled_items, true ) && '' !== $cfg['algorithm_number'] ) {
				$secondary_line[] = '<span class="wpicp-bar__item"><a class="wpicp-bar__link" href="https://beian.cac.gov.cn/" target="_blank" rel="nofollow noopener noreferrer">' . esc_html__( '互联网算法备案：', 'wpicp' ) . esc_html( (string) $cfg['algorithm_number'] ) . '</a></span>';
			}
			if ( in_array( 'app', $enabled_items, true ) && '' !== $cfg['app_number'] ) {
				$secondary_line[] = '<span class="wpicp-bar__item">' . esc_html__( 'APP备案：', 'wpicp' ) . esc_html( (string) $cfg['app_number'] ) . '</span>';
			}
			if ( in_array( 'miniapp', $enabled_items, true ) && '' !== $cfg['miniapp_number'] ) {
				$secondary_line[] = '<span class="wpicp-bar__item">' . esc_html__( '小程序备案：', 'wpicp' ) . esc_html( (string) $cfg['miniapp_number'] ) . '</span>';
			}
			if ( in_array( 'custom_licenses', $enabled_items, true ) && ! empty( $cfg['custom_licenses'] ) ) {
				foreach ( (array) $cfg['custom_licenses'] as $lic ) {
					if ( ! is_array( $lic ) ) {
						continue;
					}
					$l_name = isset( $lic['label'] ) ? trim( (string) $lic['label'] ) : '';
					$l_num  = isset( $lic['number'] ) ? trim( (string) $lic['number'] ) : '';
					$l_url  = isset( $lic['url'] ) ? trim( (string) $lic['url'] ) : '';
					$text   = ( '' !== $l_name && '' !== $l_num ) ? ( $l_name . '：' . $l_num ) : ( $l_name . $l_num );
					if ( '' === $text ) {
						continue;
					}
					$inner            = '' !== $l_url
						? '<a class="wpicp-bar__link" href="' . esc_url( $l_url ) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html( $text ) . '</a>'
						: esc_html( $text );
					$secondary_line[] = '<span class="wpicp-bar__item">' . $inner . '</span>';
				}
			}
			if ( in_array( 'report', $enabled_items, true ) ) {
				$report_str = self::format_report_inline( $cfg );
				if ( '' !== $report_str ) {
					$secondary_line[] = '<span class="wpicp-bar__item">' . $report_str . '</span>';
				}
			}
		}

		$badges_html = '';
		if ( 'badges' === $preset ) {
			$badges_html = self::render_badges_list(
				array(
					'align' => 'left' === $align ? 'left' : 'center',
				),
				$cfg
			);
		}

		if ( empty( $primary_line ) && empty( $secondary_line ) && '' === $badges_html ) {
			return '';
		}

		if ( empty( $args['skip_enqueue'] ) ) {
			self::enqueue_assets();
		}

		$classes = array(
			'wpicp-bar',
			'wpicp-bar--' . $preset,
			'wpicp-bar--align-' . $align,
		);
		if ( 'space' === $separator ) {
			$classes[] = 'wpicp-bar--sep-space';
		} elseif ( '·' === $separator ) {
			$classes[] = 'wpicp-bar--sep-dot';
		} else {
			$classes[] = 'wpicp-bar--sep-pipe';
		}
		if ( 'dark' === $theme || 'light' === $theme ) {
			$classes[] = 'wpicp-bar--theme-' . $theme;
		}
		if ( '' !== $cfg['custom_css_class'] ) {
			$classes[] = $cfg['custom_css_class'];
		}

		$sep_html = 'space' === $separator
			? ''
			: '<span class="wpicp-bar__sep" aria-hidden="true">' . esc_html( $separator ) . '</span>';

		$out = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" role="contentinfo" aria-label="' . esc_attr__( '网站备案与合规公示信息', 'wpicp' ) . '">';
		if ( ! empty( $primary_line ) ) {
			$out .= '<div class="wpicp-bar__line">' . implode( $sep_html, $primary_line ) . '</div>';
		}
		if ( ! empty( $secondary_line ) ) {
			$out .= '<div class="wpicp-bar__line wpicp-bar__line--sub">' . implode( $sep_html, $secondary_line ) . '</div>';
		}
		if ( '' !== $badges_html ) {
			$out .= '<div class="wpicp-bar__badges">' . $badges_html . '</div>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Format report email and phone into escaped inline HTML.
	 *
	 * @param array<string, mixed> $cfg Normalized settings.
	 * @return string
	 */
	private static function format_report_inline( array $cfg ) {
		$parts = array();
		if ( '' !== $cfg['report_email'] ) {
			$parts[] = esc_html__( '举报邮箱：', 'wpicp' ) . '<a class="wpicp-bar__link" href="mailto:' . esc_attr( (string) $cfg['report_email'] ) . '">' . esc_html( (string) $cfg['report_email'] ) . '</a>';
		}
		if ( '' !== $cfg['report_phone'] ) {
			$parts[] = esc_html__( '举报电话：', 'wpicp' ) . esc_html( (string) $cfg['report_phone'] );
		}
		return implode( ' ', $parts );
	}

	/**
	 * Filter an indexed array by optional `id` or comma-separated `ids` shortcode attributes.
	 *
	 * @param array<int, mixed> $items Indexed items.
	 * @param string            $id    Single index string.
	 * @param string            $ids   Comma-separated indices string.
	 * @return array<int, mixed>
	 */
	private static function filter_indexed_items( array $items, $id, $ids ) {
		if ( '' !== $id ) {
			$idx = (int) $id;
			return isset( $items[ $idx ] ) ? array( $items[ $idx ] ) : array();
		}
		if ( '' !== $ids ) {
			$wanted = array_map( 'intval', explode( ',', $ids ) );
			$out    = array();
			foreach ( $items as $idx => $item ) {
				if ( in_array( (int) $idx, $wanted, true ) ) {
					$out[] = $item;
				}
			}
			return $out;
		}
		return $items;
	}
}
