<?php
/**
 * Admin Filing & Entity Tab (`tab=filing`, with Simple / Advanced mode switch).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Options\SettingsStore;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network     = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_is_ms          = function_exists( 'is_multisite' ) && is_multisite();
$wpicp_subsite_locked = $wpicp_is_ms && ! $wpicp_is_network && ! SettingsStore::allows_subsite_override();

$wpicp_mode     = Wenpai_Admin_UI::mode();
$wpicp_base_url = isset( $shell['tabs'][1]['url'] ) ? (string) $shell['tabs'][1]['url'] : '#filing';
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
			'meta'    => __( '如需填写增值电信 EDI、生成式 AI 或算法备案，请切换右上角「高级」视图。', 'wpicp' ),
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

<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '增值电信、AI 算法与扩展许可证', 'wpicp' ),
			'tile'  => false,
		)
	);
	$settings->render_field( 'edi_number' );
	$settings->render_field( 'aigc_number' );
	$settings->render_field( 'algorithm_number' );
	$settings->render_field( 'app_number' );
	$settings->render_field( 'miniapp_number' );
	$settings->render_field( 'custom_licenses' );
	Wenpai_Admin_UI::card_foot(
		array(
			'start'   => true,
			'meta'    => __( '保存后自动同步 WordPress 常规备案项，未填写项目不在前台显示。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '保存备案与许可资质', 'wpicp' ),
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
