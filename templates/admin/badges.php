<?php
/**
 * Admin Certificates & Trust Badges Tab (`tab=badges`).
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

$wpicp_base_url = isset( $shell['tabs'][2]['url'] ) ? (string) $shell['tabs'][2]['url'] : '#badges';
$wpicp_is_proto = false !== strpos( $wpicp_base_url, '.html' );

$wpicp_groups  = array(
	'presets'      => __( '官方预设徽章', 'wpicp' ),
	'custom'       => __( '自定义合作徽章', 'wpicp' ),
	'certificates' => __( '电子证照亮照', 'wpicp' ),
);
$wpicp_section = Wenpai_Admin_UI::section( $wpicp_groups, 'presets' );

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '证照徽章', 'wpicp' ),
	__( '管理页脚权威信任徽标与电子营业执照亮照。', 'wpicp' )
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'tone' => 'ok',
			'text' => __( '多站点网络默认证照与徽章设置已保存。', 'wpicp' ),
		)
	);
}

if ( $wpicp_subsite_locked ) {
	$wpicp_net_actions = array();
	if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_network_options' ) && function_exists( 'network_admin_url' ) ) {
		$wpicp_net_actions[] = array(
			'label'   => __( '前往网络管理中心修改', 'wpicp' ),
			'href'    => network_admin_url( 'settings.php?page=wpicp&tab=badges' ),
			'variant' => 'primary',
		);
	}
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '当前子站点继承多站点网络配置', 'wpicp' ),
			'icon'  => 'image',
		)
	);
	Wenpai_Admin_UI::notice(
		array(
			'tone'    => 'info',
			'text'    => __( '网络超级管理员已关闭子站点独立覆盖，本站自动沿用全网统一的证照与信任徽章。', 'wpicp' ),
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
echo $settings->tab_input_html( 'badges' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
?>
<input type="hidden" name="_wpicp_section" value="<?php echo esc_attr( $wpicp_section ); ?>">

<?php
$wpicp_subnav_items = array();
foreach ( $wpicp_groups as $wpicp_gid => $wpicp_glabel ) {
	$wpicp_subnav_items[] = array(
		'id'      => $wpicp_gid,
		'label'   => $wpicp_glabel,
		'url'     => $wpicp_is_proto
			? ( 'presets' === $wpicp_gid ? 'badges.html' : 'badges.html?section=' . $wpicp_gid )
			: add_query_arg( 'section', $wpicp_gid, remove_query_arg( 'section', $wpicp_base_url ) ),
		'current' => $wpicp_gid === $wpicp_section,
	);
}
Wenpai_Admin_UI::subnav(
	array(
		'label' => __( '证照与徽章分类', 'wpicp' ),
		'items' => $wpicp_subnav_items,
	)
);
?>

<div class="wenpai-stack" data-wenpai-groups data-wenpai-groups-default="presets">
	<div<?php echo Wenpai_Admin_UI::group_attrs( 'presets', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '官方监管与权威信任徽章', 'wpicp' ),
				'icon'  => 'image',
			)
		);
		$settings->render_field( 'selected_presets' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '勾选后即可在页脚「完整信任徽章栏」或「信任徽章」区块中输出。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存预设徽章', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'primary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<div<?php echo Wenpai_Admin_UI::group_attrs( 'custom', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '自定义合作与安全认证徽章', 'wpicp' ),
				'icon'  => 'link',
			)
		);
		$settings->render_field( 'badges' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '所有外部徽章跳转链接均自动强制升级为 HTTPS。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存自定义徽章', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'primary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<div<?php echo Wenpai_Admin_UI::group_attrs( 'certificates', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '电子营业执照与许可证书亮照', 'wpicp' ),
				'icon'  => 'shield-check',
			)
		);
		$settings->render_field( 'certificates' );
		$settings->render_field( 'certificate_click_mode' );
		$settings->render_field( 'certificates_page' );
		$settings->render_field( 'default_image_size' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '可通过「信任徽章与证照」区块或 [wpicp_certificates] 短代码展示画廊。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存证照设置', 'wpicp' ),
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
		'message'      => __( '有未保存的证照徽章更改', 'wpicp' ),
		'submit_label' => __( '保存设置', 'wpicp' ),
	)
);
?>
</form>
<?php
Wenpai_Admin_Shell::close( $shell );
