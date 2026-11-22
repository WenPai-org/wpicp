<?php
/**
 * Admin Settings Tab (`tab=settings`, sections: `filing` | `display` | `modules`).
 *
 * Unifies core filing numbers, frontend display settings, and optional feature module
 * toggles under one top-level tab while keeping Overview strictly a status dashboard.
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Admin\AdminPage;
use WenPai\ICP\Compliance\AccessMonitor;
use WenPai\ICP\Compliance\SiteHealthCheck;
use WenPai\ICP\Frontend\Renderer;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network     = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_is_ms          = function_exists( 'is_multisite' ) && is_multisite();
$wpicp_subsite_locked = $wpicp_is_ms && ! $wpicp_is_network && ! SettingsStore::allows_subsite_override();
$wpicp_option_name    = $settings->option();
$wpicp_effective      = SettingsStore::effective();

$wpicp_sections = array(
	'filing'  => __( '备案', 'wpicp' ),
	'display' => __( '展示', 'wpicp' ),
	'modules' => __( '功能', 'wpicp' ),
);

// Respect legacy `?tab=display` / `?tab=modules` deep links when `?section=` is omitted.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing hint.
$wpicp_raw_tab_hint = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
$wpicp_default_sec  = in_array( $wpicp_raw_tab_hint, array( 'display', 'modules' ), true ) ? $wpicp_raw_tab_hint : 'filing';
$wpicp_section      = Wenpai_Admin_UI::section( $wpicp_sections, $wpicp_default_sec );
$wpicp_mode         = Wenpai_Admin_UI::mode();

$wpicp_head_control = '';
if ( ! $wpicp_subsite_locked && in_array( $wpicp_section, array( 'filing', 'display' ), true ) ) {
	$wpicp_sim_url      = AdminPage::settings_section_url( $shell, $wpicp_section, 'simple' );
	$wpicp_adv_url      = AdminPage::settings_section_url( $shell, $wpicp_section, 'advanced' );
	$wpicp_head_control = Wenpai_Admin_UI::mode_switch_html(
		array(
			'icon'  => 'equalizer',
			'wrap'  => false,
			'label' => 'filing' === $wpicp_section
				? __( '切换简单与高级备案视图', 'wpicp' )
				: __( '切换简单与高级展示设置', 'wpicp' ),
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
}

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '设置', 'wpicp' ),
	__( '统一管理站点备案资质、页脚合规栏展示与按需功能开关。', 'wpicp' ),
	$wpicp_head_control
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'level' => 'ok',
			'text'  => __( '多站点网络默认备案设置已保存。', 'wpicp' ),
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
	Wenpai_Admin_UI::next(
		array(
			'icon'    => 'settings',
			'title'   => __( '当前子站点继承多站点网络配置', 'wpicp' ),
			'desc'    => __( '网络超级管理员已关闭子站点独立覆盖，本站自动沿用全网统一的备案、展示与功能模块设置。', 'wpicp' ),
			'actions' => $wpicp_net_actions,
		)
	);
	Wenpai_Admin_Shell::close( $shell );
	return;
}

$wpicp_subnav_items = array();
foreach ( $wpicp_sections as $wpicp_sid => $wpicp_slabel ) {
	$wpicp_subnav_items[] = array(
		'id'      => $wpicp_sid,
		'label'   => $wpicp_slabel,
		'url'     => AdminPage::settings_section_url( $shell, $wpicp_sid ),
		'current' => $wpicp_sid === $wpicp_section,
	);
}

$wpicp_form_action = $wpicp_is_network && function_exists( 'network_admin_url' )
	? network_admin_url( 'edit.php?action=wpicp_save_network' )
	: 'options.php';
?>
<div class="wenpai-stack">
<?php
Wenpai_Admin_UI::subnav(
	array(
		'label' => __( '设置分区', 'wpicp' ),
		'items' => $wpicp_subnav_items,
	)
);

if ( 'filing' === $wpicp_section ) :
	$wpicp_licenses = $settings->get( 'custom_licenses' );
	if ( ! is_array( $wpicp_licenses ) ) {
		$wpicp_licenses = array();
	}
	?>
	<form class="wenpai-stack" method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
	<?php
	if ( function_exists( 'settings_fields' ) ) {
		settings_fields( $settings->group() );
	}
	echo $settings->tab_input_html( 'filing' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
	?>
	<input type="hidden" name="_wpicp_section" value="filing">
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
<?php elseif ( 'display' === $wpicp_section ) : ?>
	<?php
	$wpicp_active_style = in_array( (string) $wpicp_effective['footer_style'], array( 'minimal', 'standard', 'badges' ), true )
		? (string) $wpicp_effective['footer_style']
		: 'standard';
	?>
	<form class="wenpai-stack" method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
	<?php
	if ( function_exists( 'settings_fields' ) ) {
		settings_fields( $settings->group() );
	}
	echo $settings->tab_input_html( 'display' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
	?>
	<input type="hidden" name="_wpicp_section" value="display">
	<input type="hidden" name="_wpicp_mode" value="<?php echo esc_attr( $wpicp_mode ); ?>">

	<?php
	$wpicp_stage_controls = '<div class="wenpai-head-actions">'
		. Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '浅色页脚', 'wpicp' ),
				'type'    => 'button',
				'variant' => 'secondary',
				'size'    => 'sm',
				'attrs'   => array(
					'data-wpicp-stage-bg' => 'light',
					'aria-pressed'        => 'true',
				),
			)
		)
		. Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '深色页脚', 'wpicp' ),
				'type'    => 'button',
				'variant' => 'ghost',
				'size'    => 'sm',
				'attrs'   => array(
					'data-wpicp-stage-bg' => 'dark',
					'aria-pressed'        => 'false',
				),
			)
		)
		. '</div>';

	Wenpai_Admin_UI::card_open(
		array(
			'title'             => __( '页脚合规栏实时预览', 'wpicp' ),
			'sub'               => __( '修改下方版式、对齐或分隔符将实时反馈到预览区；可切换深浅底检验主题对比度。', 'wpicp' ),
			'tile'              => false,
			'head_control_html' => $wpicp_stage_controls,
		)
	);
	?>
	<div class="wpicp-preview">
		<div class="wpicp-preview__stage" data-wpicp-preview-stage>
			<?php foreach ( array( 'minimal', 'standard', 'badges' ) as $wpicp_variant ) : ?>
				<?php
				$wpicp_v_html = Renderer::render_compliance_bar(
					array(
						'preset'        => $wpicp_variant,
						'align'         => (string) $wpicp_effective['footer_align'],
						'separator'     => (string) $wpicp_effective['footer_separator'],
						'demo_fallback' => true,
						'skip_enqueue'  => true,
					),
					$wpicp_effective
				);
				?>
				<div data-wpicp-bar-variant="<?php echo esc_attr( $wpicp_variant ); ?>"<?php echo $wpicp_variant === $wpicp_active_style ? '' : ' hidden'; ?>>
					<?php echo $wpicp_v_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Renderer::render_compliance_bar. ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	Wenpai_Admin_UI::card_close();

	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '页脚输出与排版设置', 'wpicp' ),
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
	Wenpai_Admin_UI::savebar(
		array(
			'message'      => __( '有未保存的前台展示更改', 'wpicp' ),
			'submit_label' => __( '保存设置', 'wpicp' ),
		)
	);
	?>
	</form>
<?php else : ?>
	<?php
	// Section: `modules` (Optional feature switches).
	$wpicp_mod_badges   = SettingsStore::module_enabled( 'module_badges', $wpicp_effective );
	$wpicp_mod_access   = SettingsStore::module_enabled( 'module_access', $wpicp_effective );
	$wpicp_mod_adlaw    = SettingsStore::module_enabled( 'module_adlaw', $wpicp_effective );
	$wpicp_audit_info   = SiteHealthCheck::audit( $wpicp_effective );
	$wpicp_access_snap  = AccessMonitor::last_status();
	$wpicp_probe_snap   = isset( $wpicp_access_snap['keepalive'] ) && is_array( $wpicp_access_snap['keepalive'] ) ? $wpicp_access_snap['keepalive'] : array();
	$wpicp_keepalive_on = $wpicp_mod_access && ! empty( $wpicp_effective['keepalive_enabled'] ) && '' !== trim( (string) $wpicp_effective['keepalive_url'] );
	$wpicp_probe_bad    = $wpicp_keepalive_on && ! empty( $wpicp_probe_snap ) && empty( $wpicp_probe_snap['ok'] );
	$wpicp_scan_meta    = get_option( AuditController::SUMMARY_OPTION, array() );
	$wpicp_scan_hits    = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['total_hits'] ) ? (int) $wpicp_scan_meta['total_hits'] : 0;
	$wpicp_has_scan     = is_array( $wpicp_scan_meta ) && ! empty( $wpicp_scan_meta['last_scan_at'] );
	$wpicp_cert_count   = is_array( $wpicp_effective['certificates'] ) ? count( $wpicp_effective['certificates'] ) : 0;

	$wpicp_badges_url = AdminPage::shell_tab_url( $shell, 'badges', '#badges' );
	$wpicp_audit_url  = AdminPage::shell_tab_url( $shell, 'audit', '#audit' );
	$wpicp_is_proto   = false !== strpos( $wpicp_audit_url, '.html' );
	$wpicp_access_url = $wpicp_is_proto ? 'audit.html' : add_query_arg( 'section', 'access', $wpicp_audit_url );
	$wpicp_adlaw_url  = $wpicp_is_proto ? 'audit-adlaw.html' : add_query_arg( 'section', 'adlaw', $wpicp_audit_url );

	$wpicp_mod_switch = static function ( $mod_key, $checked, $title_label ) {
		$sw = Wenpai_Admin_Settings::switch_html(
			array(
				'id'      => 'wpicp-switch-' . str_replace( '_', '-', $mod_key ),
				'checked' => (bool) $checked,
			)
		);
		return '<span data-wpicp-module-toggle="' . esc_attr( $mod_key ) . '" data-wpicp-module-label="' . esc_attr( $title_label ) . '">' . $sw . '</span>';
	};

	Wenpai_Admin_UI::notice(
		array(
			'level' => 'info',
			'text'  => __( '按需开启扩展功能：关闭时隐藏对应顶栏入口，并停用后台定时探测与编辑器脚本；已填写的证照与规则数据不会丢失。', 'wpicp' ),
		)
	);

	Wenpai_Admin_UI::module_grid(
		array(
			'cols'  => 3,
			'items' => array(
				array(
					'title'          => __( '证照与徽章', 'wpicp' ),
					'desc'           => __( '官方预设信任徽标、自定义徽章、电子证照水印亮照与前台合集页。', 'wpicp' ),
					'icon'           => 'image',
					'tone'           => $wpicp_mod_badges ? 'accent' : 'mute',
					'control_html'   => $wpicp_mod_switch( 'module_badges', $wpicp_mod_badges, __( '证照与徽章', 'wpicp' ) ),
					'status'         => $wpicp_mod_badges
						? sprintf(
							/* translators: 1: badge count, 2: certificate count */
							__( '已开启 · %1$d 枚徽章 / %2$d 份证照', 'wpicp' ),
							$wpicp_audit_info['badge_count'],
							$wpicp_cert_count
						)
						: __( '未开启 · 顶栏隐藏「证照」', 'wpicp' ),
					'status_tone'    => $wpicp_mod_badges ? 'ok' : 'mute',
					'action_label'   => $wpicp_mod_badges ? __( '进入配置', 'wpicp' ) : '',
					'action_url'     => $wpicp_mod_badges ? $wpicp_badges_url : '',
					'action_variant' => 'ghost',
				),
				array(
					'title'          => __( '备案与接入保活', 'wpicp' ),
					'desc'           => __( '首页备案定时自检、接入商 DNS/IP 核查与子域名低频保活访问。', 'wpicp' ),
					'icon'           => 'shield-check',
					'tone'           => $wpicp_mod_access ? ( $wpicp_probe_bad ? 'bad' : 'accent' ) : 'mute',
					'control_html'   => $wpicp_mod_switch( 'module_access', $wpicp_mod_access, __( '备案与接入保活', 'wpicp' ) ),
					'status'         => $wpicp_mod_access
						? ( $wpicp_probe_bad ? __( '已开启 · 保活异常', 'wpicp' ) : ( $wpicp_keepalive_on ? __( '已开启 · 首页自检与保活', 'wpicp' ) : __( '已开启 · 首页定时自检', 'wpicp' ) ) )
						: __( '未开启 · 零后台定时探测', 'wpicp' ),
					'status_tone'    => $wpicp_mod_access ? ( $wpicp_probe_bad ? 'bad' : 'ok' ) : 'mute',
					'action_label'   => $wpicp_mod_access ? __( '进入配置', 'wpicp' ) : '',
					'action_url'     => $wpicp_mod_access ? $wpicp_access_url : '',
					'action_variant' => 'ghost',
				),
				array(
					'title'          => __( '广告法极限词巡检', 'wpicp' ),
					'desc'           => __( '离线极限词检查、编辑器发布前提示、全站扫描与无言远程词库同步。', 'wpicp' ),
					'icon'           => 'search',
					'tone'           => $wpicp_mod_adlaw ? ( $wpicp_scan_hits > 0 ? 'warn' : 'accent' ) : 'mute',
					'control_html'   => $wpicp_mod_switch( 'module_adlaw', $wpicp_mod_adlaw, __( '广告法极限词巡检', 'wpicp' ) ),
					'status'         => $wpicp_mod_adlaw
						? ( $wpicp_has_scan
							? sprintf(
								/* translators: %d: hit count */
								__( '已开启 · 发现 %d 处待复核', 'wpicp' ),
								$wpicp_scan_hits
							)
							: __( '已开启 · 编辑器与全站巡检', 'wpicp' ) )
						: __( '未开启 · 零编辑器负载', 'wpicp' ),
					'status_tone'    => $wpicp_mod_adlaw ? ( $wpicp_scan_hits > 0 ? 'warn' : 'ok' ) : 'mute',
					'action_label'   => $wpicp_mod_adlaw ? __( '进入配置', 'wpicp' ) : '',
					'action_url'     => $wpicp_mod_adlaw ? $wpicp_adlaw_url : '',
					'action_variant' => 'ghost',
				),
			),
		)
	);
endif;
?>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
