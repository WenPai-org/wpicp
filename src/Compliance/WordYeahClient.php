<?php
/**
 * WordYeah (无言) remote Advertising Law & compliance ruleset client.
 *
 * Performs one-way read-only HTTPS JSON fetches from `api.wpcy.com` with ETag
 * caching and seamless fallback to the bundled offline ruleset. Never uploads
 * site post content to external servers.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Compliance;

use WenPai\ICP\Options\SettingsStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs and caches WordYeah remote rulesets under `wpicp_wordyeah_rules`.
 */
class WordYeahClient {

	const CACHE_OPTION = 'wpicp_wordyeah_rules';
	const CRON_HOOK    = 'wpicp_wordyeah_sync';
	const ENDPOINT_URL = 'https://api.wpcy.com/wordyeah/v1/ad-law-rules.json';
	const BUILTIN_VER  = 'builtin-4.0';

	/**
	 * Register WP-Cron hook for periodic remote ruleset sync.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_sync' ) );
		add_action( 'update_option_' . SettingsStore::OPTION_SITE, array( __CLASS__, 'sync_schedule' ), 20, 0 );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ), 20 );
	}

	/**
	 * Resolve endpoint URL (filterable for self-hosted / staging mirrors).
	 *
	 * @return string
	 */
	public static function endpoint_url() {
		$url = self::ENDPOINT_URL;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpicp_wordyeah_rules_url', $url );
			if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
				$url = trim( $filtered );
			}
		}
		return $url;
	}

	/**
	 * Ensure scheduled sync exists when remote sync is enabled.
	 *
	 * @return void
	 */
	public static function ensure_schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		$cfg = SettingsStore::effective();
		if ( ! SettingsStore::module_enabled( 'module_adlaw', $cfg )
			|| empty( $cfg['ad_law_enabled'] )
			|| empty( $cfg['ad_law_remote_sync'] )
			|| 'manual' === (string) $cfg['ad_law_sync_interval']
		) {
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$rec = 'daily' === (string) $cfg['ad_law_sync_interval'] ? 'daily' : 'weekly';
			wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), $rec, self::CRON_HOOK );
		}
	}

	/**
	 * Reconcile sync schedule after settings change.
	 *
	 * @return void
	 */
	public static function sync_schedule() {
		if ( ! function_exists( 'wp_clear_scheduled_hook' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
		self::ensure_schedule();
	}

	/**
	 * WP-Cron callback.
	 *
	 * @return void
	 */
	public static function cron_sync() {
		$cfg = SettingsStore::effective();
		if ( SettingsStore::module_enabled( 'module_adlaw', $cfg ) && ! empty( $cfg['ad_law_enabled'] ) && ! empty( $cfg['ad_law_remote_sync'] ) ) {
			self::sync_now( false );
		}
	}

	/**
	 * Read cached WordYeah ruleset metadata and payloads.
	 *
	 * @return array{version:string, synced_at:string, etag:string, terms:array<string, array{category:string, suggestions:string[]}>, safe_compounds:string[], source:string}
	 */
	public static function cached() {
		$raw = function_exists( 'get_option' ) ? get_option( self::CACHE_OPTION, array() ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array(
			'version'        => isset( $raw['version'] ) ? (string) $raw['version'] : '',
			'synced_at'      => isset( $raw['synced_at'] ) ? (string) $raw['synced_at'] : '',
			'etag'           => isset( $raw['etag'] ) ? (string) $raw['etag'] : '',
			'terms'          => isset( $raw['terms'] ) && is_array( $raw['terms'] ) ? $raw['terms'] : array(),
			'safe_compounds' => isset( $raw['safe_compounds'] ) && is_array( $raw['safe_compounds'] ) ? $raw['safe_compounds'] : array(),
			'source'         => isset( $raw['source'] ) ? (string) $raw['source'] : 'builtin',
		);
	}

	/**
	 * Fetch latest rules JSON from `api.wpcy.com` (ETag-aware, safe fallback).
	 *
	 * @param bool $force Bypass ETag conditional check when true.
	 * @return array{ok:bool, status:string, version:string, remote_count:int, message:string}
	 */
	public static function sync_now( $force = false ) {
		$cached  = self::cached();
		$url     = self::endpoint_url();
		$now_str = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i' ) : gmdate( 'Y-m-d H:i' );

		if ( ! function_exists( 'wp_remote_get' ) ) {
			return array(
				'ok'           => false,
				'status'       => 'offline',
				'version'      => '' !== $cached['version'] ? $cached['version'] : self::BUILTIN_VER,
				'remote_count' => count( $cached['terms'] ),
				'message'      => __( '当前运行环境不支持 HTTP 同步，已自动使用内置基础词库。', 'wpicp' ),
			);
		}

		$headers = array(
			'Accept' => 'application/json',
		);
		if ( ! $force && '' !== $cached['etag'] ) {
			$headers['If-None-Match'] = $cached['etag'];
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'headers'    => $headers,
				'user-agent' => 'WPICP/' . ( defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0' ) . '; WordYeah-Client',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'           => false,
				'status'       => 'fallback',
				'version'      => '' !== $cached['version'] ? $cached['version'] : self::BUILTIN_VER,
				'remote_count' => count( $cached['terms'] ),
				'message'      => __( '暂无法连接无言（WordYeah）词库接口，已继续使用本地缓存/内置词库。', 'wpicp' ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 304 === $code ) {
			$cached['synced_at'] = $now_str;
			update_option( self::CACHE_OPTION, $cached, false );
			return array(
				'ok'           => true,
				'status'       => 'not-modified',
				'version'      => '' !== $cached['version'] ? $cached['version'] : self::BUILTIN_VER,
				'remote_count' => count( $cached['terms'] ),
				'message'      => __( '本地无言（WordYeah）词库已是最新版本，无需重复下载。', 'wpicp' ),
			);
		}

		if ( 200 !== $code ) {
			return array(
				'ok'           => false,
				'status'       => 'fallback',
				'version'      => '' !== $cached['version'] ? $cached['version'] : self::BUILTIN_VER,
				'remote_count' => count( $cached['terms'] ),
				'message'      => sprintf(
					/* translators: %d: HTTP status code */
					__( '远程词库接口返回 HTTP %d，已自动回退至本地词库。', 'wpicp' ),
					$code
				),
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$parsed  = self::sanitize_payload( is_array( $decoded ) ? $decoded : array() );
		if ( empty( $parsed['terms'] ) ) {
			return array(
				'ok'           => false,
				'status'       => 'invalid',
				'version'      => '' !== $cached['version'] ? $cached['version'] : self::BUILTIN_VER,
				'remote_count' => count( $cached['terms'] ),
				'message'      => __( '远程词库返回格式无效，已保留当前本地词库。', 'wpicp' ),
			);
		}

		$etag  = (string) wp_remote_retrieve_header( $response, 'etag' );
		$store = array(
			'version'        => $parsed['version'],
			'synced_at'      => $now_str,
			'etag'           => sanitize_text_field( $etag ),
			'terms'          => $parsed['terms'],
			'safe_compounds' => $parsed['safe_compounds'],
			'source'         => 'wordyeah-remote',
		);
		update_option( self::CACHE_OPTION, $store, false );

		return array(
			'ok'           => true,
			'status'       => 'updated',
			'version'      => $store['version'],
			'remote_count' => count( $store['terms'] ),
			'message'      => sprintf(
				/* translators: 1: version string, 2: term count */
				__( '已从 api.wpcy.com 同步无言（WordYeah）词库 %1$s（共 %2$d 条远程规则）。', 'wpicp' ),
				$store['version'],
				count( $store['terms'] )
			),
		);
	}

	/**
	 * Validate and normalize a decoded WordYeah JSON payload.
	 *
	 * @param array<string, mixed> $payload Decoded JSON array.
	 * @return array{version:string, terms:array<string, array{category:string, suggestions:string[]}>, safe_compounds:string[]}
	 */
	public static function sanitize_payload( array $payload ) {
		$version = isset( $payload['version'] ) && is_scalar( $payload['version'] )
			? sanitize_text_field( (string) $payload['version'] )
			: gmdate( 'Y.m' );

		$allowed_cats = array( 'absolute', 'unverified-promise', 'authority', 'regulated' );
		$terms        = array();
		if ( isset( $payload['terms'] ) && is_array( $payload['terms'] ) ) {
			foreach ( $payload['terms'] as $term => $meta ) {
				if ( ! is_string( $term ) || ! is_array( $meta ) ) {
					continue;
				}
				$clean_term = sanitize_text_field( trim( $term ) );
				if ( '' === $clean_term ) {
					continue;
				}
				$cat = isset( $meta['category'] ) ? sanitize_key( (string) $meta['category'] ) : 'absolute';
				if ( 'promise' === $cat ) {
					$cat = 'unverified-promise';
				}
				if ( ! in_array( $cat, $allowed_cats, true ) ) {
					$cat = 'absolute';
				}
				$sug = array();
				if ( isset( $meta['suggestions'] ) && is_array( $meta['suggestions'] ) ) {
					foreach ( $meta['suggestions'] as $s ) {
						if ( is_scalar( $s ) ) {
							$cs = sanitize_text_field( trim( (string) $s ) );
							if ( '' !== $cs ) {
								$sug[] = $cs;
							}
						}
					}
				}
				$terms[ $clean_term ] = array(
					'category'    => $cat,
					'suggestions' => array_slice( $sug, 0, 5 ),
				);
			}
		}

		$safe = array();
		if ( isset( $payload['safe_compounds'] ) && is_array( $payload['safe_compounds'] ) ) {
			foreach ( $payload['safe_compounds'] as $sc ) {
				if ( is_scalar( $sc ) ) {
					$clean_sc = sanitize_text_field( trim( (string) $sc ) );
					if ( '' !== $clean_sc ) {
						$safe[] = $clean_sc;
					}
				}
			}
		}

		return array(
			'version'        => $version,
			'terms'          => $terms,
			'safe_compounds' => array_values( array_unique( $safe ) ),
		);
	}
}
