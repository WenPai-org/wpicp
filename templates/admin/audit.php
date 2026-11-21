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
use WenPai\ICP\Compliance\SiteHealthCheck;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network  = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_effective   = SettingsStore::effective();
$wpicp_audit       = SiteHealthCheck::audit( $wpicp_effective );
$wpicp_scan_meta   = get_option( AuditController::SUMMARY_OPTION, array() );
$wpicp_dict_count  = count( AdLawScanner::dictionary() );
$wpicp_safe_count  = count( AdLawScanner::safe_compounds() );
$wpicp_allow_count = is_array( $wpicp_effective['ad_law_allowlist'] ) ? count( $wpicp_effective['ad_law_allowlist'] ) : 0;
$wpicp_last_hits   = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['total_hits'] ) ? (int) $wpicp_scan_meta['total_hits'] : 0;

$wpicp_base_url = isset( $shell['tabs'][4]['url'] ) ? (string) $shell['tabs'][4]['url'] : '#audit';
$wpicp_is_proto = false !== strpos( $wpicp_base_url, '.html' );

$wpicp_groups = $wpicp_is_network
	? array(
		'rules' => __( '巡检规则与完备度核查', 'wpicp' ),
	)
	: array(
		'scanner' => __( '极限词全站扫描', 'wpicp' ),
		'rules'   => __( '巡检规则与完备度核查', 'wpicp' ),
	);

$wpicp_default_sec = $wpicp_is_network ? 'rules' : 'scanner';
$wpicp_section     = Wenpai_Admin_UI::section( $wpicp_groups, $wpicp_default_sec );

$wpicp_form_action = $wpicp_is_network && function_exists( 'network_admin_url' )
	? network_admin_url( 'edit.php?action=wpicp_save_network' )
	: 'options.php';

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '合规巡检', 'wpicp' ),
	__( '离线检测《广告法》极限词风险并核查备案完备度。', 'wpicp' )
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'tone' => 'ok',
			'text' => __( '多站点网络巡检规则已保存。', 'wpicp' ),
		)
	);
}
?>
<div class="wenpai-stack">
	<?php
	if ( count( $wpicp_groups ) > 1 ) {
		$wpicp_subnav_items = array();
		foreach ( $wpicp_groups as $wpicp_gid => $wpicp_glabel ) {
			$wpicp_subnav_items[] = array(
				'id'      => $wpicp_gid,
				'label'   => $wpicp_glabel,
				'url'     => $wpicp_is_proto
					? ( 'scanner' === $wpicp_gid ? 'audit.html' : 'audit.html?section=' . $wpicp_gid )
					: add_query_arg( 'section', $wpicp_gid, remove_query_arg( 'section', $wpicp_base_url ) ),
				'current' => $wpicp_gid === $wpicp_section,
			);
		}
		Wenpai_Admin_UI::subnav(
			array(
				'label' => __( '合规巡检子视图', 'wpicp' ),
				'items' => $wpicp_subnav_items,
			)
		);
	}
	?>

	<div class="wenpai-stack" data-wenpai-groups data-wenpai-groups-default="<?php echo esc_attr( $wpicp_default_sec ); ?>">
		<?php if ( ! $wpicp_is_network ) : ?>
			<div<?php echo Wenpai_Admin_UI::group_attrs( 'scanner', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
				<?php
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
			</div>
		<?php endif; ?>

		<div class="wenpai-stack"<?php echo Wenpai_Admin_UI::group_attrs( 'rules', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
			<form method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
				<?php
				if ( function_exists( 'settings_fields' ) ) {
					settings_fields( $settings->group() );
				}
				echo $settings->tab_input_html( 'audit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
				?>
				<input type="hidden" name="_wpicp_section" value="rules">
				<?php
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
			?>
		</div>
	</div>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
