<?php
/**
 * Admin Overview Tab (`tab=overview`).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Compliance\SiteHealthCheck;
use WenPai\ICP\Frontend\Renderer;
use WenPai\ICP\Migration\LegacyMigrator;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

defined( 'ABSPATH' ) || exit;

$wpicp_effective   = SettingsStore::effective();
$wpicp_audit       = SiteHealthCheck::audit( $wpicp_effective );
$wpicp_scan_meta   = get_option( AuditController::SUMMARY_OPTION, array() );
$wpicp_scan_hits   = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['total_hits'] ) ? (int) $wpicp_scan_meta['total_hits'] : 0;
$wpicp_has_scan    = is_array( $wpicp_scan_meta ) && ! empty( $wpicp_scan_meta['last_scan_at'] );
$wpicp_filing_url  = isset( $shell['tabs'][1]['url'] ) ? (string) $shell['tabs'][1]['url'] : '#filing';
$wpicp_badges_url  = isset( $shell['tabs'][2]['url'] ) ? (string) $shell['tabs'][2]['url'] : '#badges';
$wpicp_display_url = isset( $shell['tabs'][3]['url'] ) ? (string) $shell['tabs'][3]['url'] : '#display';
$wpicp_audit_url   = isset( $shell['tabs'][4]['url'] ) ? (string) $shell['tabs'][4]['url'] : '#audit';
$wpicp_help_url    = isset( $shell['help_url'] ) ? (string) $shell['help_url'] : '#help';
$wpicp_adv_url     = false !== strpos( $wpicp_filing_url, '.html' )
	? str_replace( 'filing.html', 'filing-advanced.html?mode=advanced', $wpicp_filing_url )
	: add_query_arg( 'mode', 'advanced', $wpicp_filing_url );

$wpicp_sync_count = 0;
$wpicp_channels   = array();
foreach ( $wpicp_audit['suite'] as $wpicp_item ) {
	if ( 'ok' === $wpicp_item['state'] ) {
		++$wpicp_sync_count;
	}
	$wpicp_channels[] = array(
		'label' => $wpicp_item['label'],
		'value' => $wpicp_item['value'],
		'tone'  => 'ok' === $wpicp_item['state'] ? '' : 'warn',
	);
}

$wpicp_core_synced = isset( $wpicp_audit['suite'][0]['state'] ) && 'ok' === $wpicp_audit['suite'][0]['state'];

if ( $wpicp_audit['is_empty'] ) {
	$wpicp_lede = __( '尚未登记站点备案号，填写后将自动同步全站页脚与文派生态。', 'wpicp' );
} elseif ( 'bad' === $wpicp_audit['overall_state'] ) {
	$wpicp_lede = __( '检测到备案号格式异常，请核对后重新保存。', 'wpicp' );
} elseif ( $wpicp_scan_hits > 0 ) {
	$wpicp_lede = sprintf(
		/* translators: %d: hit count */
		__( '备案公示已启用，最近一次巡检发现 %d 处《广告法》风险表达。', 'wpicp' ),
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
		'variant' => 'secondary',
	)
);
$wpicp_head_actions .= Wenpai_Admin_UI::button_html(
	array(
		'label'   => __( '合规巡检', 'wpicp' ),
		'href'    => $wpicp_audit_url,
		'variant' => $wpicp_audit['is_empty'] ? 'secondary' : 'primary',
	)
);
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
			'desc'    => __( '一处填写 ICP 备案号、公安联网备案号与主体全称，自动同步全站页脚与文派生态插件。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '填写备案信息', 'wpicp' ),
					'href'    => $wpicp_filing_url,
					'variant' => 'primary',
				),
				array(
					'label'   => __( '高级资质设置', 'wpicp' ),
					'href'    => $wpicp_adv_url,
					'variant' => 'ghost',
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
} elseif ( $wpicp_scan_hits > 0 ) {
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
					'href'    => $wpicp_audit_url,
					'variant' => 'secondary',
				),
			),
		)
	);
}

// Row 1: Silent-when-healthy 4-col Stat Hub + Expandable 4-col Ecosystem Sync Drawer.
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
				'label' => __( '证照与徽章', 'wpicp' ),
				'value' => (string) $wpicp_audit['badge_count'],
				'unit'  => __( '枚', 'wpicp' ),
			),
			array(
				'label' => __( '巡检风险项', 'wpicp' ),
				'value' => $wpicp_has_scan ? (string) $wpicp_scan_hits : '0',
				'unit'  => __( '处', 'wpicp' ),
			),
			array(
				'label' => __( '协同通道', 'wpicp' ),
				'value' => (string) $wpicp_sync_count,
				'unit'  => __( '个', 'wpicp' ),
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

// Row 2: Live Frontend Compliance Bar Preview (matches saved footer_style).
$wpicp_active_style = in_array( (string) $wpicp_effective['footer_style'], array( 'standard', 'minimal', 'badges' ), true )
	? (string) $wpicp_effective['footer_style']
	: 'standard';

$wpicp_style_labels = array(
	'standard' => __( '标准双行', 'wpicp' ),
	'minimal'  => __( '极简单行', 'wpicp' ),
	'badges'   => __( '含信任徽章', 'wpicp' ),
);

$wpicp_view_tabs = Wenpai_Admin_UI::view_tabs_html(
	array(
		'group'      => 'wpicp-bar-preview',
		'aria_label' => __( '切换页脚合规栏预览版式', 'wpicp' ),
		'items'      => array(
			array(
				'id'     => 'standard',
				'label'  => $wpicp_style_labels['standard'],
				'active' => 'standard' === $wpicp_active_style,
			),
			array(
				'id'     => 'minimal',
				'label'  => $wpicp_style_labels['minimal'],
				'active' => 'minimal' === $wpicp_active_style,
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
foreach ( array( 'standard', 'minimal', 'badges' ) as $wpicp_preset_key ) {
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

<div class="wenpai-split wenpai-split--equal">
<?php
// Left equal-height card: Compliance checklist rendered as calm micro-tag feed.
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '备案与公示完备度', 'wpicp' ),
		'tile'  => false,
		'class' => 'wenpai-card--equal',
	)
);
echo '<div class="wenpai-card__body">';

$wpicp_feed_items = array();
$wpicp_check_urls = array(
	'icp'     => $wpicp_filing_url,
	'mps'     => $wpicp_filing_url,
	'company' => $wpicp_filing_url,
	'policy'  => $wpicp_filing_url,
	'output'  => $wpicp_display_url,
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

Wenpai_Admin_UI::tag_feed( array( 'items' => $wpicp_feed_items ) );

echo '</div>';
Wenpai_Admin_UI::card_foot(
	array(
		'start'   => true,
		'meta'    => $wpicp_audit['summary'],
		'actions' => array(
			array(
				'label'   => __( '核对资质', 'wpicp' ),
				'href'    => $wpicp_filing_url,
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

$wpicp_shortcuts_html = Wenpai_Admin_UI::icon_grid_html(
	array(
		'columns' => 2,
		'items'   => array(
			array(
				'icon'  => 'shield-check',
				'label' => __( '备案资质', 'wpicp' ),
				'href'  => $wpicp_filing_url,
				'state' => 'bad' === $wpicp_audit['overall_state'] ? 'warn' : ( $wpicp_audit['is_empty'] ? 'warn' : 'ok' ),
			),
			array(
				'icon'  => 'image',
				'label' => __( '证照徽章', 'wpicp' ),
				'href'  => $wpicp_badges_url,
				'badge' => $wpicp_audit['badge_count'] > 0 ? (string) $wpicp_audit['badge_count'] : '',
			),
			array(
				'icon'  => 'equalizer',
				'label' => __( '前台展示', 'wpicp' ),
				'href'  => $wpicp_display_url,
			),
			array(
				'icon'  => 'search',
				'label' => __( '合规巡检', 'wpicp' ),
				'href'  => $wpicp_audit_url,
				'state' => $wpicp_scan_hits > 0 ? 'warn' : ( $wpicp_has_scan ? 'ok' : '' ),
			),
		),
	)
);

$wpicp_shortcodes_html = '<div class="wpicp-code">'
	. '<div class="wpicp-code__item"><span class="meta">' . esc_html__( '完整页脚合规栏', 'wpicp' ) . '</span>'
	. Wenpai_Admin_UI::code_inline_html(
		array(
			'code'   => '[wpicp]',
			'copy'   => true,
			'button' => array( 'label' => __( '复制', 'wpicp' ) ),
		)
	)
	. '</div>'
	. '<div class="wpicp-code__item"><span class="meta">' . esc_html__( '仅输出 ICP 备案号', 'wpicp' ) . '</span>'
	. Wenpai_Admin_UI::code_inline_html(
		array(
			'code'   => '[wpicp field="icp"]',
			'copy'   => true,
			'button' => array( 'label' => __( '复制', 'wpicp' ) ),
		)
	)
	. '</div>'
	. '<div class="wpicp-code__item"><span class="meta">' . esc_html__( '信任徽章与证照列表', 'wpicp' ) . '</span>'
	. Wenpai_Admin_UI::code_inline_html(
		array(
			'code'   => '[wpicp_badges]',
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
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
