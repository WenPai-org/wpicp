<?php
/**
 * Admin Settings Tab (`tab=settings`, with Simple / Advanced mode switch).
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
$wpicp_base_url = isset( $shell['tabs'][1]['url'] ) ? (string) $shell['tabs'][1]['url'] : '#settings';
$wpicp_is_proto = false !== strpos( $wpicp_base_url, '.html' );
$wpicp_sim_url  = $wpicp_is_proto ? 'settings.html' : remove_query_arg( 'mode', $wpicp_base_url );
$wpicp_adv_url  = $wpicp_is_proto ? 'settings-advanced.html?mode=advanced' : add_query_arg( 'mode', 'advanced', $wpicp_base_url );

$wpicp_mode_switch = Wenpai_Admin_UI::mode_switch_html(
	array(
		'icon'  => 'equalizer',
		'wrap'  => false,
		'label' => __( '切换简单与高级设置视图', 'wpicp' ),
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
	__( '设置', 'wpicp' ),
	__( '集中维护备案资质、信任徽章与页脚展示。', 'wpicp' ),
	$wpicp_subsite_locked ? '' : $wpicp_mode_switch
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'tone' => 'ok',
			'text' => __( '多站点网络默认备案设置已保存。', 'wpicp' ),
		)
	);
}

if ( $wpicp_subsite_locked ) {
	$wpicp_net_actions = array();
	if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_network_options' ) && function_exists( 'network_admin_url' ) ) {
		$wpicp_net_actions[] = array(
			'label'   => __( '前往网络管理中心修改', 'wpicp' ),
			'href'    => network_admin_url( 'settings.php?page=wpicp&tab=settings' ),
			'variant' => 'primary',
		);
	}
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '当前子站点继承多站点网络配置', 'wpicp' ),
			'icon'  => 'shield-check',
		)
	);
	Wenpai_Admin_UI::notice(
		array(
			'tone'    => 'info',
			'text'    => __( '网络超级管理员已关闭子站点独立覆盖，本站自动沿用全网统一的备案与证照设置。', 'wpicp' ),
			'actions' => $wpicp_net_actions,
		)
	);
	Wenpai_Admin_UI::card_close();
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
echo $settings->tab_input_html( 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
?>
<input type="hidden" name="_wpicp_mode" value="<?php echo esc_attr( $wpicp_mode ); ?>">

<?php
// Card 1: Filing & Entity Identity.
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '备案与主体资质', 'wpicp' ),
		'icon'  => 'shield-check',
	)
);
$settings->render_field( 'icp_number' );
$settings->render_field( 'province_abbr' );
$settings->render_field( 'public_security_code' );
$settings->render_field( 'company_legal_name' );
?>
<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	$settings->render_field( 'edi_number' );
	$settings->render_field( 'aigc_number' );
	$settings->render_field( 'algorithm_number' );
	$settings->render_field( 'app_number' );
	$settings->render_field( 'miniapp_number' );
	$settings->render_field( 'report_email' );
	$settings->render_field( 'report_phone' );
	$settings->render_field( 'custom_licenses' );
	?>
</div>
<?php
Wenpai_Admin_UI::card_close();

// Card 2: Certificates & Trust Badges.
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '电子证照与信任徽章', 'wpicp' ),
		'icon'  => 'image',
	)
);
$settings->render_field( 'selected_presets' );
?>
<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	$settings->render_field( 'certificates' );
	$settings->render_field( 'certificate_click_mode' );
	$settings->render_field( 'certificates_page' );
	$settings->render_field( 'badges' );
	?>
</div>
<?php
Wenpai_Admin_UI::card_close();

// Card 3: Frontend Display & Multisite Policy.
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '前台展示与站点策略', 'wpicp' ),
		'icon'  => 'equalizer',
	)
);
$settings->render_field( 'auto_footer' );
$settings->render_field( 'footer_style' );
$settings->render_field( 'footer_align' );
?>
<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	$settings->render_field( 'footer_separator' );
	$settings->render_field( 'footer_items' );
	$settings->render_field( 'default_image_size' );
	$settings->render_field( 'custom_css_class' );
	if ( $wpicp_is_network ) {
		$settings->render_field( 'allow_subsite_override' );
	}
	$settings->render_field( 'clean_on_uninstall' );
	?>
</div>
<?php
Wenpai_Admin_UI::card_foot(
	array(
		'start'   => true,
		'meta'    => 'simple' === $wpicp_mode
			? __( '如需填写增值电信 EDI、生成式 AI 算法备案或电子证照，请切至右上角「高级」视图。', 'wpicp' )
			: __( '保存后自动双向同步 WordPress 核心备案项与文派生态插件。', 'wpicp' ),
		'actions' => array(
			array(
				'label'   => __( '保存设置', 'wpicp' ),
				'type'    => 'submit',
				'variant' => 'primary',
			),
		),
	)
);
Wenpai_Admin_UI::card_close();

Wenpai_Admin_UI::savebar(
	array(
		'message'      => __( '有未保存的备案设置更改', 'wpicp' ),
		'submit_label' => __( '保存设置', 'wpicp' ),
	)
);
?>
</form>
<?php
Wenpai_Admin_Shell::close( $shell );
