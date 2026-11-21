<?php
/**
 * Admin Maintenance & Tools Tab (`tab=tools`).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Migration\LegacyMigrator;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network    = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_legacy_tables = ! $wpicp_is_network ? LegacyMigrator::detect_deprecated_tables() : array();

$wpicp_form_action = $wpicp_is_network && function_exists( 'network_admin_url' )
	? network_admin_url( 'edit.php?action=wpicp_save_network' )
	: 'options.php';

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '工具', 'wpicp' ),
	__( '管理配置备份导入导出、旧版数据同步与恢复默认设置。', 'wpicp' )
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
	echo Wenpai_Admin_Action::notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
}
?>
<div class="wenpai-stack">
	<?php
	$settings->render_tools(
		array(
			'reset_note' => __( '重置仅恢复本插件默认选项，不会删除媒体库中已上传的证照图片。', 'wpicp' ),
		)
	);

	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '旧版配置同步', 'wpicp' ),
			'tile'  => false,
		)
	);
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
			Wenpai_Admin_Action::fields( 'wpicp_reimport_legacy' );
		}
		Wenpai_Admin_UI::row(
			array(
				'icon'         => 'refresh',
				'title'        => __( '从旧版插件或核心备案项重新同步', 'wpicp' ),
				'desc'         => __( '扫描旧版 WPICP License 遗留选项与 WordPress 常规设置中的备案号，合并补入当前配置。', 'wpicp' ),
				'control_html' => Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '重新同步旧版配置', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'secondary',
					)
				),
			)
		);
		?>
	</form>
	<?php
	Wenpai_Admin_UI::card_close();

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
	?>

	<form method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
		<?php
		if ( function_exists( 'settings_fields' ) ) {
			settings_fields( $settings->group() );
		}
		echo $settings->tab_input_html( 'tools' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '卸载数据策略', 'wpicp' ),
				'tile'  => false,
			)
		);
		$settings->render_field( 'clean_on_uninstall' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '默认停用或卸载插件时保留已有备案与证照配置。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存卸载策略', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'secondary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
