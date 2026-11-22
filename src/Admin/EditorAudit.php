<?php
/**
 * Gutenberg Pre-Publish / Document Setting Panel & Classic Editor Meta Box for Ad-Law audit.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Admin;

use WenPai\ICP\Compliance\AdLawScanner;
use WenPai\ICP\Compliance\WpmindReviewer;
use WenPai\ICP\Options\SettingsStore;
use Wenpai_Admin_Loader;
use Wenpai_Admin_UI;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates real-time non-blocking Ad-Law risk checks into Block Editor and Classic Editor.
 */
class EditorAudit {

	/**
	 * Register Block Editor asset hook and Classic Editor meta box hook.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_panel' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_classic_metabox' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_classic_assets' ) );
	}

	/**
	 * Whether editor-level Ad-Law inspection is active.
	 *
	 * @return bool
	 */
	private static function is_active() {
		$cfg = SettingsStore::effective();
		return SettingsStore::module_enabled( 'module_adlaw', $cfg )
			&& ! empty( $cfg['ad_law_enabled'] )
			&& ! empty( $cfg['ad_law_editor_panel'] );
	}

	/**
	 * Enqueue Gutenberg `PluginPrePublishPanel` + `PluginDocumentSettingPanel` script.
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_panel() {
		if ( ! self::is_active() || ! defined( 'WPICP_PLUGIN_URL' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'widgets' === $screen->id ) {
			return;
		}
		if ( $screen && 'site-editor' === $screen->id ) {
			return;
		}

		$ver = defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0';
		wp_enqueue_script(
			'wpicp-editor-audit',
			WPICP_PLUGIN_URL . 'assets/js/editor-audit.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-editor',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-api-fetch',
				'wp-i18n',
			),
			$ver,
			true
		);

		wp_localize_script(
			'wpicp-editor-audit',
			'wpicpEditorAudit',
			array(
				'restScanPath'      => '/wpicp/v1/ad-law/scan',
				'restAllowlistPath' => '/wpicp/v1/ad-law/allowlist',
				'restRewritePath'   => '/wpicp/v1/ad-law/rewrite',
				'wpmindAvailable'   => WpmindReviewer::is_wpmind_available(),
				'aiReviewActive'    => WpmindReviewer::is_active(),
				'canManageAllow'    => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Register Meta Box for Classic Editor screens when Block Editor is not used.
	 *
	 * @param string       $post_type Post type slug.
	 * @param WP_Post|null $post      Post object.
	 * @return void
	 */
	public static function register_classic_metabox( $post_type, $post = null ) {
		unset( $post );
		if ( ! self::is_active() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		$cfg     = SettingsStore::effective();
		$allowed = ! empty( $cfg['ad_law_scan_post_types'] ) ? (array) $cfg['ad_law_scan_post_types'] : array( 'post', 'page' );
		if ( ! in_array( (string) $post_type, $allowed, true ) ) {
			return;
		}

		add_meta_box(
			'wpicp-ad-law-metabox',
			__( '《广告法》合规风险检查', 'wpicp' ),
			array( __CLASS__, 'render_classic_metabox' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Enqueue kit native embed tokens on Classic Editor post screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_classic_assets( $hook_suffix ) {
		if ( ! in_array( (string) $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! self::is_active() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}
		if ( class_exists( 'Wenpai_Admin_Loader', false ) ) {
			Wenpai_Admin_Loader::enqueue( array( 'full' => false ) );
		}
	}

	/**
	 * Render Classic Editor Meta Box using `Wenpai_Admin_UI::native_metabox`.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_classic_metabox( $post ) {
		$html     = '<h1 id="post-title">' . esc_html( (string) $post->post_title ) . '</h1>'
			. "\n" . (string) $post->post_excerpt
			. "\n" . (string) $post->post_content;
		$findings = AdLawScanner::scan_html( $html, AdLawScanner::get_site_allowlist() );

		if ( empty( $findings ) ) {
			$body  = '<div class="wenpai-stack wenpai-stack--xs">';
			$body .= Wenpai_Admin_UI::pill_html(
				array(
					'text'  => __( '未发现极限词风险', 'wpicp' ),
					'state' => 'ok',
				)
			);
			$body .= '<p class="meta">' . esc_html__( '保存草稿或更新后将自动复核可见正文与标题。', 'wpicp' ) . '</p>';
			$body .= '</div>';
		} else {
			$body  = '<div class="wenpai-stack wenpai-stack--xs">';
			$body .= Wenpai_Admin_UI::pill_html(
				array(
					'text'  => sprintf(
						/* translators: %d: hit count */
						__( '命中 %d 处风险词', 'wpicp' ),
						count( $findings )
					),
					'state' => 'warn',
				)
			);
			foreach ( array_slice( $findings, 0, 8 ) as $hit ) {
				$sug   = ! empty( $hit['suggestions'] ) ? implode( ' / ', (array) $hit['suggestions'] ) : '';
				$body .= '<div class="meta"><strong>' . esc_html( $hit['term'] ) . '</strong>（' . esc_html( AdLawScanner::category_label( $hit['category'] ) ) . '）';
				if ( '' !== $sug ) {
					$body .= ' → ' . esc_html( $sug );
				}
				$body .= '</div>';
			}
			$body .= '</div>';
		}

		if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
			Wenpai_Admin_UI::native_metabox( array( 'body_html' => $body ) );
		} else {
			echo wp_kses_post( $body );
		}
	}
}
