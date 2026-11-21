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

$wpicp_adv_groups  = array(
	'basic'    => __( '基础备案与举报渠道', 'wpicp' ),
	'licenses' => __( '增值电信与算法备案', 'wpicp' ),
);
$wpicp_adv_section = Wenpai_Admin_UI::section( $wpicp_adv_groups, 'basic' );

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
			'tone' => 'ok',
			'text' => __( '多站点网络默认备案资质已保存。', 'wpicp' ),
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
echo $settings->tab_input_html( 'filing' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
?>
<input type="hidden" name="_wpicp_mode" value="<?php echo esc_attr( $wpicp_mode ); ?>">
<input type="hidden" name="_wpicp_section" value="<?php echo esc_attr( $wpicp_adv_section ); ?>">

<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
	<?php
	$wpicp_subnav_items = array();
	foreach ( $wpicp_adv_groups as $wpicp_gid => $wpicp_glabel ) {
		$wpicp_subnav_items[] = array(
			'id'      => $wpicp_gid,
			'label'   => $wpicp_glabel,
			'url'     => $wpicp_is_proto
				? ( 'basic' === $wpicp_gid ? 'filing-advanced.html?mode=advanced' : 'filing-advanced.html?mode=advanced&section=' . $wpicp_gid )
				: add_query_arg(
					array(
						'mode'    => 'advanced',
						'section' => $wpicp_gid,
					),
					$wpicp_base_url
				),
			'current' => $wpicp_gid === $wpicp_adv_section,
		);
	}
	Wenpai_Admin_UI::subnav(
		array(
			'label' => __( '高级备案资质分组', 'wpicp' ),
			'items' => $wpicp_subnav_items,
		)
	);
	?>
</div>

<div class="wenpai-stack" data-wenpai-groups data-wenpai-groups-default="basic">
	<div<?php echo 'advanced' === $wpicp_mode ? Wenpai_Admin_UI::group_attrs( 'basic', $wpicp_adv_section ) : ' data-wenpai-group="basic"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '基础备案与主办单位', 'wpicp' ),
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
			$settings->render_field( 'report_email' );
			$settings->render_field( 'report_phone' );
			?>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => 'simple' === $wpicp_mode
					? __( '如需填写增值电信 EDI、生成式 AI 或算法备案，请切换右上角「高级」视图。', 'wpicp' )
					: __( '保存后自动同步 WordPress 常规备案项与文派生态插件。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存备案资质', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'primary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</div>

	<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<div<?php echo Wenpai_Admin_UI::group_attrs( 'licenses', $wpicp_adv_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
			<?php
			Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '增值电信、AI 算法与扩展许可证', 'wpicp' ),
					'icon'  => 'cloud',
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
					'meta'    => __( '未填写的许可项目不会在前台合规栏中显示。', 'wpicp' ),
					'actions' => array(
						array(
							'label'   => __( '保存许可资质', 'wpicp' ),
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
