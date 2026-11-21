<?php
/**
 * Admin Frontend Display Tab (`tab=display`).
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

$wpicp_base_url = isset( $shell['tabs'][3]['url'] ) ? (string) $shell['tabs'][3]['url'] : '#display';
$wpicp_is_proto = false !== strpos( $wpicp_base_url, '.html' );

$wpicp_groups  = array(
	'footer' => __( '页脚自动输出与版式', 'wpicp' ),
	'items'  => __( '显示条目与高级策略', 'wpicp' ),
);
$wpicp_section = Wenpai_Admin_UI::section( $wpicp_groups, 'footer' );

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '前台展示', 'wpicp' ),
	__( '配置页脚合规公示栏样式、显示条目与多站点策略。', 'wpicp' )
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'tone' => 'ok',
			'text' => __( '多站点网络默认前台展示设置已保存。', 'wpicp' ),
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
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '当前子站点继承多站点网络配置', 'wpicp' ),
			'icon'  => 'equalizer',
		)
	);
	Wenpai_Admin_UI::notice(
		array(
			'tone'    => 'info',
			'text'    => __( '网络超级管理员已关闭子站点独立覆盖，本站自动沿用全网统一的页脚合规栏展示策略。', 'wpicp' ),
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
echo $settings->tab_input_html( 'display' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
?>
<input type="hidden" name="_wpicp_section" value="<?php echo esc_attr( $wpicp_section ); ?>">

<?php
$wpicp_subnav_items = array();
foreach ( $wpicp_groups as $wpicp_gid => $wpicp_glabel ) {
	$wpicp_subnav_items[] = array(
		'id'      => $wpicp_gid,
		'label'   => $wpicp_glabel,
		'url'     => $wpicp_is_proto
			? ( 'footer' === $wpicp_gid ? 'display.html' : 'display.html?section=' . $wpicp_gid )
			: add_query_arg( 'section', $wpicp_gid, remove_query_arg( 'section', $wpicp_base_url ) ),
		'current' => $wpicp_gid === $wpicp_section,
	);
}
Wenpai_Admin_UI::subnav(
	array(
		'label' => __( '前台展示设置分组', 'wpicp' ),
		'items' => $wpicp_subnav_items,
	)
);
?>

<div class="wenpai-stack" data-wenpai-groups data-wenpai-groups-default="footer">
	<div class="wenpai-stack"<?php echo Wenpai_Admin_UI::group_attrs( 'footer', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '页脚合规公示栏版式', 'wpicp' ),
				'icon'  => 'equalizer',
			)
		);
		$settings->render_field( 'auto_footer' );
		$settings->render_field( 'footer_style' );
		$settings->render_field( 'footer_align' );
		$settings->render_field( 'footer_separator' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '全区块主题也可在页脚模板中直接插入「备案合规栏」区块。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存展示版式', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'primary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();

		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '当前版式效果预览', 'wpicp' ),
				'icon'  => 'eye',
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
		?>
	</div>

	<div<?php echo Wenpai_Admin_UI::group_attrs( 'items', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '合规栏显示条目与附加类名', 'wpicp' ),
				'icon'  => 'settings',
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
						'label'   => __( '保存显示条目', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'primary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>
</div>

<?php
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
