<?php
/**
 * Admin Filing & Entity Tab (`tab=filing`, with Simple / Advanced mode switch).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Admin\AdminPage;
use WenPai\ICP\Options\SettingsStore;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network     = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_is_ms          = function_exists( 'is_multisite' ) && is_multisite();
$wpicp_subsite_locked = $wpicp_is_ms && ! $wpicp_is_network && ! SettingsStore::allows_subsite_override();
$wpicp_option_name    = $settings->option();
$wpicp_licenses       = $settings->get( 'custom_licenses' );
if ( ! is_array( $wpicp_licenses ) ) {
	$wpicp_licenses = array();
}

$wpicp_mode     = Wenpai_Admin_UI::mode();
$wpicp_base_url = AdminPage::shell_tab_url( $shell, 'filing', '#filing' );
$wpicp_is_proto = false !== strpos( $wpicp_base_url, '.html' );
$wpicp_sim_url  = $wpicp_is_proto ? 'filing.html' : remove_query_arg( array( 'mode', 'section' ), $wpicp_base_url );
$wpicp_adv_url  = $wpicp_is_proto ? 'filing-advanced.html?mode=advanced' : add_query_arg( 'mode', 'advanced', remove_query_arg( 'section', $wpicp_base_url ) );

$wpicp_mode_switch = Wenpai_Admin_UI::mode_switch_html(
	array(
		'icon'  => 'equalizer',
		'wrap'  => false,
		'label' => __( '切换简单与高级备案视图', 'wpicp' ),
		'items' => array(
			array(
				'label'   => __( '简单', 'wpicp' ),
				'href'    => $wpicp_sim_url,
				'current' => 'simple' === $wpicp_mode,
			),
			array(
				'label'   => __( '高级', 'wpicp' ),
				'href'    => $wpicp_adv_url,
				'current' => 'advanced' === $wpicp_mode,
			),
		),
	)
);

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '备案资质', 'wpicp' ),
	__( '维护站点备案号、主办单位与行业行政许可。', 'wpicp' ),
	$wpicp_subsite_locked ? '' : $wpicp_mode_switch
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'level' => 'ok',
			'text'  => __( '多站点网络默认备案资质已保存。', 'wpicp' ),
		)
	);
}

if ( $wpicp_subsite_locked ) {
	$wpicp_net_actions = array();
	if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_network_options' ) && function_exists( 'network_admin_url' ) ) {
		$wpicp_net_actions[] = array(
			'label'   => __( '前往网络管理中心修改', 'wpicp' ),
			'href'    => network_admin_url( 'settings.php?page=wpicp&tab=filing' ),
			'variant' => 'primary',
		);
	}
	Wenpai_Admin_UI::next(
		array(
			'icon'    => 'shield-check',
			'title'   => __( '当前子站点继承多站点网络配置', 'wpicp' ),
			'desc'    => __( '网络超级管理员已关闭子站点独立覆盖，本站自动沿用全网统一的备案与证照设置。', 'wpicp' ),
			'actions' => $wpicp_net_actions,
		)
	);
	Wenpai_Admin_Shell::close( $shell );
	return;
}

$wpicp_form_action = $wpicp_is_network && function_exists( 'network_admin_url' )
	? network_admin_url( 'edit.php?action=wpicp_save_network' )
	: 'options.php';
?>
<form class="wenpai-stack" method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
<?php
if ( function_exists( 'settings_fields' ) ) {
	settings_fields( $settings->group() );
}
echo $settings->tab_input_html( 'filing' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
?>
<input type="hidden" name="_wpicp_mode" value="<?php echo esc_attr( $wpicp_mode ); ?>">

<?php
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '基础备案与主办单位', 'wpicp' ),
		'tile'  => false,
	)
);
$settings->render_field( 'icp_number' );
$settings->render_field( 'icp_site_name' );
$settings->render_field( 'province_abbr' );
$settings->render_field( 'public_security_code' );
$settings->render_field( 'company_legal_name' );
?>
<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	$settings->render_field( 'report_email' );
	$settings->render_field( 'report_phone' );
	?>
</div>
<div<?php echo Wenpai_Admin_UI::mode_attrs( 'simple' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	Wenpai_Admin_UI::card_foot(
		array(
			'start'   => true,
			'meta'    => __( '如需填写增值电信 EDI、生成式 AI 或行业行政许可，请切换右上角「高级」视图。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '保存备案资质', 'wpicp' ),
					'type'    => 'submit',
					'variant' => 'primary',
				),
			),
		)
	);
	?>
</div>
<?php
Wenpai_Admin_UI::card_close();
?>

<div class="wenpai-stack"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '增值电信、AI 算法与移动端备案', 'wpicp' ),
			'tile'  => false,
		)
	);
	$settings->render_field( 'edi_number' );
	$settings->render_field( 'aigc_number' );
	$settings->render_field( 'algorithm_number' );
	$settings->render_field( 'app_number' );
	$settings->render_field( 'miniapp_number' );
	Wenpai_Admin_UI::card_close();

	Wenpai_Admin_UI::card_open(
		array(
			'title'             => __( '扩展行业行政许可证', 'wpicp' ),
			'sub'               => __( '可按需补充网络文化、广播电视、出版物或互联网药品信息等行业许可证。', 'wpicp' ),
			'tile'              => false,
			'head_control_html' => Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '添加许可证', 'wpicp' ),
					'type'    => 'button',
					'variant' => 'secondary',
					'size'    => 'sm',
					'attrs'   => array( 'data-wpicp-add-license' => '1' ),
				)
			),
		)
	);
	?>
	<input type="hidden" name="<?php echo esc_attr( $wpicp_option_name ); ?>[custom_licenses][__sentinel]" value="">
	<div class="wpicp-editor" data-wpicp-license-rows data-option="<?php echo esc_attr( $wpicp_option_name ); ?>">
		<div class="wpicp-editor__head"<?php echo empty( $wpicp_licenses ) ? ' hidden' : ''; ?>>
			<span><?php esc_html_e( '证照名称', 'wpicp' ); ?></span>
			<span><?php esc_html_e( '许可证编号', 'wpicp' ); ?></span>
			<span><?php esc_html_e( '查验链接（选填）', 'wpicp' ); ?></span>
			<span><?php esc_html_e( '操作', 'wpicp' ); ?></span>
		</div>
		<?php foreach ( array_values( $wpicp_licenses ) as $wpicp_idx => $wpicp_lic ) : ?>
			<?php
			$wpicp_l_label = is_array( $wpicp_lic ) && isset( $wpicp_lic['label'] ) ? (string) $wpicp_lic['label'] : '';
			$wpicp_l_num   = is_array( $wpicp_lic ) && isset( $wpicp_lic['number'] ) ? (string) $wpicp_lic['number'] : '';
			$wpicp_l_url   = is_array( $wpicp_lic ) && isset( $wpicp_lic['url'] ) ? (string) $wpicp_lic['url'] : '';
			?>
			<div class="wpicp-editor__row" data-index="<?php echo esc_attr( (string) $wpicp_idx ); ?>">
				<input type="text" data-subkey="label" name="<?php echo esc_attr( $wpicp_option_name . '[custom_licenses][' . $wpicp_idx . '][label]' ); ?>" value="<?php echo esc_attr( $wpicp_l_label ); ?>" placeholder="<?php esc_attr_e( '网络文化经营许可证', 'wpicp' ); ?>" aria-label="<?php esc_attr_e( '证照名称', 'wpicp' ); ?>">
				<input type="text" data-subkey="number" name="<?php echo esc_attr( $wpicp_option_name . '[custom_licenses][' . $wpicp_idx . '][number]' ); ?>" value="<?php echo esc_attr( $wpicp_l_num ); ?>" placeholder="粤网文〔2025〕0001-001号" aria-label="<?php esc_attr_e( '许可证编号', 'wpicp' ); ?>">
				<input type="url" data-subkey="url" name="<?php echo esc_attr( $wpicp_option_name . '[custom_licenses][' . $wpicp_idx . '][url]' ); ?>" value="<?php echo esc_attr( $wpicp_l_url ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( '查验链接', 'wpicp' ); ?>">
				<div class="wpicp-editor__acts">
					<button type="button" class="btn btn-ghost btn-sm" data-wpicp-del-license><?php esc_html_e( '移除', 'wpicp' ); ?></button>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="wpicp-editor__empty" data-wpicp-license-empty<?php echo empty( $wpicp_licenses ) ? '' : ' hidden'; ?>>
		<div class="wpicp-editor__empty-main">
			<?php echo Wenpai_Admin_UI::tile_html( array( 'icon' => 'shield-check' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit HTML. ?>
			<div>
				<div class="wpicp-editor__empty-title"><?php esc_html_e( '暂未登记扩展行业行政许可证', 'wpicp' ); ?></div>
				<div class="wpicp-editor__empty-desc"><?php esc_html_e( '如站点涉及网络文化、出版物、广电或医疗器械信息服务，可点击右上角按钮逐条登记。', 'wpicp' ); ?></div>
			</div>
		</div>
		<?php
		$wpicp_add_lic_btn = Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '添加许可证', 'wpicp' ),
				'type'    => 'button',
				'variant' => 'secondary',
				'size'    => 'sm',
				'attrs'   => array( 'data-wpicp-add-license' => '1' ),
			)
		);
		echo $wpicp_add_lic_btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
		?>
	</div>
	<?php
	Wenpai_Admin_UI::card_foot(
		array(
			'start'   => true,
			'meta'    => __( '保存后自动同步 WordPress 常规备案项，未填写项目不在前台显示。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '保存全部备案与许可', 'wpicp' ),
					'type'    => 'submit',
					'variant' => 'primary',
				),
			),
		)
	);
	Wenpai_Admin_UI::card_close();
	?>
</div>

<?php
Wenpai_Admin_UI::savebar(
	array(
		'message'      => __( '有未保存的备案资质更改', 'wpicp' ),
		'submit_label' => __( '保存设置', 'wpicp' ),
	)
);
?>
</form>
<?php
Wenpai_Admin_Shell::close( $shell );
