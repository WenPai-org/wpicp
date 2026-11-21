<?php
/**
 * Admin Compliance Audit Tab (`tab=audit`).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Compliance\AdLawScanner;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network  = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_effective   = SettingsStore::effective();
$wpicp_scan_meta   = get_option( AuditController::SUMMARY_OPTION, array() );
$wpicp_dict_count  = count( AdLawScanner::dictionary() );
$wpicp_safe_count  = count( AdLawScanner::safe_compounds() );
$wpicp_allow_count = is_array( $wpicp_effective['ad_law_allowlist'] ) ? count( $wpicp_effective['ad_law_allowlist'] ) : 0;
$wpicp_last_hits   = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['total_hits'] ) ? (int) $wpicp_scan_meta['total_hits'] : 0;

$wpicp_form_action = $wpicp_is_network && function_exists( 'network_admin_url' )
	? network_admin_url( 'edit.php?action=wpicp_save_network' )
	: 'options.php';

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '合规巡检', 'wpicp' ),
	__( '离线检测《广告法》极限词风险并管理豁免白名单。', 'wpicp' )
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'level' => 'ok',
			'text'  => __( '多站点网络巡检规则已保存。', 'wpicp' ),
		)
	);
}
?>
<div class="wenpai-stack">
	<?php if ( ! $wpicp_is_network ) : ?>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '全站《广告法》极限词分批巡检', 'wpicp' ),
				'tile'  => false,
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
				'meta'    => __( '命中项支持一键直达编辑页修订，或将专有名词加入豁免白名单。', 'wpicp' ),
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
				'tile'  => false,
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
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
