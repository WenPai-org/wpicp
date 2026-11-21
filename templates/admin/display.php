<?php
/**
 * Admin Frontend Display Tab (`tab=display`, with Simple / Advanced mode switch).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Frontend\Renderer;
use WenPai\ICP\Options\SettingsStore;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network     = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_is_ms          = function_exists( 'is_multisite' ) && is_multisite();
$wpicp_subsite_locked = $wpicp_is_ms && ! $wpicp_is_network && ! SettingsStore::allows_subsite_override();
$wpicp_effective      = SettingsStore::effective();

$wpicp_mode     = Wenpai_Admin_UI::mode();
$wpicp_base_url = isset( $shell['tabs'][3]['url'] ) ? (string) $shell['tabs'][3]['url'] : '#display';
$wpicp_is_proto = false !== strpos( $wpicp_base_url, '.html' );
$wpicp_sim_url  = $wpicp_is_proto ? 'display.html' : remove_query_arg( array( 'mode', 'section' ), $wpicp_base_url );
$wpicp_adv_url  = $wpicp_is_proto ? 'display.html?mode=advanced' : add_query_arg( 'mode', 'advanced', remove_query_arg( 'section', $wpicp_base_url ) );

$wpicp_mode_switch = Wenpai_Admin_UI::mode_switch_html(
	array(
		'icon'  => 'equalizer',
		'wrap'  => false,
		'label' => __( '切换简单与高级展示设置', 'wpicp' ),
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
	__( '前台展示', 'wpicp' ),
	__( '配置页脚合规公示栏样式、显示条目与多站点策略。', 'wpicp' ),
	$wpicp_subsite_locked ? '' : $wpicp_mode_switch
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'level' => 'ok',
			'text'  => __( '多站点网络默认前台展示设置已保存。', 'wpicp' ),
		)
	);
}

if ( $wpicp_subsite_locked ) {
	$wpicp_net_actions = array();
	if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_network_options' ) && function_exists( 'network_admin_url' ) ) {
		$wpicp_net_actions[] = array(
			'label'   => __( '前往网络管理中心修改', 'wpicp' ),
			'href'    => network_admin_url( 'settings.php?page=wpicp&tab=display' ),
			'variant' => 'primary',
		);
	}
	Wenpai_Admin_UI::next(
		array(
			'icon'    => 'equalizer',
			'title'   => __( '当前子站点继承多站点网络配置', 'wpicp' ),
			'desc'    => __( '网络超级管理员已关闭子站点独立覆盖，本站自动沿用全网统一的页脚合规栏展示策略。', 'wpicp' ),
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
echo $settings->tab_input_html( 'display' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
?>
<input type="hidden" name="_wpicp_mode" value="<?php echo esc_attr( $wpicp_mode ); ?>">

<?php
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '页脚合规公示栏版式', 'wpicp' ),
		'tile'  => false,
	)
);
$settings->render_field( 'auto_footer' );
$settings->render_field( 'footer_style' );
$settings->render_field( 'footer_align' );
$settings->render_field( 'footer_separator' );
?>
<div<?php echo Wenpai_Admin_UI::mode_attrs( 'simple' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	Wenpai_Admin_UI::card_foot(
		array(
			'start'   => true,
			'meta'    => __( '如需精细勾选页脚条目或设置自定义类名，请切换右上角「高级」视图。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '保存展示版式', 'wpicp' ),
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
			'title' => __( '合规栏显示条目与附加类名', 'wpicp' ),
			'tile'  => false,
		)
	);
	$settings->render_field( 'footer_items' );
	$settings->render_field( 'custom_css_class' );
	if ( $wpicp_is_network ) {
		$settings->render_field( 'allow_subsite_override' );
	}
	Wenpai_Admin_UI::card_foot(
		array(
			'start'   => true,
			'meta'    => __( '仅已填写具体编号或内容的条目会在前台合规栏中渲染。', 'wpicp' ),
			'actions' => array(
				array(
					'label'   => __( '保存展示设置', 'wpicp' ),
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
Wenpai_Admin_UI::card_open(
	array(
		'title' => __( '当前版式效果预览', 'wpicp' ),
		'tile'  => false,
	)
);
$wpicp_preview_html = Renderer::render_compliance_bar(
	array(
		'preset'        => (string) $wpicp_effective['footer_style'],
		'align'         => (string) $wpicp_effective['footer_align'],
		'separator'     => (string) $wpicp_effective['footer_separator'],
		'demo_fallback' => true,
		'skip_enqueue'  => true,
	),
	$wpicp_effective
);
?>
<div class="wpicp-preview"><div class="wpicp-preview__stage"><?php echo $wpicp_preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Renderer::render_compliance_bar. ?></div></div>
<?php
Wenpai_Admin_UI::card_close();

Wenpai_Admin_UI::savebar(
	array(
		'message'      => __( '有未保存的前台展示更改', 'wpicp' ),
		'submit_label' => __( '保存设置', 'wpicp' ),
	)
);
?>
</form>
<?php
Wenpai_Admin_Shell::close( $shell );
