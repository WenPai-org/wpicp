<?php
/**
 * Homepage ICP filing self-check, cloud provider DNS/IP compliance check,
 * daily/weekly keepalive subdomain probe, and standalone keepalive HTML exporter.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Compliance;

use WenPai\ICP\Options\BadgeCatalog;
use WenPai\ICP\Options\SettingsStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Monitors homepage compliance and cloud filing provider access consistency.
 */
class AccessMonitor {

	const STATUS_OPTION     = 'wpicp_access_status';
	const LOGS_OPTION       = 'wpicp_access_logs';
	const FAIL_COUNT_OPTION = 'wpicp_access_fail_count';
	const CRON_HOME_HOOK    = 'wpicp_daily_home_check';
	const CRON_PROBE_HOOK   = 'wpicp_keepalive_probe';

	/**
	 * Block page phrases that must never count as a healthy keepalive response.
	 *
	 * @var string[]
	 */
	const BLOCK_PHRASES = array(
		'未备案',
		'取消接入',
		'阻断访问',
		'接入阻断',
		'网站暂时无法访问',
		'not filed',
	);

	/**
	 * Register WP-Cron intervals, hooks, and scheduled events.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! function_exists( 'add_filter' ) || ! function_exists( 'add_action' ) ) {
			return;
		}
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedules' ) );
		add_action( self::CRON_HOME_HOOK, array( __CLASS__, 'cron_run_home_check' ) );
		add_action( self::CRON_PROBE_HOOK, array( __CLASS__, 'cron_run_keepalive_probe' ) );
		add_action( 'update_option_' . SettingsStore::OPTION_SITE, array( __CLASS__, 'sync_schedules' ), 20, 0 );
		add_action( 'init', array( __CLASS__, 'ensure_schedules' ), 20 );
	}

	/**
	 * Register custom low-frequency day/week WP-Cron intervals.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules['wpicp_3d']  = array(
			'interval' => 3 * DAY_IN_SECONDS,
			'display'  => __( '每 3 天 1 次', 'wpicp' ),
		);
		$schedules['wpicp_7d']  = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display'  => __( '每周 1 次', 'wpicp' ),
		);
		$schedules['wpicp_14d'] = array(
			'interval' => 14 * DAY_IN_SECONDS,
			'display'  => __( '每 2 周 1 次', 'wpicp' ),
		);
		return $schedules;
	}

	/**
	 * Map setting interval key (`1d|3d|7d|14d`) to WP-Cron schedule slug.
	 *
	 * @param string $interval Setting interval key.
	 * @return string
	 */
	public static function cron_recurrence_for( $interval ) {
		switch ( (string) $interval ) {
			case '1d':
				return 'daily';
			case '7d':
				return 'wpicp_7d';
			case '14d':
				return 'wpicp_14d';
			case '3d':
			default:
				return 'wpicp_3d';
		}
	}

	/**
	 * Human-readable label for a keepalive interval.
	 *
	 * @param string $interval Interval key.
	 * @return string
	 */
	public static function interval_label( $interval ) {
		$map = array(
			'1d'  => __( '每天 1 次', 'wpicp' ),
			'3d'  => __( '每 3 天 1 次', 'wpicp' ),
			'7d'  => __( '每周 1 次', 'wpicp' ),
			'14d' => __( '每 2 周 1 次', 'wpicp' ),
		);
		return isset( $map[ $interval ] ) ? $map[ $interval ] : $map['3d'];
	}

	/**
	 * Human-readable label for a filing provider slug.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public static function provider_label( $provider ) {
		$map = self::provider_choices();
		return isset( $map[ $provider ] ) ? $map[ $provider ] : __( '境内接入商', 'wpicp' );
	}

	/**
	 * Supported Chinese mainland filing cloud provider choices.
	 *
	 * @return array<string, string>
	 */
	public static function provider_choices() {
		return array(
			'aliyun'  => __( '阿里云（Aliyun）', 'wpicp' ),
			'tencent' => __( '腾讯云（Tencent Cloud）', 'wpicp' ),
			'huawei'  => __( '华为云（Huawei Cloud）', 'wpicp' ),
			'baidu'   => __( '百度智能云', 'wpicp' ),
			'volc'    => __( '火山引擎', 'wpicp' ),
			'other'   => __( '其他境内接入商', 'wpicp' ),
		);
	}

	/**
	 * Ensure scheduled events exist when enabled (idempotent on `init`).
	 *
	 * @return void
	 */
	public static function ensure_schedules() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		$cfg = SettingsStore::effective();
		if ( ! SettingsStore::module_enabled( 'module_access', $cfg ) ) {
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::CRON_HOME_HOOK );
				wp_clear_scheduled_hook( self::CRON_PROBE_HOOK );
			}
			return;
		}
		if ( ! empty( $cfg['home_check_enabled'] ) && ! wp_next_scheduled( self::CRON_HOME_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOME_HOOK );
		}
		if ( ! empty( $cfg['keepalive_enabled'] ) && '' !== trim( (string) $cfg['keepalive_url'] ) && ! wp_next_scheduled( self::CRON_PROBE_HOOK ) ) {
			$rec = self::cron_recurrence_for( (string) $cfg['keepalive_interval'] );
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), $rec, self::CRON_PROBE_HOOK );
		}
	}

	/**
	 * Reconcile WP-Cron schedules immediately after settings are saved.
	 *
	 * @return void
	 */
	public static function sync_schedules() {
		if ( ! function_exists( 'wp_clear_scheduled_hook' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		wp_clear_scheduled_hook( self::CRON_HOME_HOOK );
		wp_clear_scheduled_hook( self::CRON_PROBE_HOOK );
		$cfg = SettingsStore::effective();
		if ( ! SettingsStore::module_enabled( 'module_access', $cfg ) ) {
			return;
		}
		if ( ! empty( $cfg['home_check_enabled'] ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOME_HOOK );
		}
		if ( ! empty( $cfg['keepalive_enabled'] ) && '' !== trim( (string) $cfg['keepalive_url'] ) ) {
			$rec = self::cron_recurrence_for( (string) $cfg['keepalive_interval'] );
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), $rec, self::CRON_PROBE_HOOK );
		}
	}

	/**
	 * WP-Cron callback for daily homepage check.
	 *
	 * @return void
	 */
	public static function cron_run_home_check() {
		$cfg = SettingsStore::effective();
		if ( SettingsStore::module_enabled( 'module_access', $cfg ) && ! empty( $cfg['home_check_enabled'] ) ) {
			self::run_all_checks( $cfg, true, false );
		}
	}

	/**
	 * WP-Cron callback for low-frequency keepalive subdomain probe.
	 *
	 * @return void
	 */
	public static function cron_run_keepalive_probe() {
		$cfg = SettingsStore::effective();
		if ( SettingsStore::module_enabled( 'module_access', $cfg ) && ! empty( $cfg['keepalive_enabled'] ) && '' !== trim( (string) $cfg['keepalive_url'] ) ) {
			self::run_all_checks( $cfg, false, true );
		}
	}

	/**
	 * Read last saved status snapshot (safe empty defaults if never executed).
	 *
	 * @return array<string, mixed>
	 */
	public static function last_status() {
		$saved = function_exists( 'get_option' ) ? get_option( self::STATUS_OPTION, array() ) : array();
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Read rolling keepalive probe log entries (most recent first, max 10).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function recent_logs() {
		$logs = function_exists( 'get_option' ) ? get_option( self::LOGS_OPTION, array() ) : array();
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Execute homepage check and/or keepalive probe, persist snapshot & logs, and fire throttled alerts.
	 *
	 * @param array<string, mixed>|null $settings      Optional settings override.
	 * @param bool                      $run_home      Whether to inspect homepage HTML.
	 * @param bool                      $run_keepalive Whether to probe keepalive subdomain.
	 * @return array<string, mixed>
	 */
	public static function run_all_checks( ?array $settings = null, $run_home = true, $run_keepalive = true ) {
		$cfg      = is_array( $settings ) ? SettingsStore::normalize( $settings ) : SettingsStore::effective();
		$prev     = self::last_status();
		$now_str  = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i' ) : gmdate( 'Y-m-d H:i' );
		$snapshot = array_merge(
			array(
				'checked_at' => $now_str,
				'home'       => isset( $prev['home'] ) && is_array( $prev['home'] ) ? $prev['home'] : array(),
				'keepalive'  => isset( $prev['keepalive'] ) && is_array( $prev['keepalive'] ) ? $prev['keepalive'] : array(),
			),
			array( 'checked_at' => $now_str )
		);

		$issues = array();

		if ( $run_home ) {
			$home_url         = function_exists( 'home_url' ) ? home_url( '/' ) : '';
			$snapshot['home'] = self::check_homepage_url( $home_url, $cfg );
			if ( empty( $snapshot['home']['ok'] ) ) {
				$issues[] = isset( $snapshot['home']['summary'] ) ? (string) $snapshot['home']['summary'] : __( '首页备案自检存在异常', 'wpicp' );
			}
		}

		if ( $run_keepalive && ! empty( $cfg['keepalive_enabled'] ) && '' !== trim( (string) $cfg['keepalive_url'] ) ) {
			$probe                 = self::probe_keepalive_url( (string) $cfg['keepalive_url'], $cfg );
			$snapshot['keepalive'] = $probe;
			self::append_log( $probe );
			if ( empty( $probe['ok'] ) ) {
				$issues[] = isset( $probe['summary'] ) ? (string) $probe['summary'] : __( '保活子域名检测未通过', 'wpicp' );
			}
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::STATUS_OPTION, $snapshot, false );
			self::handle_alert_threshold( $issues, $cfg );
		}

		return $snapshot;
	}

	/**
	 * Pure-function inspection of homepage HTML against MIIT compliance rules.
	 *
	 * @param string               $html       Raw HTML document.
	 * @param int                  $status     HTTP status code.
	 * @param int                  $latency_ms Elapsed milliseconds.
	 * @param array<string, mixed> $cfg        Normalized settings.
	 * @return array<string, mixed>
	 */
	public static function inspect_homepage_html( $html, $status, $latency_ms, array $cfg ) {
		$body      = (string) $html;
		$icp       = trim( (string) $cfg['icp_number'] );
		$site_name = trim( (string) $cfg['icp_site_name'] );
		$blocked   = self::contains_block_phrase( $body );

		$http_ok = ( 200 === (int) $status ) && ! $blocked && '' !== trim( $body );
		$icp_ok  = '' === $icp || false !== mb_strpos( $body, $icp );

		$has_official_link = (bool) preg_match( '#https?://beian\.miit\.gov\.cn/?#i', $body );
		$has_legacy_link   = (bool) preg_match( '#miitbeian\.gov\.cn#i', $body );
		$link_ok           = '' === $icp || ( $has_official_link && ! $has_legacy_link );

		$page_title = '';
		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $body, $m ) ) {
			$page_title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
		}
		$title_ok = '' === $site_name || ( '' !== $page_title && false !== mb_strpos( $page_title, $site_name ) );

		$checks = array(
			array(
				'id'     => 'http',
				'label'  => __( '首页 HTTP 可达性（无空壳/阻断页）', 'wpicp' ),
				'state'  => $http_ok ? 'ok' : 'bad',
				'value'  => $http_ok
					? sprintf( '200 OK · %dms', (int) $latency_ms )
					: ( $blocked ? __( '检测到疑似阻断页特征词', 'wpicp' ) : sprintf( 'HTTP %d', (int) $status ) ),
				'detail' => $http_ok ? '' : __( '请核查站点是否可公开访问且未出现云厂商阻断页。', 'wpicp' ),
			),
			array(
				'id'     => 'icp_present',
				'label'  => __( '首页底部 ICP 备案号悬挂', 'wpicp' ),
				'state'  => '' === $icp ? 'warn' : ( $icp_ok ? 'ok' : 'bad' ),
				'value'  => '' === $icp ? __( '未登记备案号', 'wpicp' ) : ( $icp_ok ? $icp : __( '首页未检出', 'wpicp' ) ),
				'detail' => $icp_ok ? '' : __( '请在「前台展示」开启自动页脚注入或在主题页脚插入 [wpicp]。', 'wpicp' ),
			),
			array(
				'id'     => 'miit_link',
				'label'  => __( '工信部官方超链接规范（无旧域名死链）', 'wpicp' ),
				'state'  => '' === $icp ? 'warn' : ( $link_ok ? 'ok' : 'bad' ),
				'value'  => $has_legacy_link
					? __( '存在已停用旧域名 miitbeian.gov.cn', 'wpicp' )
					: ( $has_official_link ? 'https://beian.miit.gov.cn/' : __( '缺少官方链接', 'wpicp' ) ),
				'detail' => $link_ok ? '' : __( '备案号须链接至 https://beian.miit.gov.cn/，不可使用旧域名。', 'wpicp' ),
			),
			array(
				'id'     => 'title_match',
				'label'  => __( '首页 <title> 与工信部登记名称一致性', 'wpicp' ),
				'state'  => '' === $site_name ? 'ok' : ( $title_ok ? 'ok' : 'warn' ),
				'value'  => '' === $site_name
					? __( '未指定核对名', 'wpicp' )
					: ( $title_ok
						? sprintf(
							/* translators: %s: registered site name */
							__( '含「%s」', 'wpicp' ),
							$site_name
						)
						: __( '标题未含备案名', 'wpicp' ) ),
				'detail' => $title_ok ? '' : __( '首页标题建议完整包含工信部登记的网站名称。', 'wpicp' ),
			),
		);

		$passed = 0;
		foreach ( $checks as $chk ) {
			if ( 'ok' === $chk['state'] ) {
				++$passed;
			}
		}

		$all_ok = $http_ok && $icp_ok && $link_ok && $title_ok;

		return array(
			'ok'         => $all_ok,
			'passed'     => $passed,
			'total'      => count( $checks ),
			'http_code'  => (int) $status,
			'latency_ms' => (int) $latency_ms,
			'checks'     => $checks,
			'summary'    => $all_ok
				? __( '首页 4 项备案规范检测全部通过。', 'wpicp' )
				: __( '首页备案公示或标题一致性存在待修正项。', 'wpicp' ),
		);
	}

	/**
	 * Fetch live homepage URL and run compliance inspection.
	 *
	 * @param string               $url Homepage URL.
	 * @param array<string, mixed> $cfg Normalized settings.
	 * @return array<string, mixed>
	 */
	public static function check_homepage_url( $url, array $cfg ) {
		if ( '' === trim( $url ) || ! function_exists( 'wp_remote_get' ) ) {
			return self::inspect_homepage_html( '', 0, 0, $cfg );
		}

		$start    = microtime( true );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'sslverify'   => false,
				'user-agent'  => 'WPICP-ComplianceCheck/' . ( defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0' ),
			)
		);
		$elapsed  = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return self::inspect_homepage_html( '', 0, $elapsed, $cfg );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		return self::inspect_homepage_html( $body, $code, $elapsed, $cfg );
	}

	/**
	 * Probe keepalive subdomain DNS IP match and HTTP 200 + keyword response.
	 *
	 * @param string               $url Keepalive subdomain URL.
	 * @param array<string, mixed> $cfg Normalized settings.
	 * @return array<string, mixed>
	 */
	public static function probe_keepalive_url( $url, array $cfg ) {
		$host       = (string) wp_parse_url( $url, PHP_URL_HOST );
		$resolved   = self::resolve_host_ips( $host );
		$expected   = self::parse_expected_ips( (string) $cfg['expected_provider_ips'] );
		$ip_matched = empty( $expected ) ? ! empty( $resolved ) : self::any_ip_matches( $resolved, $expected );
		$primary_ip = ! empty( $resolved ) ? $resolved[0] : __( '解析失败', 'wpicp' );
		$keyword    = trim( (string) $cfg['keepalive_keyword'] );
		if ( '' === $keyword ) {
			$keyword = trim( (string) $cfg['icp_number'] );
		}

		$http_code  = 0;
		$latency_ms = 0;
		$body       = '';
		if ( function_exists( 'wp_remote_get' ) ) {
			$start      = microtime( true );
			$response   = wp_remote_get(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 2,
					'user-agent'  => 'WordYeah-ComplianceProbe/' . ( defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0' ),
				)
			);
			$latency_ms = (int) round( ( microtime( true ) - $start ) * 1000 );
			if ( ! is_wp_error( $response ) ) {
				$http_code = (int) wp_remote_retrieve_response_code( $response );
				$body      = (string) wp_remote_retrieve_body( $response );
			}
		}

		$blocked    = self::contains_block_phrase( $body );
		$keyword_ok = '' === $keyword || false !== mb_strpos( $body, $keyword );
		$http_ok    = ( 200 === $http_code ) && ! $blocked && $keyword_ok;
		$overall_ok = $ip_matched && $http_ok;

		$result_label = __( '正常 · 含备案号', 'wpicp' );
		if ( ! $ip_matched ) {
			$result_label = __( 'DNS IP 漂移', 'wpicp' );
		} elseif ( $blocked ) {
			$result_label = __( '命中阻断特征词', 'wpicp' );
		} elseif ( 200 !== $http_code ) {
			$result_label = sprintf( 'HTTP %d', $http_code );
		} elseif ( ! $keyword_ok ) {
			$result_label = __( '未检出校验关键词', 'wpicp' );
		}

		$now_str = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i' ) : gmdate( 'Y-m-d H:i' );

		return array(
			'ok'           => $overall_ok,
			'time'         => $now_str,
			'url'          => $url,
			'host'         => $host,
			'resolved_ips' => $resolved,
			'primary_ip'   => $primary_ip,
			'ip_matched'   => $ip_matched,
			'http_code'    => $http_code,
			'latency_ms'   => $latency_ms,
			'keyword_ok'   => $keyword_ok,
			'blocked'      => $blocked,
			'result_label' => $result_label,
			'summary'      => $overall_ok
				? sprintf(
					/* translators: 1: host, 2: IP */
					__( '%1$s 解析至 %2$s 且保活访问 200 OK。', 'wpicp' ),
					$host,
					$primary_ip
				)
				: sprintf(
					/* translators: 1: host, 2: failure reason */
					__( '保活子域名 %1$s 异常：%2$s', 'wpicp' ),
					$host,
					$result_label
				),
		);
	}

	/**
	 * Resolve A / AAAA / CNAME records for a hostname to final IP strings.
	 *
	 * @param string $host Hostname.
	 * @return string[]
	 */
	public static function resolve_host_ips( $host ) {
		$host = trim( strtolower( (string) $host ) );
		if ( '' === $host ) {
			return array();
		}

		$ips = array();
		if ( function_exists( 'dns_get_record' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS lookups can emit warnings on offline hosts.
			$records = @dns_get_record( $host, DNS_A | DNS_AAAA | DNS_CNAME );
			if ( is_array( $records ) ) {
				foreach ( $records as $rec ) {
					if ( isset( $rec['ip'] ) && filter_var( $rec['ip'], FILTER_VALIDATE_IP ) ) {
						$ips[] = $rec['ip'];
					} elseif ( isset( $rec['ipv6'] ) && filter_var( $rec['ipv6'], FILTER_VALIDATE_IP ) ) {
						$ips[] = $rec['ipv6'];
					} elseif ( isset( $rec['target'] ) && '' !== (string) $rec['target'] && $rec['target'] !== $host ) {
						// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						$sub = @dns_get_record( (string) $rec['target'], DNS_A | DNS_AAAA );
						if ( is_array( $sub ) ) {
							foreach ( $sub as $srec ) {
								if ( isset( $srec['ip'] ) && filter_var( $srec['ip'], FILTER_VALIDATE_IP ) ) {
									$ips[] = $srec['ip'];
								} elseif ( isset( $srec['ipv6'] ) && filter_var( $srec['ipv6'], FILTER_VALIDATE_IP ) ) {
									$ips[] = $srec['ipv6'];
								}
							}
						}
					}
				}
			}
		}

		if ( empty( $ips ) && function_exists( 'gethostbynamel' ) ) {
			$fallback = gethostbynamel( $host );
			if ( is_array( $fallback ) ) {
				$ips = $fallback;
			}
		}

		return array_values( array_unique( $ips ) );
	}

	/**
	 * Parse comma/newline-separated expected IP / CIDR list.
	 *
	 * @param string $raw Raw input string.
	 * @return string[]
	 */
	public static function parse_expected_ips( $raw ) {
		$parts = preg_split( '/[\s,，;；]+/', trim( (string) $raw ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $item ) {
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Test whether any resolved IP matches at least one expected IP or CIDR range.
	 *
	 * @param string[] $resolved Resolved IPs.
	 * @param string[] $expected Expected IPs or CIDRs.
	 * @return bool
	 */
	public static function any_ip_matches( array $resolved, array $expected ) {
		foreach ( $resolved as $ip ) {
			foreach ( $expected as $rule ) {
				if ( self::ip_in_rule( $ip, $rule ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Match a single IPv4/IPv6 address against an exact IP or CIDR notation.
	 *
	 * @param string $ip   IP address.
	 * @param string $rule Exact IP or CIDR (e.g. `47.115.120.0/24`).
	 * @return bool
	 */
	public static function ip_in_rule( $ip, $rule ) {
		$ip   = trim( (string) $ip );
		$rule = trim( (string) $rule );
		if ( '' === $ip || '' === $rule ) {
			return false;
		}
		if ( false === strpos( $rule, '/' ) ) {
			return strtolower( $ip ) === strtolower( $rule );
		}

		list( $subnet, $prefix ) = array_pad( explode( '/', $rule, 2 ), 2, '' );
		if ( '' === $prefix || ! is_numeric( $prefix ) ) {
			return false;
		}
		$prefix_len = (int) $prefix;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$ip_bin = @inet_pton( $ip );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$sub_bin = @inet_pton( $subnet );
		if ( false === $ip_bin || false === $sub_bin || strlen( $ip_bin ) !== strlen( $sub_bin ) ) {
			return false;
		}

		$max_bits = strlen( $ip_bin ) * 8;
		if ( $prefix_len < 0 || $prefix_len > $max_bits ) {
			return false;
		}

		$full_bytes = intdiv( $prefix_len, 8 );
		$rem_bits   = $prefix_len % 8;

		if ( $full_bytes > 0 && substr( $ip_bin, 0, $full_bytes ) !== substr( $sub_bin, 0, $full_bytes ) ) {
			return false;
		}
		if ( $rem_bits > 0 ) {
			$mask = ( 0xFF << ( 8 - $rem_bits ) ) & 0xFF;
			if ( ( ord( $ip_bin[ $full_bytes ] ) & $mask ) !== ( ord( $sub_bin[ $full_bytes ] ) & $mask ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Detect cloud provider blocking / cancellation splash screen phrases.
	 *
	 * @param string $html Response HTML.
	 * @return bool
	 */
	public static function contains_block_phrase( $html ) {
		foreach ( self::BLOCK_PHRASES as $phrase ) {
			if ( false !== mb_stripos( (string) $html, $phrase ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Generate a standalone, zero-dependency static HTML landing page (`index.html`)
	 * ready to deploy on the filing provider's mainland server for keepalive verification.
	 *
	 * @param array<string, mixed>|null $settings Optional settings snapshot.
	 * @return string
	 */
	public static function generate_keepalive_html( ?array $settings = null ) {
		$cfg       = is_array( $settings ) ? SettingsStore::normalize( $settings ) : SettingsStore::effective();
		$site_name = '' !== trim( (string) $cfg['icp_site_name'] )
			? trim( (string) $cfg['icp_site_name'] )
			: ( function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '网站合规公示' );
		$company   = trim( (string) $cfg['company_legal_name'] );
		$icp       = trim( (string) $cfg['icp_number'] );
		$mps_info  = BadgeCatalog::parse_mps( (string) $cfg['public_security_code'], (string) $cfg['province_abbr'] );
		$mps_text  = $mps_info['formatted'];
		$mps_url   = $mps_info['url'];
		$home_url  = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
		$email     = trim( (string) $cfg['report_email'] );
		$phone     = trim( (string) $cfg['report_phone'] );
		$year      = function_exists( 'wp_date' ) ? wp_date( 'Y' ) : gmdate( 'Y' );

		$lines   = array();
		$lines[] = '<!DOCTYPE html>';
		$lines[] = '<html lang="zh-CN">';
		$lines[] = '<head>';
		$lines[] = '<meta charset="utf-8">';
		$lines[] = '<meta name="viewport" content="width=device-width, initial-scale=1">';
		$lines[] = '<title>' . esc_html( $site_name ) . '</title>';
		$lines[] = '<style>body{margin:0;min-height:100vh;display:flex;flex-direction:column;justify-content:space-between;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f7f8fa;color:#1f2329}main{max-width:680px;margin:12vh auto 0;padding:32px;background:#fff;border:1px solid #e5e7eb;border-radius:12px}h1{margin:0 0 12px;font-size:22px;font-weight:500}p{margin:0 0 12px;line-height:1.7;color:#4b5563;font-size:14px}a.btn{display:inline-block;margin-top:8px;padding:8px 18px;border-radius:6px;background:#2563eb;color:#fff;text-decoration:none;font-size:14px}footer{padding:24px 16px;text-align:center;font-size:13px;color:#6b7280;line-height:1.8}footer a{color:#4b5563;text-decoration:none}footer a:hover{text-decoration:underline}</style>';
		$lines[] = '</head>';
		$lines[] = '<body>';
		$lines[] = '<main>';
		$lines[] = '<h1>' . esc_html( $site_name ) . '</h1>';
		if ( '' !== $company ) {
			$lines[] = '<p>主办单位：' . esc_html( $company ) . '</p>';
		}
		$lines[] = '<p>本页面为站点备案与合规主体公示入口，如需浏览完整内容请前往主站。</p>';
		if ( '' !== $email || '' !== $phone ) {
			$contact = array();
			if ( '' !== $email ) {
				$contact[] = '举报邮箱：' . esc_html( $email );
			}
			if ( '' !== $phone ) {
				$contact[] = '联系电话：' . esc_html( $phone );
			}
			$lines[] = '<p>' . implode( ' · ', $contact ) . '</p>';
		}
		$lines[] = '<a class="btn" href="' . esc_url( $home_url ) . '">访问主站</a>';
		$lines[] = '</main>';
		$lines[] = '<footer>';
		$foot    = array();
		if ( '' !== $company ) {
			$foot[] = '&copy; ' . esc_html( $year ) . ' ' . esc_html( $company );
		}
		if ( '' !== $icp ) {
			$foot[] = '<a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer">' . esc_html( $icp ) . '</a>';
		}
		if ( '' !== $mps_text ) {
			$foot[] = '<a href="' . esc_url( $mps_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $mps_text ) . '</a>';
		}
		$lines[] = implode( ' | ', $foot );
		$lines[] = '</footer>';
		$lines[] = '</body>';
		$lines[] = '</html>';

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Append a keepalive probe result to the rolling 10-entry log option.
	 *
	 * @param array<string, mixed> $probe Probe result.
	 * @return void
	 */
	private static function append_log( array $probe ) {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$logs = self::recent_logs();
		array_unshift(
			$logs,
			array(
				'time'         => (string) $probe['time'],
				'url'          => (string) $probe['url'],
				'primary_ip'   => (string) $probe['primary_ip'],
				'ip_matched'   => ! empty( $probe['ip_matched'] ),
				'http_code'    => (int) $probe['http_code'],
				'latency_ms'   => (int) $probe['latency_ms'],
				'ok'           => ! empty( $probe['ok'] ),
				'result_label' => (string) $probe['result_label'],
			)
		);
		update_option( self::LOGS_OPTION, array_slice( $logs, 0, 10 ), false );
	}

	/**
	 * Track consecutive failures and notify admin email after 2 failures (cooldown 24h).
	 *
	 * @param string[]             $issues Detected issue descriptions.
	 * @param array<string, mixed> $cfg    Normalized settings.
	 * @return void
	 */
	private static function handle_alert_threshold( array $issues, array $cfg ) {
		if ( empty( $issues ) ) {
			update_option( self::FAIL_COUNT_OPTION, 0, false );
			return;
		}

		$count = (int) get_option( self::FAIL_COUNT_OPTION, 0 ) + 1;
		update_option( self::FAIL_COUNT_OPTION, $count, false );

		if ( $count < 2 || ! function_exists( 'wp_mail' ) ) {
			return;
		}

		$to = trim( (string) $cfg['alert_email'] );
		if ( '' === $to ) {
			$to = (string) get_option( 'admin_email', '' );
		}
		if ( '' === $to ) {
			return;
		}

		$cooldown_key = 'wpicp_access_alert_sent';
		if ( function_exists( 'get_transient' ) && get_transient( $cooldown_key ) ) {
			return;
		}

		$site    = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : 'WordPress';
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] 备案首页自检或接入商保活异常提醒', 'wpicp' ),
			$site
		);
		$message = __( '文派备案（WPICP）连续检测到以下合规或接入异常，请在接入商常规整改期内（通常 5 个工作日）及时核对：', 'wpicp' )
			. "\n\n- " . implode( "\n- ", $issues ) . "\n";

		wp_mail( $to, $subject, $message );
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $cooldown_key, 1, DAY_IN_SECONDS );
		}
	}
}
