<?php
/**
 * Admin Compliance Audit & Tools Tab (`tab=audit`).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Compliance\AdLawScanner;
use WenPai\ICP\Compliance\SiteHealthCheck;
use WenPai\ICP\Migration\LegacyMigrator;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network    = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_effective     = SettingsStore::effective();
$wpicp_audit         = SiteHealthCheck::audit( $wpicp_effective );
$wpicp_scan_meta     = get_option( AuditController::SUMMARY_OPTION, array() );
$wpicp_dict_count    = count( AdLawScanner::dictionary() );
$wpicp_safe_count    = count( AdLawScanner::safe_compounds() );
$wpicp_allow_count   = is_array( $wpicp_effective['ad_law_allowlist'] ) ? count( $wpicp_effective['ad_law_allowlist'] ) : 0;
$wpicp_last_hits     = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['total_hits'] ) ? (int) $wpicp_scan_meta['total_hits'] : 0;
$wpicp_legacy_tables = ! $wpicp_is_network ? LegacyMigrator::detect_deprecated_tables() : array();

$wpicp_form_action = $wpicp_is_network && function_exists( 'network_admin_url' )
	? network_admin_url( 'edit.php?action=wpicp_save_network' )
	: 'options.php';

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '合规巡检', 'wpicp' ),
	__( '离线检测极限词风险，管理备份与迁移。', 'wpicp' )
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
	echo Wenpai_Admin_Action::notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
}
?>
<div class="wenpai-stack">
	<?php if ( ! $wpicp_is_network ) : ?>
		<?php
		// Card 1: Full-site Offline Ad-Law Batch Scanner.
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '全站《广告法》极限词分批巡检', 'wpicp' ),
				'icon'  => 'search',
				'attrs' => array(
					'data-wpicp-scanner' => '1',
				),
			)
		);

		Wenpai_Admin_UI::statbar(
			array(
				'items' => array(
					array(
						'label' => __( '内置违禁表达', 'wpicp' ),
						'value' => (string) $wpicp_dict_count,
					),
					array(
						'label' => __( '安全复合词', 'wpicp' ),
						'value' => (string) $wpicp_safe_count,
					),
					array(
						'label' => __( '自定义白名单', 'wpicp' ),
						'value' => (string) $wpicp_allow_count,
					),
					array(
						'label' => __( '上次命中风险', 'wpicp' ),
						'value' => (string) $wpicp_last_hits,
					),
				),
			)
		);
		?>
		<div class="wpicp-audit__progress" data-wpicp-scan-progress hidden>
			<?php
			Wenpai_Admin_UI::progressbar(
				array(
					'value' => 0,
					'label' => __( '全站巡检进度', 'wpicp' ),
				)
			);
			?>
		</div>
		<div class="wpicp-audit__results" data-wpicp-scan-results>
			<?php
			Wenpai_Admin_UI::empty_state(
				array(
					'icon'   => 'shield-check',
					'text'   => __( '本地毫秒级离线扫描，内容无需发往外部接口', 'wpicp' ),
					'meta'   => __( '自动剥离脚本与样式代码，扫描文章、页面及站点副标题中的绝对化极限词、无法证实承诺与违规权威背书。', 'wpicp' ),
					'action' => array(
						'label'   => __( '开始全站巡检', 'wpicp' ),
						'variant' => 'secondary',
						'attrs'   => array(
							'data-wpicp-scan-start' => '1',
						),
					),
				)
			);
			?>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '命中项支持一键直达编辑页修订，或将专有名词加入下方豁免白名单。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '重新扫描全站', 'wpicp' ),
						'variant' => 'ghost',
						'attrs'   => array(
							'data-wpicp-scan-start' => '1',
						),
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
		<?php
		if ( function_exists( 'settings_fields' ) ) {
			settings_fields( $settings->group() );
		}
		echo $settings->tab_input_html( 'audit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '《广告法》巡检规则与白名单配置', 'wpicp' ),
				'icon'  => 'shield-check',
			)
		);
		$settings->render_field( 'ad_law_enabled' );
		$settings->render_field( 'ad_law_editor_panel' );
		$settings->render_field( 'ad_law_scan_post_types' );
		$settings->render_field( 'ad_law_allowlist' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '内置白名单已默认豁免「第一时间」「第一步」「唯一标识」等 30 个常规复合词。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存巡检配置', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'primary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>

	<?php
	// Card 3: Site Compliance Checklist.
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '站点备案与公示完备度核查', 'wpicp' ),
			'icon'  => 'info',
		)
	);
	$wpicp_check_rows = array();
	foreach ( $wpicp_audit['checks'] as $wpicp_chk ) {
		$wpicp_check_rows[] = array(
			'label'       => $wpicp_chk['label'],
			'value'       => $wpicp_chk['value'],
			'state'       => $wpicp_chk['state'],
			'state_label' => 'ok' === $wpicp_chk['state']
				? __( '规范', 'wpicp' )
				: ( 'bad' === $wpicp_chk['state'] ? __( '格式异常', 'wpicp' ) : __( '待完善', 'wpicp' ) ),
			'desc'        => $wpicp_chk['detail'],
		);
	}
	Wenpai_Admin_UI::status_list( array( 'items' => $wpicp_check_rows ) );
	Wenpai_Admin_UI::card_close();

	Wenpai_Admin_UI::section_title(
		array(
			'title' => __( '数据迁移、导入导出与重置', 'wpicp' ),
			'desc'  => __( '无损同步旧版配置、导出备份 JSON 或恢复出厂默认设置。', 'wpicp' ),
		)
	);

	// Legacy `wpicp-license` manual re-sync card.
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '从旧版插件或核心备案项重新同步', 'wpicp' ),
			'sub'   => __( '重新扫描旧版 WPICP License 遗留选项与 WordPress 常规设置中的备案号，合并补入当前配置。', 'wpicp' ),
			'icon'  => 'refresh',
		)
	);
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
			Wenpai_Admin_Action::fields( 'wpicp_reimport_legacy' );
		}
		Wenpai_Admin_UI::card_foot(
			array(
				'actions' => array(
					array(
						'label'   => __( '重新同步旧版配置', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'secondary',
					),
				),
			)
		);
		?>
	</form>
	<?php
	Wenpai_Admin_UI::card_close();

	// Conditional 2025 deprecated tables cleanup card (only shown when detected!).
	if ( ! empty( $wpicp_legacy_tables ) ) {
		$wpicp_tbl_names = array();
		foreach ( $wpicp_legacy_tables as $wpicp_tbl ) {
			$wpicp_tbl_names[] = $wpicp_tbl['name'] . '（' . (int) $wpicp_tbl['rows'] . '）';
		}
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '清理 2025 早期版本遗留数据表', 'wpicp' ),
				'sub'   => __( '检测到早期测试版遗留的自建表（WPICP 4.0 采用零自定义表设计）：', 'wpicp' ) . implode( '、', $wpicp_tbl_names ),
				'icon'  => 'tools',
				'state' => 'warn',
			)
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
				Wenpai_Admin_Action::fields( 'wpicp_drop_legacy_tables' );
			}
			Wenpai_Admin_UI::gate(
				array(
					'name'    => 'wenpai_confirm',
					'label'   => __( '我已确认上述旧测试表无需保留，立即移除这些废弃表', 'wpicp' ),
					'text_id' => 'wpicp-drop-tables-gate',
				)
			);
			Wenpai_Admin_UI::card_foot(
				array(
					'actions' => array(
						array(
							'label'      => __( '清理废弃数据表', 'wpicp' ),
							'type'       => 'submit',
							'variant'    => 'danger',
							'need_apply' => true,
							'attrs'      => array( 'aria-describedby' => 'wpicp-drop-tables-gate' ),
						),
					),
				)
			);
			?>
		</form>
		<?php
		Wenpai_Admin_UI::card_close();
	}

	// Kit built-in JSON Export / Import / Reset cards.
	$settings->render_tools(
		array(
			'reset_note' => __( '重置仅恢复本插件默认选项，不会删除媒体库中已上传的证照图片。', 'wpicp' ),
		)
	);
	?>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
