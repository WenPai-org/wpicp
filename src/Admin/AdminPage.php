<?php
/**
 * Tier-A WenPai 4.0 Admin Page controller (`overview` / `settings` / `audit` + `help`).
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Admin;

use WenPai\ICP\Migration\LegacyMigrator;
use WenPai\ICP\Options\SettingsStore;
use Wenpai_Admin_Action;
use Wenpai_Admin_Icons;
use Wenpai_Admin_Loader;
use Wenpai_Admin_Menu;
use Wenpai_Admin_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WPICP admin menus, actions, assets, and renders 4.0 shell templates.
 */
class AdminPage {

	/**
	 * Site settings framework instance.
	 *
	 * @var Wenpai_Admin_Settings|null
	 */
	private static $site_settings = null;

	/**
	 * Network settings framework instance.
	 *
	 * @var Wenpai_Admin_Settings|null
	 */
	private static $network_settings = null;

	/**
	 * Register settings framework, admin actions, menus, and asset hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		self::$site_settings = SettingsStore::register_framework( SettingsStore::OPTION_SITE );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			self::$network_settings = SettingsStore::register_framework( SettingsStore::OPTION_NETWORK );
		}

		self::register_actions();

		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'register_site_menu' ) );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			add_action( 'network_admin_menu', array( __CLASS__, 'register_network_menu' ) );
			add_action( 'network_admin_edit_wpicp_save_network', array( __CLASS__, 'handle_network_save' ) );
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		if ( defined( 'WPICP_PLUGIN_BASENAME' ) ) {
			add_filter( 'plugin_action_links_' . WPICP_PLUGIN_BASENAME, array( __CLASS__, 'filter_action_links' ) );
			add_filter( 'network_admin_plugin_action_links_' . WPICP_PLUGIN_BASENAME, array( __CLASS__, 'filter_network_action_links' ) );
		}
	}

	/**
	 * Register POST actions for manual legacy re-import and gated 2025 table cleanup.
	 *
	 * @return void
	 */
	public static function register_actions() {
		if ( ! class_exists( 'Wenpai_Admin_Action', false ) ) {
			return;
		}

		$cap = ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_network_admin' ) && is_network_admin() )
			? 'manage_network_options'
			: 'manage_options';

		Wenpai_Admin_Action::register(
			array(
				'action'     => 'wpicp_reimport_legacy',
				'capability' => $cap,
				'callback'   => static function () {
					LegacyMigrator::maybe_migrate( true );
					return array(
						'level'   => 'ok',
						'message' => __( '已重新读取旧版选项与核心备案项并同步至当前设置。', 'wpicp' ),
					);
				},
			)
		);

		Wenpai_Admin_Action::register(
			array(
				'action'     => 'wpicp_drop_legacy_tables',
				'capability' => 'manage_options',
				'confirm'    => 'wenpai_confirm',
				'callback'   => static function () {
					$dropped = LegacyMigrator::drop_deprecated_tables();
					return array(
						'level'   => 'ok',
						'message' => sprintf(
							/* translators: %d: number of dropped legacy tables */
							__( '已清理 %d 张 2025 早期版本遗留的废弃数据表。', 'wpicp' ),
							$dropped
						),
					);
				},
			)
		);
	}

	/**
	 * Resolve suite-wide display metadata via `wenpai_plugin_display` white-label contract.
	 *
	 * @return array{brand:string, plugin_name:string, help_url:string, feedback_url:string, docs_url:string, news_feed_url:string}
	 */
	public static function display_meta() {
		$defaults = array(
			'brand'         => __( '文派备案', 'wpicp' ),
			'plugin_name'   => 'WPICP',
			'help_url'      => 'https://wpcy.com/icp',
			'feedback_url'  => 'https://wpcy.com/support',
			'docs_url'      => 'https://wpcy.com/document',
			'news_feed_url' => 'https://wptea.com/wp-json/wp/v2/posts',
		);

		$filtered = function_exists( 'apply_filters' )
			? apply_filters( 'wenpai_plugin_display', $defaults, 'wpicp' )
			: $defaults;

		if ( ! is_array( $filtered ) ) {
			return $defaults;
		}

		foreach ( $defaults as $k => $v ) {
			if ( empty( $filtered[ $k ] ) || ! is_string( $filtered[ $k ] ) ) {
				$filtered[ $k ] = $v;
			}
		}

		return $filtered;
	}

	/**
	 * Register single-site / subsite menu entry (`设置 → 备案` or WPCY suite submenu).
	 *
	 * @return void
	 */
	public static function register_site_menu() {
		if ( ! class_exists( 'Wenpai_Admin_Menu', false ) ) {
			return;
		}
		$display = self::display_meta();
		Wenpai_Admin_Menu::register(
			array(
				'slug'         => 'wpicp',
				'label'        => __( '备案', 'wpicp' ),
				'title'        => $display['brand'],
				'capability'   => 'manage_options',
				'render'       => array( __CLASS__, 'render_page' ),
				'legacy_slugs' => array( 'wpicp_license_settings' ),
			)
		);
	}

	/**
	 * Register Multisite Network Admin menu entry (`设置 → 备案`).
	 *
	 * @return void
	 */
	public static function register_network_menu() {
		if ( ! class_exists( 'Wenpai_Admin_Menu', false ) ) {
			return;
		}
		$display = self::display_meta();
		Wenpai_Admin_Menu::register(
			array(
				'slug'       => 'wpicp',
				'label'      => __( '备案', 'wpicp' ),
				'title'      => $display['brand'],
				'capability' => 'manage_network_options',
				'network'    => true,
				'render'     => array( __CLASS__, 'render_page' ),
			)
		);
	}

	/**
	 * Handle Multisite Network Admin form submission (`edit.php?action=wpicp_save_network`).
	 *
	 * @return void
	 */
	public static function handle_network_save() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( '您没有权限修改网络备案配置。', 'wpicp' ), 403 );
		}

		$fw = self::get_settings_instance( true );
		check_admin_referer( $fw->group() . '-options' );

		$raw = isset( $_POST[ SettingsStore::OPTION_NETWORK ] ) && is_array( $_POST[ SettingsStore::OPTION_NETWORK ] )
			? wp_unslash( $_POST[ SettingsStore::OPTION_NETWORK ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via Wenpai_Admin_Settings::sanitize() below.
			: array();

		$tab = isset( $raw[ Wenpai_Admin_Settings::TAB_KEY ] )
			? self::normalize_tab( (string) $raw[ Wenpai_Admin_Settings::TAB_KEY ] )
			: 'filing';
		if ( ! in_array( $tab, array( 'filing', 'badges', 'display', 'audit', 'tools' ), true ) ) {
			$tab = 'filing';
		}

		$sanitized = $fw->sanitize( $raw );
		SettingsStore::save_network( $sanitized );

		$mode    = isset( $_POST['_wpicp_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['_wpicp_mode'] ) ) : '';
		$section = isset( $_POST['_wpicp_section'] ) ? sanitize_key( wp_unslash( (string) $_POST['_wpicp_section'] ) ) : '';
		$args    = array(
			'page'    => 'wpicp',
			'tab'     => $tab,
			'updated' => 'true',
		);
		if ( 'advanced' === $mode ) {
			$args['mode'] = 'advanced';
		}
		if ( '' !== $section ) {
			$args['section'] = $section;
		}

		wp_safe_redirect( add_query_arg( $args, network_admin_url( 'settings.php' ) ) );
		exit;
	}

	/**
	 * Enqueue kit CSS/JS, media modal, and WPICP admin script on WPICP admin pages only.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( '' === $page || ! class_exists( 'Wenpai_Admin_Menu', false ) || ! Wenpai_Admin_Menu::owns_page( $page ) ) {
			return;
		}

		Wenpai_Admin_Loader::enqueue( array( 'body' => 'wpicp-admin' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab check.
		$tab = isset( $_GET['tab'] ) ? self::normalize_tab( wp_unslash( (string) $_GET['tab'] ) ) : 'overview';
		if ( 'badges' === $tab && function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		if ( ! defined( 'WPICP_PLUGIN_URL' ) ) {
			return;
		}
		$ver = defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0';

		wp_enqueue_style(
			'wpicp-frontend',
			WPICP_PLUGIN_URL . 'assets/css/frontend.css',
			array( Wenpai_Admin_Loader::STYLE_HANDLE ),
			$ver
		);

		wp_enqueue_style(
			'wpicp-admin',
			WPICP_PLUGIN_URL . 'assets/css/admin.css',
			array( Wenpai_Admin_Loader::STYLE_HANDLE, 'wpicp-frontend' ),
			$ver
		);

		wp_enqueue_script(
			'wpicp-admin',
			WPICP_PLUGIN_URL . 'assets/js/admin.js',
			array( Wenpai_Admin_Loader::SCRIPT_HANDLE ),
			$ver,
			true
		);

		wp_localize_script(
			'wpicp-admin',
			'wpicpAdminData',
			array(
				'restScanSiteUrl'  => esc_url_raw( rest_url( 'wpicp/v1/ad-law/scan-site' ) ),
				'restAllowlistUrl' => esc_url_raw( rest_url( 'wpicp/v1/ad-law/allowlist' ) ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'i18n'             => array(
					'scanning'     => __( '正在扫描站点内容…', 'wpicp' ),
					'doneZero'     => __( '扫描完成，未发现《广告法》极限词风险。', 'wpicp' ),
					/* translators: %d: number of detected risk expressions */
					'doneHits'     => __( '扫描完成，共发现 %d 处风险表达。', 'wpicp' ),
					'failed'       => __( '巡检请求未完成，请稍后重试。', 'wpicp' ),
					'editPost'     => __( '直达编辑', 'wpicp' ),
					'addAllowlist' => __( '加入白名单', 'wpicp' ),
					'addedAllow'   => __( '已加入白名单', 'wpicp' ),
					'pickMedia'    => __( '选择媒体图片', 'wpicp' ),
					'useMedia'     => __( '使用此图片', 'wpicp' ),
				),
			)
		);
	}

	/**
	 * Add quick links on `plugins.php`.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function filter_action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$set_url   = self::tab_url( 'filing', false );
		$audit_url = self::tab_url( 'audit', false );
		$custom    = array(
			'settings' => '<a href="' . esc_url( $set_url ) . '">' . esc_html__( '设置', 'wpicp' ) . '</a>',
			'audit'    => '<a href="' . esc_url( $audit_url ) . '">' . esc_html__( '合规巡检', 'wpicp' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}

	/**
	 * Add quick links on Multisite Network `plugins.php`.
	 *
	 * @param string[] $links Existing network action links.
	 * @return string[]
	 */
	public static function filter_network_action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$set_url = self::tab_url( 'filing', true );
		$custom  = array(
			'settings' => '<a href="' . esc_url( $set_url ) . '">' . esc_html__( '网络设置', 'wpicp' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}

	/**
	 * Return settings framework instance for site or network context.
	 *
	 * @param bool $network Whether requesting network settings instance.
	 * @return Wenpai_Admin_Settings
	 */
	public static function get_settings_instance( $network = false ) {
		if ( $network ) {
			if ( ! self::$network_settings instanceof Wenpai_Admin_Settings ) {
				self::$network_settings = SettingsStore::register_framework( SettingsStore::OPTION_NETWORK );
			}
			return self::$network_settings;
		}
		if ( ! self::$site_settings instanceof Wenpai_Admin_Settings ) {
			self::$site_settings = SettingsStore::register_framework( SettingsStore::OPTION_SITE );
		}
		return self::$site_settings;
	}

	/**
	 * Normalize raw `?tab=` parameter, supporting `settings` as alias for `filing`.
	 *
	 * @param string $raw Raw tab slug.
	 * @return string
	 */
	public static function normalize_tab( $raw ) {
		$key = sanitize_key( (string) $raw );
		if ( 'settings' === $key ) {
			return 'filing';
		}
		$allowed = array( 'overview', 'filing', 'badges', 'display', 'audit', 'tools', 'help' );
		return in_array( $key, $allowed, true ) ? $key : 'overview';
	}

	/**
	 * Build URL for a tab in single-site or network admin.
	 *
	 * @param string               $tab        Tab id (`overview`, `filing`, `badges`, `display`, `audit`, `tools`, `help`).
	 * @param bool|null            $is_network Null auto-detects `is_network_admin()`.
	 * @param array<string, mixed> $extra      Optional extra query parameters.
	 * @return string
	 */
	public static function tab_url( $tab, $is_network = null, array $extra = array() ) {
		$tab = self::normalize_tab( $tab );
		if ( null === $is_network ) {
			$is_network = function_exists( 'is_network_admin' ) && is_network_admin();
		}
		$query = array_merge( array( 'tab' => $tab ), $extra );
		if ( 'overview' === $tab && empty( $extra ) ) {
			unset( $query['tab'] );
		}
		if ( $is_network && function_exists( 'network_admin_url' ) ) {
			return add_query_arg( array_merge( array( 'page' => 'wpicp' ), $query ), network_admin_url( 'settings.php' ) );
		}
		if ( class_exists( 'Wenpai_Admin_Menu', false ) ) {
			return Wenpai_Admin_Menu::url( 'wpicp', $query );
		}
		return add_query_arg( array_merge( array( 'page' => 'wpicp' ), $query ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Build shared `Wenpai_Admin_Shell::open` / `close` configuration with 6 compact tabs.
	 *
	 * @param string                $current  Active tab (`overview`, `filing`, `badges`, `display`, `audit`, `tools`, `help`).
	 * @param array<string, string> $tab_urls Optional offline prototype URL map.
	 * @return array<string, mixed>
	 */
	public static function shell_args( $current, array $tab_urls = array() ) {
		$current  = self::normalize_tab( $current );
		$display  = self::display_meta();
		$url_for  = static function ( $id ) use ( $tab_urls ) {
			return isset( $tab_urls[ $id ] ) ? $tab_urls[ $id ] : self::tab_url( $id );
		};
		$ico      = static function ( $name ) {
			return class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( $name ) : '';
		};
		$mark_svg = $ico( 'shield-check' );
		$ver      = defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0';

		return array(
			'slug'               => 'wpicp',
			'name'               => $display['brand'],
			'mark_html'          => $mark_svg,
			'home_url'           => $url_for( 'overview' ),
			'current'            => $current,
			'tabs'               => array(
				array(
					'id'        => 'overview',
					'label'     => __( '概览', 'wpicp' ),
					'url'       => $url_for( 'overview' ),
					'icon_html' => $ico( 'overview' ),
				),
				array(
					'id'        => 'filing',
					'label'     => __( '备案资质', 'wpicp' ),
					'url'       => $url_for( 'filing' ),
					'icon_html' => $ico( 'shield-check' ),
				),
				array(
					'id'        => 'badges',
					'label'     => __( '证照徽章', 'wpicp' ),
					'url'       => $url_for( 'badges' ),
					'icon_html' => $ico( 'image' ),
				),
				array(
					'id'        => 'display',
					'label'     => __( '前台展示', 'wpicp' ),
					'url'       => $url_for( 'display' ),
					'icon_html' => $ico( 'equalizer' ),
				),
				array(
					'id'        => 'audit',
					'label'     => __( '合规巡检', 'wpicp' ),
					'url'       => $url_for( 'audit' ),
					'icon_html' => $ico( 'search' ),
				),
				array(
					'id'        => 'tools',
					'label'     => __( '工具', 'wpicp' ),
					'url'       => $url_for( 'tools' ),
					'icon_html' => $ico( 'tools' ),
				),
			),
			'help_url'           => $url_for( 'help' ),
			'help_label'         => __( '帮助', 'wpicp' ),
			'help_icon_html'     => $ico( 'help' ),
			'feedback_url'       => $display['feedback_url'],
			'feedback_label'     => __( '反馈', 'wpicp' ),
			'feedback_icon_html' => $ico( 'feedback' ),
			'version'            => $display['plugin_name'] . ' ' . $ver,
			'doc_url'            => $display['docs_url'],
			'doc_label'          => __( '使用文档', 'wpicp' ),
			'links'              => array(
				array(
					'url'   => $display['help_url'],
					'label' => __( '产品主页', 'wpicp' ),
				),
			),
		);
	}

	/**
	 * Render the requested WPICP admin tab.
	 *
	 * @return void
	 */
	public static function render_page() {
		$is_network = function_exists( 'is_network_admin' ) && is_network_admin();
		$cap        = $is_network ? 'manage_network_options' : 'manage_options';
		if ( function_exists( 'current_user_can' ) && ! current_user_can( $cap ) ) {
			wp_die( esc_html__( '您没有权限访问此页面。', 'wpicp' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing.
		$tab = isset( $_GET['tab'] ) ? self::normalize_tab( wp_unslash( (string) $_GET['tab'] ) ) : 'overview';

		$settings   = self::get_settings_instance( $is_network );
		$shell      = self::shell_args( $tab );
		$display    = self::display_meta();
		$plugin_dir = defined( 'WPICP_PLUGIN_DIR' ) ? WPICP_PLUGIN_DIR : dirname( __DIR__, 2 ) . '/';

		require $plugin_dir . 'templates/admin/' . $tab . '.php';
	}
}
