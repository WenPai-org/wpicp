<?php
/**
 * WenPai Bridge (`updates.wenpai.net`) plugin update checker for WPICP.
 *
 * Cooperatively yields via `wpicp_updater_override` / `wenpai_updater_override`
 * when WP-China-Yes centralized updates are active.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP;

use stdClass;
use WenPai\ICP\Admin\AdminPage;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles `Update URI: https://updates.wenpai.net` update checks and plugin details modal.
 */
class Updater {

	const API_URL         = 'https://updates.wenpai.net/api/v1';
	const PLUGIN_HOME_URL = 'https://wpcy.com/icp';

	/**
	 * Plugin basename (`wpicp/wpicp.php`).
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin directory slug (`wpicp`).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Installed version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize updater hooks unless overridden by WPCY centralized bridge.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param string $version     Installed version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = (string) $plugin_file;
		$this->slug        = dirname( $this->plugin_file );
		$this->version     = (string) $version;

		if ( ! function_exists( 'apply_filters' ) || ! function_exists( 'add_filter' ) ) {
			return;
		}

		$is_overridden = (bool) apply_filters( 'wpicp_updater_override', false, $this->slug );
		if ( ! $is_overridden ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WenPai suite hook.
			$is_overridden = (bool) apply_filters( 'wenpai_updater_override', false, $this->slug );
		}

		if ( ! $is_overridden ) {
			add_filter( 'update_plugins_updates.wenpai.net', array( $this, 'check_update' ), 10, 4 );
			add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		}
	}

	/**
	 * Check WenPai Bridge for plugin updates.
	 *
	 * @param array<string, mixed>|false $update      Existing update payload.
	 * @param array<string, mixed>       $plugin_data Plugin header data.
	 * @param string                     $plugin_file Plugin basename.
	 * @param string[]                   $locales     Installed locales.
	 * @return object|false
	 */
	public function check_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );
		if ( $plugin_file !== $this->plugin_file ) {
			return $update;
		}

		$response = $this->api_request(
			'update-check',
			array(
				'plugins' => array(
					$this->plugin_file => array(
						'Version' => $this->version,
					),
				),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['plugins'][ $this->plugin_file ] ) ) {
			return $update;
		}

		$data  = $response['plugins'][ $this->plugin_file ];
		$icons = isset( $data['icons'] ) && is_array( $data['icons'] ) ? $data['icons'] : array();
		unset( $icons['svg'] );

		return (object) array(
			'id'           => isset( $data['id'] ) ? (string) $data['id'] : '',
			'slug'         => isset( $data['slug'] ) ? (string) $data['slug'] : $this->slug,
			'plugin'       => $this->plugin_file,
			'version'      => isset( $data['version'] ) ? (string) $data['version'] : '',
			'new_version'  => isset( $data['version'] ) ? (string) $data['version'] : '',
			'url'          => self::PLUGIN_HOME_URL,
			'package'      => isset( $data['package'] ) ? (string) $data['package'] : '',
			'icons'        => $icons,
			'banners'      => isset( $data['banners'] ) && is_array( $data['banners'] ) ? $data['banners'] : array(),
			'requires'     => isset( $data['requires'] ) ? (string) $data['requires'] : '',
			'tested'       => isset( $data['tested'] ) ? (string) $data['tested'] : '',
			'requires_php' => isset( $data['requires_php'] ) ? (string) $data['requires_php'] : '',
		);
	}

	/**
	 * Provide plugin details modal data (`plugins_api`).
	 *
	 * @param false|object|array<string, mixed> $result Current result.
	 * @param string                            $action API action.
	 * @param object                            $args   Request args.
	 * @return false|object|array<string, mixed>
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$display = AdminPage::display_meta();
		$info    = new stdClass();

		$response = $this->api_request( 'plugins/' . $this->slug . '/info' );
		if ( ! is_wp_error( $response ) && ! empty( $response['name'] ) ) {
			$icons = isset( $response['icons'] ) && is_array( $response['icons'] ) ? $response['icons'] : array();
			unset( $icons['svg'] );

			$info->name          = $display['brand'];
			$info->slug          = $this->slug;
			$info->version       = isset( $response['version'] ) ? (string) $response['version'] : $this->version;
			$info->author        = 'WenPai';
			$info->homepage      = $display['help_url'];
			$info->download_link = isset( $response['download_link'] ) ? (string) $response['download_link'] : '';
			$info->requires      = isset( $response['requires'] ) ? (string) $response['requires'] : '6.2';
			$info->tested        = isset( $response['tested'] ) ? (string) $response['tested'] : '6.8';
			$info->requires_php  = isset( $response['requires_php'] ) ? (string) $response['requires_php'] : '7.4';
			$info->icons         = $icons;
			$info->banners       = isset( $response['banners'] ) && is_array( $response['banners'] ) ? $response['banners'] : array();
			$info->sections      = isset( $response['sections'] ) && is_array( $response['sections'] )
				? array_map( 'wp_kses_post', $response['sections'] )
				: array();
			$info->external      = true;
			return $info;
		}

		$info->name         = $display['brand'];
		$info->slug         = $this->slug;
		$info->version      = $this->version;
		$info->author       = 'WenPai';
		$info->homepage     = $display['help_url'];
		$info->requires     = '6.2';
		$info->requires_php = '7.4';
		$info->sections     = array(
			'description' => __( '中国站点一站式备案资质公示、电子证照信任徽章与离线《广告法》内容合规巡检中枢。', 'wpicp' ),
		);
		$info->external     = true;
		return $info;
	}

	/**
	 * Send JSON request to WenPai Bridge API.
	 *
	 * @param string                    $endpoint Relative endpoint.
	 * @param array<string, mixed>|null $body     Optional POST payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private function api_request( $endpoint, ?array $body = null ) {
		$url  = self::API_URL . '/' . ltrim( (string) $endpoint, '/' );
		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
			$response                        = wp_remote_post( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'wenpai_bridge_http_error', 'HTTP ' . $code );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : new WP_Error( 'wenpai_bridge_json_error', 'Invalid JSON' );
	}
}
