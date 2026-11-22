<?php
/**
 * REST API endpoints for offline Ad-Law scanning, batch site auditing,
 * allowlist management, and optional WPMind AI compliant rewriting.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Rest;

use WenPai\ICP\Compliance\AccessMonitor;
use WenPai\ICP\Compliance\AdLawScanner;
use WenPai\ICP\Compliance\WordYeahClient;
use WenPai\ICP\Options\SettingsStore;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `/wpicp/v1/ad-law/*` REST routes.
 */
class AuditController {

	const NAMESPACE_V1   = 'wpicp/v1';
	const SUMMARY_OPTION = 'wpicp_audit_summary';

	/**
	 * Hook REST route registration to `rest_api_init`.
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
	 * Register REST routes under `wpicp/v1`.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/ad-law/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_scan_fragment' ),
				'permission_callback' => array( __CLASS__, 'can_edit_content' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ad-law/scan-site',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_scan_site' ),
				'permission_callback' => array( __CLASS__, 'can_manage_compliance' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ad-law/allowlist',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_add_allowlist' ),
				'permission_callback' => array( __CLASS__, 'can_manage_compliance' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ad-law/rewrite',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_wpmind_rewrite' ),
				'permission_callback' => array( __CLASS__, 'can_edit_content' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/access/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_access_check' ),
				'permission_callback' => array( __CLASS__, 'can_manage_compliance' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/wordyeah/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_wordyeah_sync' ),
				'permission_callback' => array( __CLASS__, 'can_manage_compliance' ),
			)
		);
	}

	/**
	 * Execute live homepage compliance + keepalive subdomain probe (`POST /wpicp/v1/access/check`).
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_access_check() {
		$snapshot = AccessMonitor::run_all_checks( null, true, true );
		return new WP_REST_Response( $snapshot, 200 );
	}

	/**
	 * Sync WordYeah remote ruleset from `api.wpcy.com` (`POST /wpicp/v1/wordyeah/sync`).
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_wordyeah_sync() {
		$res = WordYeahClient::sync_now( true );
		return new WP_REST_Response( $res, 200 );
	}

	/**
	 * Permission callback for editor-level scanning and AI rewriting.
	 *
	 * @return bool
	 */
	public static function can_edit_content() {
		return current_user_can( 'edit_posts' ) || self::can_manage_compliance();
	}

	/**
	 * Permission callback for site-wide audit and allowlist writes.
	 *
	 * @return bool
	 */
	public static function can_manage_compliance() {
		if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Scan a single post/page draft or HTML fragment (`POST /wpicp/v1/ad-law/scan`).
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public static function handle_scan_fragment( WP_REST_Request $request ) {
		if ( ! AdLawScanner::is_enabled() ) {
			return new WP_REST_Response(
				array(
					'enabled'  => false,
					'findings' => array(),
					'count'    => 0,
				),
				200
			);
		}

		$title     = (string) $request->get_param( 'title' );
		$content   = (string) $request->get_param( 'html' );
		$excerpt   = (string) $request->get_param( 'excerpt' );
		$allow_raw = $request->get_param( 'allowlist' );
		$extra     = is_array( $allow_raw ) ? $allow_raw : array();

		$fragments = array();
		if ( '' !== trim( $title ) ) {
			$fragments[] = '<h1 id="post-title">' . esc_html( $title ) . '</h1>';
		}
		if ( '' !== trim( $excerpt ) ) {
			$fragments[] = '<div id="post-excerpt">' . wp_kses_post( $excerpt ) . '</div>';
		}
		if ( '' !== trim( $content ) ) {
			$fragments[] = '<div id="post-content">' . $content . '</div>';
		}

		$merged_allow = array_merge( AdLawScanner::get_site_allowlist(), $extra );
		$raw_hits     = AdLawScanner::scan_html( implode( "\n", $fragments ), $merged_allow );
		$enriched     = self::enrich_findings( $raw_hits );

		return new WP_REST_Response(
			array(
				'enabled'         => true,
				'wpmindAvailable' => function_exists( 'wpmind_is_available' ) && wpmind_is_available(),
				'canManageAllow'  => self::can_manage_compliance(),
				'count'           => count( $enriched ),
				'findings'        => $enriched,
			),
			200
		);
	}

	/**
	 * Batch-scan published/draft posts & site tagline (`POST /wpicp/v1/ad-law/scan-site`).
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response
	 */
	public static function handle_scan_site( WP_REST_Request $request ) {
		$cfg        = SettingsStore::effective();
		$page       = max( 1, (int) $request->get_param( 'page' ) );
		$raw_per    = (int) $request->get_param( 'per_page' );
		$per_page   = min( 50, max( 5, $raw_per > 0 ? $raw_per : 20 ) );
		$req_types  = $request->get_param( 'post_types' );
		$post_types = is_array( $req_types ) && ! empty( $req_types )
			? array_values( array_filter( array_map( 'sanitize_key', $req_types ) ) )
			: ( ! empty( $cfg['ad_law_scan_post_types'] ) ? (array) $cfg['ad_law_scan_post_types'] : array( 'post', 'page' ) );

		$allowlist = AdLawScanner::get_site_allowlist();
		$items     = array();

		// On batch page 1, also inspect site title and tagline (`blogdescription`).
		if ( 1 === $page ) {
			$blog_name = (string) get_option( 'blogname', '' );
			$blog_desc = (string) get_option( 'blogdescription', '' );
			$meta_html = '<div id="blogname">' . esc_html( $blog_name ) . '</div><div id="blogdescription">' . esc_html( $blog_desc ) . '</div>';
			$meta_hits = AdLawScanner::scan_html( $meta_html, $allowlist );
			foreach ( self::enrich_findings( $meta_hits ) as $hit ) {
				$items[] = array_merge(
					$hit,
					array(
						'post_id'    => 0,
						'post_title' => __( '站点标题与副标题', 'wpicp' ),
						'post_type'  => __( '常规设置', 'wpicp' ),
						'edit_url'   => admin_url( 'options-general.php' ),
						'view_url'   => home_url( '/' ),
					)
				);
			}
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $post ) {
			$combined = '<h1 id="post-title">' . esc_html( (string) $post->post_title ) . '</h1>'
				. "\n" . (string) $post->post_excerpt
				. "\n" . (string) $post->post_content;
			$hits     = AdLawScanner::scan_html( $combined, $allowlist );
			if ( empty( $hits ) ) {
				continue;
			}

			$pt_obj   = get_post_type_object( $post->post_type );
			$pt_label = ( $pt_obj && isset( $pt_obj->labels->singular_name ) )
				? (string) $pt_obj->labels->singular_name
				: (string) $post->post_type;
			$title    = '' !== trim( (string) $post->post_title )
				? (string) $post->post_title
				: sprintf( '#%d', $post->ID );
			$edit_url = (string) get_edit_post_link( $post->ID, 'raw' );
			$view_url = (string) get_permalink( $post->ID );

			foreach ( self::enrich_findings( $hits ) as $hit ) {
				$items[] = array_merge(
					$hit,
					array(
						'post_id'    => (int) $post->ID,
						'post_title' => $title,
						'post_type'  => $pt_label,
						'edit_url'   => $edit_url,
						'view_url'   => $view_url,
					)
				);
			}
		}

		$total_posts = (int) $query->found_posts;
		$total_pages = max( 1, (int) $query->max_num_pages );

		// Persist cumulative audit summary when client completes final batch.
		$total_hits_acc = $request->get_param( 'accumulated_hits' );
		if ( $page >= $total_pages ) {
			$final_hits = null !== $total_hits_acc
				? max( 0, (int) $total_hits_acc ) + count( $items )
				: count( $items );
			update_option(
				self::SUMMARY_OPTION,
				array(
					'last_scan_at'  => time(),
					'scanned_posts' => $total_posts,
					'total_hits'    => $final_hits,
				),
				false
			);
		}

		return new WP_REST_Response(
			array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => $total_pages,
				'total_posts' => $total_posts,
				'scanned'     => count( $query->posts ),
				'items'       => $items,
			),
			200
		);
	}

	/**
	 * Append a term/phrase to the site/network custom allowlist (`POST /wpicp/v1/ad-law/allowlist`).
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_add_allowlist( WP_REST_Request $request ) {
		$term = sanitize_text_field( trim( (string) $request->get_param( 'term' ) ) );
		if ( '' === $term ) {
			return new WP_Error( 'wpicp_empty_term', __( '请提供需要加入白名单的词条。', 'wpicp' ), array( 'status' => 400 ) );
		}

		$is_net_locked = is_multisite() && ! SettingsStore::allows_subsite_override();
		if ( $is_net_locked ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				return new WP_Error( 'wpicp_forbidden', __( '当前网络配置由超级管理员统一维护。', 'wpicp' ), array( 'status' => 403 ) );
			}
			$cfg       = SettingsStore::effective();
			$allowlist = is_array( $cfg['ad_law_allowlist'] ) ? $cfg['ad_law_allowlist'] : array();
			if ( ! in_array( $term, $allowlist, true ) ) {
				$allowlist[] = $term;
			}
			$saved = SettingsStore::save_network( array( 'ad_law_allowlist' => $allowlist ) );
		} else {
			$cfg       = SettingsStore::effective();
			$allowlist = is_array( $cfg['ad_law_allowlist'] ) ? $cfg['ad_law_allowlist'] : array();
			if ( ! in_array( $term, $allowlist, true ) ) {
				$allowlist[] = $term;
			}
			$saved = SettingsStore::save_site( array( 'ad_law_allowlist' => $allowlist ) );
		}

		return new WP_REST_Response(
			array(
				'added'     => $term,
				'allowlist' => $saved['ad_law_allowlist'],
			),
			200
		);
	}

	/**
	 * Optional compliant sentence rewrite via `WPMind` (`POST /wpicp/v1/ad-law/rewrite`).
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_wpmind_rewrite( WP_REST_Request $request ) {
		$excerpt = sanitize_textarea_field( trim( (string) $request->get_param( 'excerpt' ) ) );
		$term    = sanitize_text_field( trim( (string) $request->get_param( 'term' ) ) );
		if ( '' === $excerpt ) {
			return new WP_Error( 'wpicp_empty_excerpt', __( '缺少待改写的文本片段。', 'wpicp' ), array( 'status' => 400 ) );
		}

		if ( ! function_exists( 'wpmind_is_available' ) || ! wpmind_is_available() || ! function_exists( 'wpmind_chat' ) ) {
			return new WP_Error( 'wpicp_wpmind_unavailable', __( '未检测到可用的文派心思（WPMind）AI 通道。', 'wpicp' ), array( 'status' => 400 ) );
		}

		$prompt = sprintf(
			"请将下面这段中文网页文案中的《广告法》风险用语「%s」改写为客观、克制、合规的中文表达，保持原意通顺，只输出改写后的单句文本，不要加引号或解释：\n%s",
			$term,
			$excerpt
		);

		$result = wpmind_chat(
			$prompt,
			array(
				'context'     => 'wpicp_ad_law_rewrite',
				'system'      => '你是熟悉中国《广告法》的合规文案编辑，严禁使用绝对化用语、无法证实的承诺和违规权威背书词。',
				'temperature' => 0.3,
				'max_tokens'  => 240,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$rewritten = is_array( $result ) && isset( $result['content'] )
			? trim( (string) $result['content'] )
			: ( is_string( $result ) ? trim( $result ) : '' );

		if ( '' === $rewritten ) {
			return new WP_Error( 'wpicp_wpmind_empty', __( 'AI 未返回有效改写结果，请采用推荐替换词。', 'wpicp' ), array( 'status' => 502 ) );
		}

		return new WP_REST_Response(
			array(
				'original'  => $excerpt,
				'rewritten' => $rewritten,
			),
			200
		);
	}

	/**
	 * Attach human-readable `category_label` to scanner findings.
	 *
	 * @param array<int, array<string, mixed>> $findings Raw scanner findings.
	 * @return array<int, array<string, mixed>>
	 */
	private static function enrich_findings( array $findings ) {
		$out = array();
		foreach ( $findings as $hit ) {
			$cat                   = isset( $hit['category'] ) ? (string) $hit['category'] : 'absolute';
			$hit['category_label'] = AdLawScanner::category_label( $cat );
			$out[]                 = $hit;
		}
		return $out;
	}
}
