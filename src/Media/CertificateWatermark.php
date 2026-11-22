<?php
/**
 * Server-side GD certificate watermark synthesis, protected cache management,
 * and template variable interpolation for WPICP 4.0 electronic certificates.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Media;

use WenPai\ICP\Options\SettingsStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates watermarked certificate copies on demand via REST route `/wpicp/v1/certificate/{id}`.
 */
class CertificateWatermark {

	const NAMESPACE_V1 = 'wpicp/v1';

	/**
	 * Register REST output endpoint.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register public read-only watermarked image endpoint (only for registered certificate IDs).
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/certificate/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'serve_certificate_image' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Interpolate `{site_name}`, `{domain}`, `{date}` in watermark text template.
	 *
	 * @param string                    $template Raw template string.
	 * @param array<string, mixed>|null $settings Optional settings snapshot.
	 * @return string
	 */
	public static function resolve_text( $template = '', ?array $settings = null ) {
		$cfg  = is_array( $settings ) ? $settings : SettingsStore::effective();
		$text = trim( (string) $template );
		if ( '' === $text ) {
			$text = isset( $cfg['cert_watermark_text'] ) ? trim( (string) $cfg['cert_watermark_text'] ) : '';
		}
		if ( '' === $text ) {
			$text = __( '仅供 {site_name} 官网亮照公示使用，他用无效', 'wpicp' );
		}

		$site_name = '' !== trim( (string) ( $cfg['icp_site_name'] ?? '' ) )
			? trim( (string) $cfg['icp_site_name'] )
			: ( function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '官网' );
		$home      = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$domain    = (string) wp_parse_url( $home, PHP_URL_HOST );
		$date      = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );

		return str_replace(
			array( '{site_name}', '{domain}', '{date}' ),
			array( $site_name, '' !== $domain ? $domain : 'example.com', $date ),
			$text
		);
	}

	/**
	 * Build display URL for a certificate attachment (uses server watermark route when enabled & GD ready).
	 *
	 * @param int                       $attachment_id Attachment ID.
	 * @param string                    $size          WordPress image size.
	 * @param array<string, mixed>|null $settings      Optional settings snapshot.
	 * @return string
	 */
	public static function image_url( $attachment_id, $size = 'full', ?array $settings = null ) {
		$id  = absint( $attachment_id );
		$cfg = is_array( $settings ) ? $settings : SettingsStore::effective();
		if ( $id <= 0 ) {
			return '';
		}

		if ( ! empty( $cfg['cert_watermark_enabled'] ) && 'server' === (string) ( $cfg['cert_watermark_engine'] ?? 'server' ) && function_exists( 'rest_url' ) && self::gd_available() ) {
			$ver = substr( md5( self::settings_fingerprint( $cfg ) . '|' . $size ), 0, 8 );
			return add_query_arg(
				array(
					'size' => sanitize_key( (string) $size ),
					'v'    => $ver,
				),
				rest_url( self::NAMESPACE_V1 . '/certificate/' . $id )
			);
		}

		if ( function_exists( 'wp_get_attachment_image_url' ) ) {
			return (string) wp_get_attachment_image_url( $id, $size );
		}

		return '';
	}

	/**
	 * Whether PHP GD extension is available for server-side watermark synthesis.
	 *
	 * @return bool
	 */
	public static function gd_available() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' );
	}

	/**
	 * Compute deterministic cache fingerprint for watermark settings.
	 *
	 * @param array<string, mixed> $cfg Normalized settings.
	 * @return string
	 */
	public static function settings_fingerprint( array $cfg ) {
		return implode(
			'|',
			array(
				(string) ( $cfg['cert_watermark_text'] ?? '' ),
				(string) ( $cfg['cert_watermark_layout'] ?? 'tile' ),
				(string) ( $cfg['cert_watermark_tone'] ?? 'standard' ),
			)
		);
	}

	/**
	 * Serve cached or freshly synthesized watermarked PNG/JPEG for a registered certificate attachment.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|void
	 */
	public static function serve_certificate_image( WP_REST_Request $request ) {
		$id  = absint( $request->get_param( 'id' ) );
		$cfg = SettingsStore::effective();
		if ( ! SettingsStore::module_enabled( 'module_badges', $cfg ) ) {
			return new WP_Error( 'wpicp_module_disabled', __( '证照与徽章模块尚未开启。', 'wpicp' ), array( 'status' => 404 ) );
		}

		// Restrict serving strictly to attachment IDs registered in `certificates`.
		$allowed = false;
		if ( isset( $cfg['certificates'] ) && is_array( $cfg['certificates'] ) ) {
			foreach ( $cfg['certificates'] as $cert ) {
				if ( is_array( $cert ) && isset( $cert['image_id'] ) && absint( $cert['image_id'] ) === $id ) {
					$allowed = true;
					break;
				}
			}
		}
		if ( ! $allowed ) {
			return new WP_Error( 'wpicp_cert_forbidden', __( '未授权的证照图片请求。', 'wpicp' ), array( 'status' => 404 ) );
		}

		$src_path = function_exists( 'get_attached_file' ) ? (string) get_attached_file( $id ) : '';
		if ( '' === $src_path || ! is_readable( $src_path ) ) {
			return new WP_Error( 'wpicp_cert_missing', __( '证照原图文件不存在。', 'wpicp' ), array( 'status' => 404 ) );
		}

		$cached = self::build_watermarked_cache( $src_path, $id, $cfg );
		$target = ( '' !== $cached && is_readable( $cached ) ) ? $cached : $src_path;
		$mime   = function_exists( 'wp_check_filetype' ) ? (string) ( wp_check_filetype( $target )['type'] ?? 'image/png' ) : 'image/png';
		if ( '' === $mime ) {
			$mime = 'image/png';
		}

		header( 'Content-Type: ' . $mime );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming binary image response.
		readfile( $target );
		exit;
	}

	/**
	 * Synthesize and cache a watermarked PNG copy using PHP GD (graceful fallback if unsupported).
	 *
	 * @param string               $src_path Absolute path to original attachment.
	 * @param int                  $id       Attachment ID.
	 * @param array<string, mixed> $cfg      Normalized settings.
	 * @return string Cached file path, or '' on fallback.
	 */
	public static function build_watermarked_cache( $src_path, $id, array $cfg ) {
		if ( ! self::gd_available() || ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}

		$upload    = wp_upload_dir();
		$cache_dir = trailingslashit( (string) $upload['basedir'] ) . 'wpicp-watermark-cache';
		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
			// Guard directory listing.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- lightweight guard file.
			@file_put_contents( $cache_dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}

		$hash       = substr( md5( self::settings_fingerprint( $cfg ) . '|' . filemtime( $src_path ) ), 0, 12 );
		$cache_file = $cache_dir . '/cert-' . absint( $id ) . '-' . $hash . '.png';
		if ( is_readable( $cache_file ) ) {
			return $cache_file;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- local image read.
		$raw = @file_get_contents( $src_path );
		if ( false === $raw ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$img = @imagecreatefromstring( $raw );
		if ( ! $img ) {
			return '';
		}

		imagealphablending( $img, true );
		imagesavealpha( $img, true );

		$w      = imagesx( $img );
		$h      = imagesy( $img );
		$tone   = (string) ( $cfg['cert_watermark_tone'] ?? 'standard' );
		$layout = (string) ( $cfg['cert_watermark_layout'] ?? 'tile' );

		$alpha = 96;
		$r     = 40;
		$g     = 46;
		$b     = 58;
		if ( 'light' === $tone ) {
			$alpha = 108;
		} elseif ( 'strong' === $tone ) {
			$alpha = 74;
		} elseif ( 'red' === $tone ) {
			$r     = 198;
			$g     = 40;
			$b     = 40;
			$alpha = 84;
		}

		$color = imagecolorallocatealpha( $img, $r, $g, $b, $alpha );
		$text  = self::resolve_text( '', $cfg );
		$font  = self::find_ttf_font();

		if ( '' !== $font && function_exists( 'imagettftext' ) ) {
			$size = max( 12, (int) round( min( $w, $h ) * 0.036 ) );
			if ( 'corner' === $layout ) {
				imagettftext( $img, $size, 0, max( 12, (int) ( $w * 0.08 ) ), max( 24, $h - 20 ), $color, $font, $text );
			} elseif ( 'center' === $layout ) {
				imagettftext( $img, (int) round( $size * 1.25 ), 18, max( 16, (int) ( $w * 0.12 ) ), (int) round( $h * 0.56 ), $color, $font, $text );
			} else {
				$step_x = max( 220, (int) round( $w * 0.45 ) );
				$step_y = max( 120, (int) round( $h * 0.28 ) );
				for ( $y = $step_y / 2; $y < $h + $step_y; $y += $step_y ) {
					for ( $x = -40; $x < $w + $step_x; $x += $step_x ) {
						imagettftext( $img, $size, 22, (int) $x, (int) $y, $color, $font, $text );
					}
				}
			}
		} else {
			// Subtle diagonal anti-theft crosshatch lines when no CJK TTF font exists on host.
			$step = max( 48, (int) round( min( $w, $h ) * 0.14 ) );
			for ( $offset = -$h; $offset < $w + $h; $offset += $step ) {
				imageline( $img, $offset, 0, $offset + $h, $h, $color );
			}
			$ascii = 'OFFICIAL FILING ONLY - ' . strtoupper( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
			imagestring( $img, 4, max( 12, (int) ( $w * 0.1 ) ), max( 12, (int) ( $h * 0.5 ) ), $ascii, $color );
		}

		$saved = imagepng( $img, $cache_file, 6 );
		unset( $img );

		return $saved ? $cache_file : '';
	}

	/**
	 * Locate an available CJK TrueType font on Linux/macOS hosts (filterable).
	 *
	 * @return string
	 */
	private static function find_ttf_font() {
		$candidates = array(
			'/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
			'/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
			'/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
			'/System/Library/Fonts/PingFang.ttc',
			'/System/Library/Fonts/STHeiti Light.ttc',
		);
		if ( function_exists( 'apply_filters' ) ) {
			$candidates = (array) apply_filters( 'wpicp_watermark_font_candidates', $candidates );
		}
		foreach ( $candidates as $path ) {
			if ( is_string( $path ) && '' !== $path && is_readable( $path ) ) {
				return $path;
			}
		}
		return '';
	}
}
