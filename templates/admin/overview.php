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
$wpicp_set_url     = isset( $shell['tabs'][1]['url'] ) ? (string) $shell['tabs'][1]['url'] : '#filing';
$wpicp_adv_url     = false !== strpos( $wpicp_set_url, '.html' )
	? str_replace( 'filing.html', 'filing-advanced.html?mode=advanced', $wpicp_set_url )
	: add_query_arg( 'mode', 'advanced', $wpicp_set_url );
$wpicp_display_url = isset( $shell['tabs'][3]['url'] ) ? (string) $shell['tabs'][3]['url'] : '#display';
$wpicp_audit_url   = isset( $shell['tabs'][4]['url'] ) ? (string) $shell['tabs'][4]['url'] : '#audit';

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '概览', 'wpicp' ),
	__( '查看备案完备度、前台预览与合规状态。', 'wpicp' )
);
?>
<div class="wenpai-stack">
<?php
if ( LegacyMigrator::consume_takeover_notice() ) {
	Wenpai_Admin_UI::notice(
		array(
			'tone' => 'ok',
			'text' => __( '已自动从旧版 WPICP License 无损迁移全部备案与证照配置并停用旧插件。', 'wpicp' ),
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
					'href'    => $wpicp_set_url,
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
	Wenpai_Admin_UI::notice(
		array(
			'tone'    => 'bad',
			'text'    => $wpicp_audit['summary'],
			'actions' => array(
				array(
					'label'   => __( '立即核对修正', 'wpicp' ),
					'href'    => $wpicp_set_url,
					'variant' => 'secondary',
				),
			),
		)
	);
} elseif ( $wpicp_scan_hits > 0 ) {
	Wenpai_Admin_UI::notice(
		array(
			'tone'    => 'warn',
			'text'    => sprintf(
				/* translators: %d: hit count */
				__( '最近一次《广告法》巡检发现 %d 处极限词风险表达，建议及时复核修订。', 'wpicp' ),
				$wpicp_scan_hits
			),
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
?>

<div class="wenpai-split wenpai-split--equal">
	<div>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '备案与资质完备度', 'wpicp' ),
				'icon'  => 'shield-check',
				'state' => 'bad' === $wpicp_audit['overall_state'] ? 'bad' : ( $wpicp_audit['is_empty'] ? 'warn' : 'ok' ),
			)
		);

		$wpicp_sync_count = 0;
		foreach ( $wpicp_audit['suite'] as $wpicp_s ) {
			if ( 'ok' === $wpicp_s['state'] ) {
				++$wpicp_sync_count;
			}
		}

		Wenpai_Admin_UI::statbar(
			array(
				'items' => array(
					array(
						'label' => __( '已登记资质', 'wpicp' ),
						'value' => (string) $wpicp_audit['filled_count'],
					),
					array(
						'label' => __( '证照与徽章', 'wpicp' ),
						'value' => (string) $wpicp_audit['badge_count'],
					),
					array(
						'label' => __( '巡检风险项', 'wpicp' ),
						'value' => $wpicp_has_scan ? (string) $wpicp_scan_hits : '0',
					),
					array(
						'label' => __( '协同通道', 'wpicp' ),
						'value' => (string) $wpicp_sync_count,
					),
				),
			)
		);

		$wpicp_icon_map = array(
			'icp'     => 'cloud',
			'mps'     => 'shield-check',
			'company' => 'user',
			'policy'  => 'info',
			'output'  => 'external',
		);

		foreach ( $wpicp_audit['checks'] as $wpicp_check ) {
			$wpicp_cid = $wpicp_check['id'];
			Wenpai_Admin_UI::row(
				array(
					'icon'  => isset( $wpicp_icon_map[ $wpicp_cid ] ) ? $wpicp_icon_map[ $wpicp_cid ] : 'info',
					'state' => $wpicp_check['state'],
					'title' => $wpicp_check['label'],
					'desc'  => $wpicp_check['detail'],
					'pill'  => array(
						'text'  => $wpicp_check['value'],
						'state' => $wpicp_check['state'],
					),
				)
			);
		}

		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => $wpicp_audit['summary'],
				'actions' => array(
					array(
						'label'   => __( '前往设置', 'wpicp' ),
						'href'    => $wpicp_set_url,
						'variant' => 'secondary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<div>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '文派生态同步与巡检摘要', 'wpicp' ),
				'icon'  => 'plug',
			)
		);

		$wpicp_status_rows = array();
		foreach ( $wpicp_audit['suite'] as $wpicp_item ) {
			$wpicp_status_rows[] = array(
				'label'       => $wpicp_item['label'],
				'value'       => $wpicp_item['value'],
				'state'       => $wpicp_item['state'],
				'state_label' => 'ok' === $wpicp_item['state'] ? __( '已连接', 'wpicp' ) : '',
			);
		}
		$wpicp_status_rows[] = array(
			'label'       => __( '离线《广告法》词库', 'wpicp' ),
			'value'       => ! empty( $wpicp_effective['ad_law_enabled'] ) ? __( '45 条规则就绪', 'wpicp' ) : __( '已关闭', 'wpicp' ),
			'state'       => ! empty( $wpicp_effective['ad_law_enabled'] ) ? 'ok' : 'warn',
			'state_label' => ! empty( $wpicp_effective['ad_law_enabled'] ) ? __( '启用', 'wpicp' ) : __( '停用', 'wpicp' ),
		);
		$wpicp_status_rows[] = array(
			'label'       => __( '最近全站巡检', 'wpicp' ),
			'value'       => $wpicp_has_scan
				? sprintf(
					/* translators: 1: scanned post count, 2: hit count */
					__( '已扫 %1$d 篇 · %2$d 处命中', 'wpicp' ),
					isset( $wpicp_scan_meta['scanned_posts'] ) ? (int) $wpicp_scan_meta['scanned_posts'] : 0,
					$wpicp_scan_hits
				)
				: __( '尚未执行全站扫描', 'wpicp' ),
			'state'       => $wpicp_scan_hits > 0 ? 'warn' : ( $wpicp_has_scan ? 'ok' : '' ),
			'state_label' => $wpicp_scan_hits > 0 ? __( '待复核', 'wpicp' ) : ( $wpicp_has_scan ? __( '合规', 'wpicp' ) : '' ),
		);

		Wenpai_Admin_UI::status_list( array( 'items' => $wpicp_status_rows ) );

		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '共享单点真源，避免多处重复填写。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '合规巡检', 'wpicp' ),
						'href'    => $wpicp_audit_url,
						'variant' => 'ghost',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>
</div>

<?php
$wpicp_view_tabs = Wenpai_Admin_UI::view_tabs_html(
	array(
		'group'      => 'wpicp-bar-preview',
		'aria_label' => __( '切换页脚合规栏预览版式', 'wpicp' ),
		'items'      => array(
			array(
				'id'     => 'standard',
				'label'  => __( '标准双行', 'wpicp' ),
				'active' => true,
			),
			array(
				'id'    => 'minimal',
				'label' => __( '极简单行', 'wpicp' ),
			),
			array(
				'id'    => 'badges',
				'label' => __( '含信任徽章', 'wpicp' ),
			),
		),
	)
);

Wenpai_Admin_UI::card_open(
	array(
		'title'             => __( '前台合规栏实时预览与快速调用', 'wpicp' ),
		'icon'              => 'eye',
		'head_control_html' => $wpicp_view_tabs,
	)
);

$wpicp_panes = array();
foreach ( array( 'standard', 'minimal', 'badges' ) as $wpicp_preset_key ) {
	$wpicp_bar_markup = Renderer::render_compliance_bar(
		array(
			'preset'        => $wpicp_preset_key,
			'demo_fallback' => true,
			'skip_enqueue'  => true,
		),
		$wpicp_effective
	);
	$wpicp_note       = $wpicp_audit['is_empty']
		? '<p class="meta">' . esc_html__( '当前展示示例预览效果，填写备案设置后将实时替换为本站真实数据。', 'wpicp' ) . '</p>'
		: '';
	$wpicp_panes[]    = array(
		'id'     => $wpicp_preset_key,
		'active' => 'standard' === $wpicp_preset_key,
		'html'   => '<div class="wpicp-preview"><div class="wpicp-preview__stage">' . $wpicp_bar_markup . '</div>' . $wpicp_note . '</div>',
	);
}

Wenpai_Admin_UI::view_panes(
	array(
		'group' => 'wpicp-bar-preview',
		'panes' => $wpicp_panes,
	)
);

$wpicp_copy_row  = '<div class="wenpai-row">';
$wpicp_copy_row .= '<span class="meta">' . esc_html__( '短代码调用：', 'wpicp' ) . '</span>';
$wpicp_copy_row .= Wenpai_Admin_UI::code_inline_html( array( 'code' => '[wpicp]' ) );
$wpicp_copy_row .= Wenpai_Admin_UI::code_inline_html( array( 'code' => '[wpicp field="icp"]' ) );
$wpicp_copy_row .= Wenpai_Admin_UI::code_inline_html( array( 'code' => '[wpicp field="mps"]' ) );
$wpicp_copy_row .= '</div>';

Wenpai_Admin_UI::card_foot(
	array(
		'start'     => true,
		'meta_html' => $wpicp_copy_row,
		'actions'   => array(
			array(
				'label'   => __( '配置页脚展示', 'wpicp' ),
				'href'    => $wpicp_display_url,
				'variant' => 'ghost',
			),
		),
	)
);
Wenpai_Admin_UI::card_close();
?>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
