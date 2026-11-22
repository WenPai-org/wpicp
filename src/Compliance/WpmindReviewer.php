<?php
/**
 * Optional WPMind semantic review helper for Chinese Advertising Law findings.
 *
 * Zero hard dependency on WPMind: when WPMind is absent, unavailable, disabled
 * in WPICP settings, or returns a WP_Error / malformed payload, callers cleanly
 * fall back to offline dictionary findings without losing any detections.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Compliance;

use WenPai\ICP\Options\SettingsStore;
use Wenpai_Admin_Capability;
use Wenpai_Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates optional WPMind semantic false-positive filtering for Ad-Law scans.
 */
class WpmindReviewer {

	/**
	 * WPMind usage-tracking context identifier (rolls up to `wpicp` in WPMind ledger).
	 */
	const CONTEXT = 'wpicp_adlaw';

	/**
	 * Relative plugin file path for sibling capability probing.
	 */
	const WPMIND_PLUGIN_FILE = 'wpmind/wpmind.php';

	/**
	 * Maximum candidate findings submitted in a single semantic review call.
	 */
	const MAX_ITEMS_PER_CALL = 20;

	/**
	 * Check whether the WPMind runtime API is available.
	 *
	 * Supports both direct `wpmind_is_available()` and weak-coupled filter fallback.
	 *
	 * @return bool
	 */
	public static function is_wpmind_available() {
		if ( function_exists( 'wpmind_is_available' ) ) {
			return (bool) wpmind_is_available();
		}

		if ( function_exists( 'apply_filters' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WPMind public API hook.
			return (bool) apply_filters( 'wpmind_is_available', false );
		}

		return false;
	}

	/**
	 * Check whether the site administrator enabled WPMind semantic review for Ad-Law scans.
	 *
	 * @param array<string, mixed>|null $cfg Optional settings snapshot.
	 * @return bool
	 */
	public static function is_enabled( ?array $cfg = null ) {
		$settings = is_array( $cfg ) ? $cfg : SettingsStore::effective();

		if ( ! SettingsStore::module_enabled( 'module_adlaw', $settings ) ) {
			return false;
		}
		if ( empty( $settings['ad_law_enabled'] ) ) {
			return false;
		}

		$engine = isset( $settings['ai_engine'] ) ? (string) $settings['ai_engine'] : 'offline';
		if ( 'wpmind' === $engine ) {
			return true;
		}

		return ! empty( $settings['ai_review_enabled'] ) && '0' !== (string) $settings['ai_review_enabled'];
	}

	/**
	 * Whether semantic review is both enabled in WPICP settings and backed by an active WPMind channel.
	 *
	 * @param array<string, mixed>|null $cfg Optional settings snapshot.
	 * @return bool
	 */
	public static function is_active( ?array $cfg = null ) {
		return self::is_enabled( $cfg ) && self::is_wpmind_available();
	}

	/**
	 * Resolve WPMind settings URL through `Wenpai_Admin_Menu::url()` when available.
	 *
	 * @return string
	 */
	public static function wpmind_settings_url() {
		if ( class_exists( 'Wenpai_Admin_Menu', false ) ) {
			return Wenpai_Admin_Menu::url( 'wpmind' );
		}
		if ( function_exists( 'admin_url' ) ) {
			return admin_url( 'options-general.php?page=wpmind' );
		}
		return 'options-general.php?page=wpmind';
	}

	/**
	 * Build shared arguments for `Wenpai_Admin_Capability::choice_gate()` and `::status()`.
	 *
	 * @return array<string, mixed>
	 */
	public static function capability_args() {
		return array(
			'plugin'           => self::WPMIND_PLUGIN_FILE,
			'slot'             => 'wpicp-mind-adlaw',
			'product'          => __( '文派心思', 'wpicp' ),
			'available'        => array( __CLASS__, 'is_wpmind_available' ),
			'learn_url'        => 'https://wpcy.com/mind',
			'settings_url'     => self::wpmind_settings_url(),
			'hint_missing'     => __( '推荐', 'wpicp' ),
			'available_status' => array(
				'text' => __( '已就绪', 'wpicp' ),
				'desc' => array(
					__( '离线词库命中疑似极限词后，将交由文派心思结合语境剔除明显误报。', 'wpicp' ),
				),
			),
			'installed_status' => array(
				'text' => __( '未启用', 'wpicp' ),
				'desc' => array(
					__( '已安装文派心思但尚未启用，当前自动使用纯离线词库扫描。', 'wpicp' ),
				),
			),
			'setup_status'     => array(
				'text' => __( '需配置', 'wpicp' ),
				'desc' => array(
					__( '文派心思尚未完成模型或云端算力配置，当前自动退回纯离线词库扫描。', 'wpicp' ),
				),
			),
			'missing_status'   => array(
				'text' => __( '未安装', 'wpicp' ),
				'desc' => array(
					__( '安装文派心思（WPMind）后可开启广告法语义复核，甄别客观陈述与误报。', 'wpicp' ),
				),
			),
		);
	}

	/**
	 * Resolve choice-card gate metadata using `Wenpai_Admin_Capability::choice_gate()` when available.
	 *
	 * @return array{disabled: bool, hint: string, hidden: bool, dismiss: array<string, mixed>|null}
	 */
	public static function choice_gate() {
		if ( class_exists( 'Wenpai_Admin_Capability', false ) ) {
			return Wenpai_Admin_Capability::choice_gate( self::capability_args() );
		}

		$available = self::is_wpmind_available();
		return array(
			'disabled' => ! $available,
			'hint'     => '',
			'hidden'   => false,
			'dismiss'  => null,
		);
	}

	/**
	 * Resolve read-only status payload using `Wenpai_Admin_Capability::status()` when available.
	 *
	 * @return array<string, mixed>
	 */
	public static function status_payload() {
		if ( class_exists( 'Wenpai_Admin_Capability', false ) ) {
			return Wenpai_Admin_Capability::status( self::capability_args() );
		}

		if ( self::is_wpmind_available() ) {
			return array(
				'level' => 'ok',
				'text'  => __( '已就绪', 'wpicp' ),
				'desc'  => array(
					__( '文派心思通道可用，开启后将在离线命中后执行语境复核。', 'wpicp' ),
				),
				'link'  => array(
					'url'   => self::wpmind_settings_url(),
					'label' => __( '打开设置', 'wpicp' ),
				),
			);
		}

		return array(
			'level' => 'mute',
			'text'  => __( '未安装', 'wpicp' ),
			'desc'  => array(
				__( '未检测到可用的文派心思（WPMind），当前仅使用纯离线词库扫描。', 'wpicp' ),
			),
		);
	}

	/**
	 * JSON Schema passed to `wpmind_structured()` for deterministic false-positive filtering.
	 *
	 * @return array<string, mixed>
	 */
	public static function review_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'items' ),
			'properties' => array(
				'items' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array( 'index', 'is_violation', 'reason' ),
						'properties' => array(
							'index'        => array( 'type' => 'integer' ),
							'is_violation' => array( 'type' => 'boolean' ),
							'confidence'   => array(
								'type' => 'string',
								'enum' => array( 'high', 'medium', 'low' ),
							),
							'reason'       => array( 'type' => 'string' ),
							'suggestion'   => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Semantically review offline Ad-Law candidate hits via WPMind.
	 *
	 * Falls back cleanly to the original `$findings` whenever:
	 * - `$findings` is empty
	 * - AI review is disabled in settings (unless `$options['force']` is true)
	 * - WPMind is absent or unavailable
	 * - WPMind returns a `WP_Error` or an unreadable payload
	 *
	 * @param array<int, array<string, mixed>> $findings Offline findings from `AdLawScanner::scan_html()`.
	 * @param array<string, mixed>             $options  {
	 *     Optional behavior flags.
	 *
	 *     @type bool                      $force                Bypass settings check (still requires WPMind available).
	 *     @type bool                      $keep_false_positives Keep non-violations tagged with `ai_verdict => 'false_positive'`.
	 *     @type array<string, mixed>|null $settings             Optional settings snapshot override.
	 * }
	 * @return array<int, array<string, mixed>>
	 */
	public static function review_findings( array $findings, array $options = array() ) {
		if ( empty( $findings ) ) {
			return array();
		}

		$cfg   = isset( $options['settings'] ) && is_array( $options['settings'] ) ? $options['settings'] : null;
		$force = ! empty( $options['force'] );
		if ( ! $force && ! self::is_enabled( $cfg ) ) {
			return $findings;
		}
		if ( ! self::is_wpmind_available() ) {
			return $findings;
		}

		$normalized = array_values( $findings );
		$batch      = array_slice( $normalized, 0, self::MAX_ITEMS_PER_CALL );
		$overflow   = array_slice( $normalized, self::MAX_ITEMS_PER_CALL );

		$payload_items = array();
		foreach ( $batch as $idx => $hit ) {
			$payload_items[] = array(
				'index'    => $idx,
				'term'     => isset( $hit['term'] ) ? (string) $hit['term'] : '',
				'category' => isset( $hit['category'] ) ? (string) $hit['category'] : 'absolute',
				'excerpt'  => isset( $hit['excerpt'] ) ? (string) $hit['excerpt'] : '',
			);
		}

		$encoded_items = function_exists( 'wp_json_encode' )
			? wp_json_encode( $payload_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			: json_encode( $payload_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fallback outside WordPress.

		if ( ! is_string( $encoded_items ) || '' === $encoded_items ) {
			return $findings;
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => '你是熟悉中国《广告法》的内容合规语义复核助手。针对离线词库命中的候选项，结合摘录语境区分真实商业宣传违规（is_violation=true）与客观事实陈述/技术参数/非商业宣传误报（is_violation=false）。输出 JSON 对象 {"items":[{"index":0,"is_violation":true,"confidence":"high","reason":"简短理由","suggestion":"可选替换词"}]}，严格按输入 index 逐条返回。',
			),
			array(
				'role'    => 'user',
				'content' => "请逐条复核以下《广告法》疑似极限词命中项：\n" . $encoded_items,
			),
		);

		$schema    = self::review_schema();
		$call_opts = array(
			'context'     => self::CONTEXT,
			'temperature' => 0.1,
			'max_tokens'  => 1200,
		);

		$data = self::invoke_wpmind( $messages, $schema, $call_opts );
		if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return $findings;
		}

		$verdicts = array();
		foreach ( $data['items'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['index'] ) || ! is_numeric( $row['index'] ) ) {
				continue;
			}
			$verdicts[ (int) $row['index'] ] = $row;
		}

		if ( empty( $verdicts ) ) {
			return $findings;
		}

		$keep_fp  = ! empty( $options['keep_false_positives'] );
		$reviewed = array();

		foreach ( $batch as $idx => $hit ) {
			if ( ! isset( $verdicts[ $idx ] ) ) {
				// Conservative fallback: keep any item omitted by the model.
				$reviewed[] = $hit;
				continue;
			}

			$verdict      = $verdicts[ $idx ];
			$is_violation = ! array_key_exists( 'is_violation', $verdict ) || (bool) $verdict['is_violation'];
			$confidence   = isset( $verdict['confidence'] ) && in_array( (string) $verdict['confidence'], array( 'high', 'medium', 'low' ), true )
				? (string) $verdict['confidence']
				: 'medium';
			$reason       = isset( $verdict['reason'] ) && is_scalar( $verdict['reason'] )
				? sanitize_text_field( trim( (string) $verdict['reason'] ) )
				: '';
			$suggestion   = isset( $verdict['suggestion'] ) && is_scalar( $verdict['suggestion'] )
				? sanitize_text_field( trim( (string) $verdict['suggestion'] ) )
				: '';

			$hit['ai_reviewed']   = true;
			$hit['ai_verdict']    = $is_violation ? 'violation' : 'false_positive';
			$hit['ai_confidence'] = $confidence;
			$hit['ai_reason']     = $reason;

			if ( $is_violation && '' !== $suggestion ) {
				$sugs = isset( $hit['suggestions'] ) && is_array( $hit['suggestions'] ) ? $hit['suggestions'] : array();
				if ( ! in_array( $suggestion, $sugs, true ) ) {
					array_unshift( $sugs, $suggestion );
				}
				$hit['suggestions'] = $sugs;
			}

			if ( ! $is_violation && ! $keep_fp ) {
				continue;
			}

			$reviewed[] = $hit;
		}

		foreach ( $overflow as $hit ) {
			$reviewed[] = $hit;
		}

		return array_values( $reviewed );
	}

	/**
	 * Invoke `wpmind_structured()` first, falling back to `wpmind_chat()` (JSON mode) if structured API is absent.
	 *
	 * @param array<int, array{role:string, content:string}> $messages  Chat messages.
	 * @param array<string, mixed>                           $schema    JSON Schema.
	 * @param array<string, mixed>                           $call_opts WPMind call options.
	 * @return array<string, mixed>|null Decoded payload or null on error/unavailability.
	 */
	private static function invoke_wpmind( array $messages, array $schema, array $call_opts ) {
		$has_structured_fn     = function_exists( 'wpmind_structured' );
		$has_structured_filter = ! $has_structured_fn && function_exists( 'has_filter' ) && false !== has_filter( 'wpmind_structured' );

		if ( $has_structured_fn || $has_structured_filter ) {
			$raw = $has_structured_fn
				? wpmind_structured( $messages, $schema, $call_opts )
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WPMind public API hook.
				: apply_filters( 'wpmind_structured', null, $messages, $schema, $call_opts );

			if ( self::is_error_result( $raw ) || ! is_array( $raw ) ) {
				return null;
			}

			if ( isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
				return $raw['data'];
			}
			if ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
				return $raw;
			}
			return null;
		}

		$has_chat_fn     = function_exists( 'wpmind_chat' );
		$has_chat_filter = ! $has_chat_fn && function_exists( 'has_filter' ) && false !== has_filter( 'wpmind_chat' );

		if ( ! $has_chat_fn && ! $has_chat_filter ) {
			return null;
		}

		$chat_opts = array_merge(
			$call_opts,
			array(
				'json_mode' => true,
			)
		);

		$raw = $has_chat_fn
			? wpmind_chat( $messages, $chat_opts )
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared WPMind public API hook.
			: apply_filters( 'wpmind_chat', null, $messages, $chat_opts );

		if ( self::is_error_result( $raw ) ) {
			return null;
		}

		$content = '';
		if ( is_array( $raw ) && isset( $raw['content'] ) && is_string( $raw['content'] ) ) {
			$content = $raw['content'];
		} elseif ( is_string( $raw ) ) {
			$content = $raw;
		}

		return self::decode_json_payload( $content );
	}

	/**
	 * Decode a JSON string that may optionally be wrapped in Markdown code fences.
	 *
	 * @param string $content Raw model text.
	 * @return array<string, mixed>|null
	 */
	private static function decode_json_payload( $content ) {
		$trimmed = trim( (string) $content );
		if ( '' === $trimmed ) {
			return null;
		}

		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $m ) ) {
			$trimmed = trim( (string) $m[1] );
		}

		$decoded = json_decode( $trimmed, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}

	/**
	 * Check whether `$result` is a `WP_Error` instance without requiring WP core bootstrap.
	 *
	 * @param mixed $result API return value.
	 * @return bool
	 */
	private static function is_error_result( $result ) {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			return true;
		}
		return is_object( $result ) && is_a( $result, 'WP_Error' );
	}
}
