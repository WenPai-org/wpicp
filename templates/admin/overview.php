<?php
/**
 * Admin Overview Tab (`tab=overview`).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Admin\AdminPage;
use WenPai\ICP\Compliance\AccessMonitor;
use WenPai\ICP\Compliance\SiteHealthCheck;
use WenPai\ICP\Frontend\Renderer;
use WenPai\ICP\Migration\LegacyMigrator;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network     = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_is_ms          = function_exists( 'is_multisite' ) && is_multisite();
$wpicp_subsite_locked = $wpicp_is_ms && ! $wpicp_is_network && ! SettingsStore::allows_subsite_override();
$wpicp_effective      = SettingsStore::effective();
$wpicp_mod_badges     = SettingsStore::module_enabled( 'module_badges', $wpicp_effective );
$wpicp_mod_access     = SettingsStore::module_enabled( 'module_access', $wpicp_effective );
$wpicp_mod_adlaw      = SettingsStore::module_enabled( 'module_adlaw', $wpicp_effective );
$wpicp_audit          = SiteHealthCheck::audit( $wpicp_effective );
$wpicp_access_snap    = AccessMonitor::last_status();
$wpicp_home_snap      = isset( $wpicp_access_snap['home'] ) && is_array( $wpicp_access_snap['home'] ) ? $wpicp_access_snap['home'] : array();
$wpicp_probe_snap     = isset( $wpicp_access_snap['keepalive'] ) && is_array( $wpicp_access_snap['keepalive'] ) ? $wpicp_access_snap['keepalive'] : array();
$wpicp_keepalive_on   = $wpicp_mod_access && ! empty( $wpicp_effective['keepalive_enabled'] ) && '' !== trim( (string) $wpicp_effective['keepalive_url'] );
$wpicp_scan_meta      = get_option( AuditController::SUMMARY_OPTION, array() );
$wpicp_scan_hits      = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['total_hits'] ) ? (int) $wpicp_scan_meta['total_hits'] : 0;
$wpicp_has_scan       = is_array( $wpicp_scan_meta ) && ! empty( $wpicp_scan_meta['last_scan_at'] );

$wpicp_filing_url  = AdminPage::shell_tab_url( $shell, 'filing', '#filing' );
$wpicp_badges_url  = AdminPage::shell_tab_url( $shell, 'badges', '#badges' );
$wpicp_display_url = AdminPage::shell_tab_url( $shell, 'display', '#display' );
$wpicp_audit_url   = AdminPage::shell_tab_url( $shell, 'audit', '#audit' );
$wpicp_tools_url   = AdminPage::shell_tab_url( $shell, 'tools', '#tools' );
$wpicp_help_url    = AdminPage::shell_tab_url( $shell, 'help', '#help' );
$wpicp_is_proto    = false !== strpos( $wpicp_filing_url, '.html' );
$wpicp_access_url  = $wpicp_is_proto ? 'audit.html' : add_query_arg( 'section', 'access', $wpicp_audit_url );
$wpicp_adlaw_url   = $wpicp_is_proto ? 'audit-adlaw.html' : add_query_arg( 'section', 'adlaw', $wpicp_audit_url );

$wpicp_channels = array();
foreach ( $wpicp_audit['suite'] as $wpicp_item ) {
	$wpicp_channels[] = array(
		'label' => $wpicp_item['label'],
		'value' => $wpicp_item['value'],
		'tone'  => 'ok' === $wpicp_item['state'] ? '' : 'warn',
	);
}

$wpicp_core_synced = isset( $wpicp_audit['suite'][0]['state'] ) && 'ok' === $wpicp_audit['suite'][0]['state'];
$wpicp_probe_bad   = $wpicp_keepalive_on && ! empty( $wpicp_probe_snap ) && empty( $wpicp_probe_snap['ok'] );

if ( $wpicp_audit['is_empty'] ) {
	$wpicp_lede = __( '尚未登记站点备案号，填写后将自动同步全站页脚并按需开启扩展功能。', 'wpicp' );
} elseif ( $wpicp_probe_bad ) {
	$wpicp_lede = __( '检测到接入商保活子域名异常，请及时核查 DNS 与服务可达性避免触发取消接入。', 'wpicp' );
} elseif ( 'bad' === $wpicp_audit['overall_state'] ) {
	$wpicp_lede = __( '检测到备案号格式异常，请核对后重新保存。', 'wpicp' );
} elseif ( $wpicp_mod_adlaw && $wpicp_scan_hits > 0 ) {
	$wpicp_lede = sprintf(
		/* translators: %d: hit count */
		__( '备案公示正常，最近一次《广告法》巡检发现 %d 处待复核表达。', 'wpicp' ),
		$wpicp_scan_hits
	);
} else {
	$wpicp_lede = __( '备案公示已启用，单点数据已同步至 WordPress 核心与文派生态。', 'wpicp' );
}

$wpicp_head_actions  = '<div class="wenpai-head-actions">';
$wpicp_head_actions .= Wenpai_Admin_UI::button_html(
	array(
		'label'   => __( '编辑备案', 'wpicp' ),
		'href'    => $wpicp_filing_url,
		'variant' => $wpicp_audit['is_empty'] ? 'primary' : 'secondary',
	)
);
if ( $wpicp_mod_access ) {
	$wpicp_head_actions .= Wenpai_Admin_UI::button_html(
		array(
			'label'   => __( '接入体检', 'wpicp' ),
			'href'    => $wpicp_access_url,
			'variant' => $wpicp_audit['is_empty'] ? 'secondary' : 'primary',
		)
	);
} elseif ( $wpicp_mod_adlaw ) {
	$wpicp_head_actions .= Wenpai_Admin_UI::button_html(
		array(
			'label'   => __( '广告法巡检', 'wpicp' ),
			'href'    => $wpicp_adlaw_url,
			'variant' => $wpicp_audit['is_empty'] ? 'secondary' : 'primary',
		)
	);
} else {
	$wpicp_head_actions .= Wenpai_Admin_UI::button_html(
		array(
			'label'   => __( '调整展示', 'wpicp' ),
			'href'    => $wpicp_display_url,
			'variant' => $wpicp_audit['is_empty'] ? 'secondary' : 'primary',
		)
	);
}
$wpicp_head_actions .= '</div>';

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '概览', 'wpicp' ),
	$wpicp_lede,
	$wpicp_head_actions,
	Wenpai_Admin_UI::drawer_toggle_html(
		array(
			'target'     => 'wpicp-suite-drawer',
			'label'      => __( '生态协同', 'wpicp' ),
			'open_label' => __( '收起协同', 'wpicp' ),
		)
	)
);
?>
<div class="wenpai-stack">
<?php
if ( LegacyMigrator::consume_takeover_notice() ) {
	Wenpai_Admin_UI::notice(
		array(
			'level' => 'ok',
			'text'  => __( '已自动从旧版 WPICP License 无损迁移全部备案与证照配置并停用旧插件。', 'wpicp' ),
		)
	);
}

if ( $wpicp_audit['is_empty'] ) {
	Wenpai_Admin_UI::next(
		array(
			'icon'    => 'shield-check',
			'title'   => __( '尚未登记站点备案与主体资质', 'wpicp' ),
			'desc'    => __( '一处填写 ICP 备案号、公安联网备案号与主体全称，自动同步全站页脚与文派生态；证照、保活与巡检功能可在下方按需开启。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '填写备案信息', 'wpicp' ),
					'href'    => $wpicp_filing_url,
					'variant' => 'primary',
				),
				array(
					'label'   => __( '调整展示版式', 'wpicp' ),
					'href'    => $wpicp_display_url,
					'variant' => 'ghost',
				),
			),
		)
	);
} elseif ( $wpicp_probe_bad ) {
	Wenpai_Admin_UI::next(
		array(
			'state'   => 'bad',
			'icon'    => 'info',
			'title'   => __( '接入商保活子域名检测异常，存在取消接入风险', 'wpicp' ),
			'desc'    => isset( $wpicp_probe_snap['summary'] ) ? (string) $wpicp_probe_snap['summary'] : __( '请核查保活子域名 DNS 解析与页面可达性。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '查看接入核查详情', 'wpicp' ),
					'href'    => $wpicp_access_url,
					'variant' => 'secondary',
				),
			),
		)
	);
} elseif ( 'bad' === $wpicp_audit['overall_state'] ) {
	Wenpai_Admin_UI::next(
		array(
			'state'   => 'bad',
			'icon'    => 'info',
			'title'   => __( '备案编号格式存在异常', 'wpicp' ),
			'desc'    => $wpicp_audit['summary'],
			'actions' => array(
				array(
					'label'   => __( '立即核对修正', 'wpicp' ),
					'href'    => $wpicp_filing_url,
					'variant' => 'secondary',
				),
			),
		)
	);
} elseif ( $wpicp_mod_adlaw && $wpicp_scan_hits > 0 ) {
	Wenpai_Admin_UI::next(
		array(
			'state'   => 'warn',
			'icon'    => 'search',
			'title'   => sprintf(
				/* translators: %d: hit count */
				__( '全站巡检发现 %d 处《广告法》风险表达', 'wpicp' ),
				$wpicp_scan_hits
			),
			'desc'    => __( '建议前往合规巡检页复核修订，或将专有名词加入豁免白名单。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '查看巡检详情', 'wpicp' ),
					'href'    => $wpicp_adlaw_url,
					'variant' => 'secondary',
				),
			),
		)
	);
}

// Compute stat_hub values (honest & quiet when modules are disabled or unconfigured).
if ( ! $wpicp_mod_access ) {
	$wpicp_home_val  = __( '未开启', 'wpicp' );
	$wpicp_home_unit = __( '按需启用', 'wpicp' );
	$wpicp_keep_val  = __( '未开启', 'wpicp' );
	$wpicp_keep_unit = __( '按需启用', 'wpicp' );
} else {
	$wpicp_home_val  = __( '未检', 'wpicp' );
	$wpicp_home_unit = '';
	if ( ! empty( $wpicp_home_snap ) && isset( $wpicp_home_snap['passed'], $wpicp_home_snap['total'] ) ) {
		$wpicp_home_val  = ! empty( $wpicp_home_snap['ok'] ) ? __( '通过', 'wpicp' ) : __( '待修', 'wpicp' );
		$wpicp_home_unit = (int) $wpicp_home_snap['passed'] . '/' . (int) $wpicp_home_snap['total'];
	}

	$wpicp_keep_val  = __( '未开启', 'wpicp' );
	$wpicp_keep_unit = __( '主站直连', 'wpicp' );
	if ( $wpicp_keepalive_on ) {
		if ( empty( $wpicp_probe_snap ) ) {
			$wpicp_keep_val  = __( '待首次探测', 'wpicp' );
			$wpicp_keep_unit = AccessMonitor::interval_label( (string) $wpicp_effective['keepalive_interval'] );
		} elseif ( ! empty( $wpicp_probe_snap['ok'] ) ) {
			$wpicp_keep_val  = __( '正常', 'wpicp' );
			$wpicp_keep_unit = '200 OK';
		} else {
			$wpicp_keep_val  = __( '异常', 'wpicp' );
			$wpicp_keep_unit = isset( $wpicp_probe_snap['result_label'] ) ? (string) $wpicp_probe_snap['result_label'] : '';
		}
	}
}

$wpicp_adlaw_stat_val  = ! $wpicp_mod_adlaw
	? __( '未开启', 'wpicp' )
	: ( $wpicp_has_scan ? (string) $wpicp_scan_hits : __( '未执行', 'wpicp' ) );
$wpicp_adlaw_stat_unit = ! $wpicp_mod_adlaw
	? __( '按需启用', 'wpicp' )
	: ( $wpicp_has_scan ? __( '处', 'wpicp' ) : '' );

Wenpai_Admin_UI::stat_hub(
	array(
		'drawer_id' => 'wpicp-suite-drawer',
		'stats'     => array(
			array(
				'label' => __( '已登记资质', 'wpicp' ),
				'value' => (string) $wpicp_audit['filled_count'],
				'unit'  => __( '项', 'wpicp' ),
			),
			array(
				'label' => __( '首页备案自检', 'wpicp' ),
				'value' => $wpicp_home_val,
				'unit'  => $wpicp_home_unit,
			),
			array(
				'label' => __( '接入商保活', 'wpicp' ),
				'value' => $wpicp_keep_val,
				'unit'  => $wpicp_keep_unit,
			),
			array(
				'label' => __( '广告法风险', 'wpicp' ),
				'value' => $wpicp_adlaw_stat_val,
				'unit'  => $wpicp_adlaw_stat_unit,
			),
		),
		'alert'     => array(
			'visible'      => ! $wpicp_audit['is_empty'] && ! $wpicp_core_synced,
			'text'         => __( 'WordPress 核心常规备案项尚未与本站配置同步', 'wpicp' ),
			'action_label' => __( '展开查看', 'wpicp' ),
		),
		'channels'  => $wpicp_channels,
	)
);

// Optional feature module switches —开启后再在顶栏显示对应标签页并激活后台与前台能力.
$wpicp_cert_count   = is_array( $wpicp_effective['certificates'] ) ? count( $wpicp_effective['certificates'] ) : 0;
$wpicp_badge_total  = $wpicp_audit['badge_count'];
$wpicp_mod_switch   = static function ( $mod_key, $checked, $title_label ) use ( $wpicp_subsite_locked ) {
	$sw = Wenpai_Admin_Settings::switch_html(
		array(
			'id'       => 'wpicp-switch-' . str_replace( '_', '-', $mod_key ),
			'checked'  => (bool) $checked,
			'disabled' => (bool) $wpicp_subsite_locked,
		)
	);
	return '<span data-wpicp-module-toggle="' . esc_attr( $mod_key ) . '" data-wpicp-module-label="' . esc_attr( $title_label ) . '">' . $sw . '</span>';
};
$wpicp_module_cards = array(
	array(
		'title'          => __( '证照与徽章', 'wpicp' ),
		'desc'           => __( '官方预设信任徽标、自定义徽章、电子证照水印亮照与前台合集页。', 'wpicp' ),
		'icon'           => 'image',
		'tone'           => $wpicp_mod_badges ? 'accent' : 'mute',
		'control_html'   => $wpicp_mod_switch( 'module_badges', $wpicp_mod_badges, __( '证照与徽章', 'wpicp' ) ),
		'status'         => $wpicp_mod_badges
			? sprintf(
				/* translators: 1: badge count, 2: certificate count */
				__( '已开启 · %1$d 枚徽章 / %2$d 份证照', 'wpicp' ),
				$wpicp_badge_total,
				$wpicp_cert_count
			)
			: __( '未开启 · 顶栏隐藏「证照」', 'wpicp' ),
		'status_tone'    => $wpicp_mod_badges ? 'ok' : 'mute',
		'action_label'   => $wpicp_mod_badges ? __( '配置', 'wpicp' ) : '',
		'action_url'     => $wpicp_mod_badges ? $wpicp_badges_url : '',
		'action_variant' => 'ghost',
	),
	array(
		'title'          => __( '备案与接入保活', 'wpicp' ),
		'desc'           => __( '首页备案定时自检、接入商 DNS/IP 核查与子域名低频保活访问。', 'wpicp' ),
		'icon'           => 'shield-check',
		'tone'           => $wpicp_mod_access ? ( $wpicp_probe_bad ? 'bad' : 'accent' ) : 'mute',
		'control_html'   => $wpicp_mod_switch( 'module_access', $wpicp_mod_access, __( '备案与接入保活', 'wpicp' ) ),
		'status'         => $wpicp_mod_access
			? ( $wpicp_probe_bad ? __( '已开启 · 保活异常', 'wpicp' ) : ( $wpicp_keepalive_on ? __( '已开启 · 首页自检与保活', 'wpicp' ) : __( '已开启 · 首页定时自检', 'wpicp' ) ) )
			: __( '未开启 · 零后台定时探测', 'wpicp' ),
		'status_tone'    => $wpicp_mod_access ? ( $wpicp_probe_bad ? 'bad' : 'ok' ) : 'mute',
		'action_label'   => $wpicp_mod_access ? __( '配置', 'wpicp' ) : '',
		'action_url'     => $wpicp_mod_access ? $wpicp_access_url : '',
		'action_variant' => 'ghost',
	),
	array(
		'title'          => __( '广告法极限词巡检', 'wpicp' ),
		'desc'           => __( '离线极限词检查、编辑器发布前提示、全站扫描与无言远程词库同步。', 'wpicp' ),
		'icon'           => 'search',
		'tone'           => $wpicp_mod_adlaw ? ( $wpicp_scan_hits > 0 ? 'warn' : 'accent' ) : 'mute',
		'control_html'   => $wpicp_mod_switch( 'module_adlaw', $wpicp_mod_adlaw, __( '广告法极限词巡检', 'wpicp' ) ),
		'status'         => $wpicp_mod_adlaw
			? ( $wpicp_has_scan
				? sprintf(
					/* translators: %d: hit count */
					__( '已开启 · 发现 %d 处待复核', 'wpicp' ),
					$wpicp_scan_hits
				)
				: __( '已开启 · 编辑器与全站巡检', 'wpicp' ) )
			: __( '未开启 · 零编辑器负载', 'wpicp' ),
		'status_tone'    => $wpicp_mod_adlaw ? ( $wpicp_scan_hits > 0 ? 'warn' : 'ok' ) : 'mute',
		'action_label'   => $wpicp_mod_adlaw ? __( '配置', 'wpicp' ) : '',
		'action_url'     => $wpicp_mod_adlaw ? $wpicp_adlaw_url : '',
		'action_variant' => 'ghost',
	),
);

Wenpai_Admin_UI::module_grid(
	array(
		'cols'  => 3,
		'items' => $wpicp_module_cards,
	)
);
?>

<div class="wenpai-split wenpai-split--equal">
<?php
// Left equal-height card: Homepage filing & provider compliance feed.
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '首页备案与接入合规体检', 'wpicp' ),
		'tile'  => false,
		'class' => 'wenpai-card--equal',
	)
);
echo '<div class="wenpai-card__body">';

$wpicp_feed_items = array();
$wpicp_check_urls = array(
	'icp'         => $wpicp_filing_url,
	'mps'         => $wpicp_filing_url,
	'company'     => $wpicp_filing_url,
	'policy'      => $wpicp_filing_url,
	'output'      => $wpicp_display_url,
	'cert_expiry' => $wpicp_is_proto ? 'badges-certificates.html' : add_query_arg( 'section', 'certificates', $wpicp_badges_url ),
);

foreach ( $wpicp_audit['checks'] as $wpicp_check ) {
	$wpicp_state = (string) $wpicp_check['state'];
	$wpicp_cid   = (string) $wpicp_check['id'];
	$wpicp_entry = array(
		'tag'   => 'ok' === $wpicp_state
			? __( '正常', 'wpicp' )
			: ( 'bad' === $wpicp_state ? __( '异常', 'wpicp' ) : __( '待补', 'wpicp' ) ),
		'tone'  => 'ok' === $wpicp_state ? 'ok' : ( 'bad' === $wpicp_state ? 'bad' : 'warn' ),
		'title' => $wpicp_check['label'],
	);
	if ( 'ok' === $wpicp_state ) {
		$wpicp_entry['when'] = $wpicp_check['value'];
	} else {
		$wpicp_entry['note']         = $wpicp_check['detail'];
		$wpicp_entry['action_label'] = 'bad' === $wpicp_state ? __( '修正', 'wpicp' ) : __( '完善', 'wpicp' );
		$wpicp_entry['action_url']   = isset( $wpicp_check_urls[ $wpicp_cid ] ) ? $wpicp_check_urls[ $wpicp_cid ] : $wpicp_filing_url;
	}
	$wpicp_feed_items[] = $wpicp_entry;
}

if ( $wpicp_keepalive_on ) {
	if ( ! empty( $wpicp_probe_snap ) ) {
		$wpicp_ok           = ! empty( $wpicp_probe_snap['ok'] );
		$wpicp_feed_items[] = array(
			'tag'          => $wpicp_ok ? __( '正常', 'wpicp' ) : __( '异常', 'wpicp' ),
			'tone'         => $wpicp_ok ? 'ok' : 'bad',
			'title'        => sprintf(
				/* translators: %s: cloud provider label */
				__( '接入商保活（%s）', 'wpicp' ),
				AccessMonitor::provider_label( (string) $wpicp_effective['access_provider'] )
			),
			'when'         => $wpicp_ok ? (string) $wpicp_probe_snap['primary_ip'] : '',
			'note'         => $wpicp_ok ? '' : (string) $wpicp_probe_snap['summary'],
			'action_label' => $wpicp_ok ? '' : __( '处理', 'wpicp' ),
			'action_url'   => $wpicp_ok ? '' : $wpicp_access_url,
		);
	} else {
		$wpicp_feed_items[] = array(
			'tag'          => __( '待检', 'wpicp' ),
			'tone'         => 'warn',
			'title'        => __( '接入商子域名保活探测', 'wpicp' ),
			'note'         => __( '已配置保活地址，尚未执行首次探测。', 'wpicp' ),
			'action_label' => __( '检测', 'wpicp' ),
			'action_url'   => $wpicp_access_url,
		);
	}
}

Wenpai_Admin_UI::tag_feed( array( 'items' => array_slice( $wpicp_feed_items, 0, 6 ) ) );

echo '</div>';
Wenpai_Admin_UI::card_foot(
	array(
		'start'   => true,
		'meta'    => ( $wpicp_mod_access && ! empty( $wpicp_access_snap['checked_at'] ) )
			? sprintf(
				/* translators: %s: timestamp */
				__( '上次首页/接入检测：%s', 'wpicp' ),
				(string) $wpicp_access_snap['checked_at']
			)
			: $wpicp_audit['summary'],
		'actions' => array(
			array(
				'label'   => $wpicp_mod_access ? __( '进入接入保活', 'wpicp' ) : __( '编辑备案信息', 'wpicp' ),
				'href'    => $wpicp_mod_access ? $wpicp_access_url : $wpicp_filing_url,
				'variant' => 'ghost',
				'size'    => 'sm',
			),
		),
	)
);
Wenpai_Admin_UI::card_close();

// Right equal-height card: Quick navigation grid & copyable shortcodes switcher.
$wpicp_right_tabs = Wenpai_Admin_UI::view_tabs_html(
	array(
		'group'      => 'wpicp-quick-box',
		'aria_label' => __( '切换快捷入口与短代码', 'wpicp' ),
		'items'      => array(
			array(
				'id'         => 'shortcuts',
				'label'      => __( '快捷入口', 'wpicp' ),
				'active'     => true,
				'card_title' => __( '快捷入口', 'wpicp' ),
			),
			array(
				'id'         => 'shortcodes',
				'label'      => __( '调用代码', 'wpicp' ),
				'card_title' => __( '短代码调用', 'wpicp' ),
			),
		),
	)
);

Wenpai_Admin_UI::card_open(
	array(
		'title'             => __( '快捷入口', 'wpicp' ),
		'tile'              => false,
		'class'             => 'wenpai-card--equal',
		'head_control_html' => $wpicp_right_tabs,
	)
);
echo '<div class="wenpai-card__body">';

$wpicp_shortcut_items = array(
	array(
		'icon'  => 'shield-check',
		'label' => __( '备案资质', 'wpicp' ),
		'href'  => $wpicp_filing_url,
		'state' => ( 'bad' === $wpicp_audit['overall_state'] || $wpicp_audit['is_empty'] ) ? 'warn' : 'ok',
	),
	array(
		'icon'  => 'equalizer',
		'label' => __( '前台展示', 'wpicp' ),
		'href'  => $wpicp_display_url,
	),
);
if ( $wpicp_mod_badges ) {
	$wpicp_shortcut_items[] = array(
		'icon'  => 'image',
		'label' => __( '证照徽章', 'wpicp' ),
		'href'  => $wpicp_badges_url,
		'badge' => $wpicp_audit['badge_count'] > 0 ? (string) $wpicp_audit['badge_count'] : '',
	);
}
if ( $wpicp_mod_access || $wpicp_mod_adlaw ) {
	$wpicp_shortcut_items[] = array(
		'icon'  => 'search',
		'label' => __( '合规巡检', 'wpicp' ),
		'href'  => $wpicp_mod_access ? $wpicp_access_url : $wpicp_adlaw_url,
		'state' => ( ( $wpicp_mod_adlaw && $wpicp_scan_hits > 0 ) || $wpicp_probe_bad ) ? 'warn' : ( $wpicp_mod_adlaw && $wpicp_has_scan ? 'ok' : '' ),
	);
}
if ( count( $wpicp_shortcut_items ) < 4 ) {
	$wpicp_shortcut_items[] = array(
		'icon'  => 'tools',
		'label' => __( '工具迁移', 'wpicp' ),
		'href'  => $wpicp_tools_url,
	);
}
if ( count( $wpicp_shortcut_items ) < 4 ) {
	$wpicp_shortcut_items[] = array(
		'icon'  => 'help',
		'label' => __( '使用帮助', 'wpicp' ),
		'href'  => $wpicp_help_url,
	);
}

$wpicp_shortcuts_html = Wenpai_Admin_UI::icon_grid_html(
	array(
		'columns' => 2,
		'items'   => $wpicp_shortcut_items,
	)
);

$wpicp_third_code_label = $wpicp_mod_badges ? __( '信任徽章与电子证照画廊', 'wpicp' ) : __( '仅输出公安联网备案号', 'wpicp' );
$wpicp_third_code_val   = $wpicp_mod_badges ? '[wpicp_certificates]' : '[wpicp field="mps"]';
$wpicp_shortcodes_html  = '<div class="wpicp-preview__code">'
	. '<div class="wpicp-preview__code-item"><span class="meta">' . esc_html__( '完整页脚合规栏', 'wpicp' ) . '</span>'
	. Wenpai_Admin_UI::code_inline_html(
		array(
			'code'   => '[wpicp]',
			'copy'   => true,
			'button' => array( 'label' => __( '复制', 'wpicp' ) ),
		)
	)
	. '</div>'
	. '<div class="wpicp-preview__code-item"><span class="meta">' . esc_html__( '仅输出 ICP 备案号', 'wpicp' ) . '</span>'
	. Wenpai_Admin_UI::code_inline_html(
		array(
			'code'   => '[wpicp field="icp"]',
			'copy'   => true,
			'button' => array( 'label' => __( '复制', 'wpicp' ) ),
		)
	)
	. '</div>'
	. '<div class="wpicp-preview__code-item"><span class="meta">' . esc_html( $wpicp_third_code_label ) . '</span>'
	. Wenpai_Admin_UI::code_inline_html(
		array(
			'code'   => $wpicp_third_code_val,
			'copy'   => true,
			'button' => array( 'label' => __( '复制', 'wpicp' ) ),
		)
	)
	. '</div>'
	. '</div>';

Wenpai_Admin_UI::view_panes(
	array(
		'group' => 'wpicp-quick-box',
		'panes' => array(
			array(
				'id'     => 'shortcuts',
				'active' => true,
				'html'   => $wpicp_shortcuts_html,
			),
			array(
				'id'   => 'shortcodes',
				'html' => $wpicp_shortcodes_html,
			),
		),
	)
);

echo '</div>';
Wenpai_Admin_UI::card_foot(
	array(
		'start'   => true,
		'meta'    => __( '支持区块编辑器与经典短代码调用', 'wpicp' ),
		'actions' => array(
			array(
				'label'   => __( '帮助文档', 'wpicp' ),
				'href'    => $wpicp_help_url,
				'variant' => 'ghost',
				'size'    => 'sm',
			),
		),
	)
);
Wenpai_Admin_UI::card_close();
?>
</div>

<?php
// Row 3: Live Frontend Compliance Bar Preview (matches saved footer_style).
$wpicp_active_style = in_array( (string) $wpicp_effective['footer_style'], array( 'minimal', 'standard', 'badges' ), true )
	? (string) $wpicp_effective['footer_style']
	: 'standard';

$wpicp_style_labels = array(
	'minimal'  => __( '极简单行', 'wpicp' ),
	'standard' => __( '标准双行', 'wpicp' ),
	'badges'   => __( '含信任徽章', 'wpicp' ),
);

$wpicp_view_tabs = Wenpai_Admin_UI::view_tabs_html(
	array(
		'group'      => 'wpicp-bar-preview',
		'aria_label' => __( '切换页脚合规栏预览版式', 'wpicp' ),
		'items'      => array(
			array(
				'id'     => 'minimal',
				'label'  => $wpicp_style_labels['minimal'],
				'active' => 'minimal' === $wpicp_active_style,
			),
			array(
				'id'     => 'standard',
				'label'  => $wpicp_style_labels['standard'],
				'active' => 'standard' === $wpicp_active_style,
			),
			array(
				'id'     => 'badges',
				'label'  => $wpicp_style_labels['badges'],
				'active' => 'badges' === $wpicp_active_style,
			),
		),
	)
);

Wenpai_Admin_UI::card_open(
	array(
		'title'             => __( '前台合规栏实时预览', 'wpicp' ),
		'tile'              => false,
		'head_control_html' => $wpicp_view_tabs,
	)
);

$wpicp_panes = array();
foreach ( array( 'minimal', 'standard', 'badges' ) as $wpicp_preset_key ) {
	$wpicp_bar_markup = Renderer::render_compliance_bar(
		array(
			'preset'        => $wpicp_preset_key,
			'align'         => (string) $wpicp_effective['footer_align'],
			'separator'     => (string) $wpicp_effective['footer_separator'],
			'demo_fallback' => true,
			'skip_enqueue'  => true,
		),
		$wpicp_effective
	);
	$wpicp_note       = $wpicp_audit['is_empty']
		? '<p class="meta">' . esc_html__( '当前展示示例效果，填写备案信息后将自动替换为本站真实数据。', 'wpicp' ) . '</p>'
		: '';
	$wpicp_panes[]    = array(
		'id'     => $wpicp_preset_key,
		'active' => $wpicp_preset_key === $wpicp_active_style,
		'html'   => '<div class="wpicp-preview"><div class="wpicp-preview__stage">' . $wpicp_bar_markup . '</div>' . $wpicp_note . '</div>',
	);
}

Wenpai_Admin_UI::view_panes(
	array(
		'group' => 'wpicp-bar-preview',
		'panes' => $wpicp_panes,
	)
);

$wpicp_preview_meta = sprintf(
	/* translators: 1: style label, 2: auto output status */
	__( '默认版式：%1$s · %2$s', 'wpicp' ),
	$wpicp_style_labels[ $wpicp_active_style ],
	! empty( $wpicp_effective['auto_footer'] ) ? __( '页脚自动输出已开启', 'wpicp' ) : __( '手动区块或短代码输出', 'wpicp' )
);

Wenpai_Admin_UI::card_foot(
	array(
		'start'   => true,
		'meta'    => $wpicp_preview_meta,
		'actions' => array(
			array(
				'label'   => __( '调整展示版式', 'wpicp' ),
				'href'    => $wpicp_display_url,
				'variant' => 'ghost',
				'size'    => 'sm',
			),
		),
	)
);
Wenpai_Admin_UI::card_close();
?>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
