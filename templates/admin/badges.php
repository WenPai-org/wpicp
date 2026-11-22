<?php
/**
 * Admin Certificates & Trust Badges Tab (`tab=badges`).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Media\CertificateWatermark;
use WenPai\ICP\Options\BadgeCatalog;
use WenPai\ICP\Options\SettingsStore;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network     = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_is_ms          = function_exists( 'is_multisite' ) && is_multisite();
$wpicp_subsite_locked = $wpicp_is_ms && ! $wpicp_is_network && ! SettingsStore::allows_subsite_override();
$wpicp_option_name    = $settings->option();
$wpicp_effective      = SettingsStore::effective();

$wpicp_base_url = isset( $shell['tabs'][2]['url'] ) ? (string) $shell['tabs'][2]['url'] : '#badges';
$wpicp_is_proto = false !== strpos( $wpicp_base_url, '.html' );

$wpicp_groups  = array(
	'presets'      => __( '官方预设徽章', 'wpicp' ),
	'custom'       => __( '自定义合作徽章', 'wpicp' ),
	'certificates' => __( '电子证照亮照', 'wpicp' ),
);
$wpicp_section = Wenpai_Admin_UI::section( $wpicp_groups, 'presets' );

$wpicp_all_presets = BadgeCatalog::presets( true );
$wpicp_sel_presets = $settings->get( 'selected_presets' );
if ( ! is_array( $wpicp_sel_presets ) ) {
	$wpicp_sel_presets = array();
}
$wpicp_sel_presets = array_map( 'strval', $wpicp_sel_presets );

$wpicp_custom_badges = $settings->get( 'badges' );
if ( ! is_array( $wpicp_custom_badges ) ) {
	$wpicp_custom_badges = array();
}

$wpicp_certs = $settings->get( 'certificates' );
if ( ! is_array( $wpicp_certs ) ) {
	$wpicp_certs = array();
}

$wpicp_wm_text   = CertificateWatermark::resolve_text( (string) $wpicp_effective['cert_watermark_text'], $wpicp_effective );
$wpicp_wm_on     = ! empty( $wpicp_effective['cert_watermark_enabled'] );
$wpicp_wm_layout = (string) $wpicp_effective['cert_watermark_layout'];
$wpicp_wm_tone   = (string) $wpicp_effective['cert_watermark_tone'];
$wpicp_wm_cls    = 'wpicp-certificates__wm-admin';
if ( 'center' === $wpicp_wm_layout ) {
	$wpicp_wm_cls .= ' is-center';
} elseif ( 'corner' === $wpicp_wm_layout ) {
	$wpicp_wm_cls .= ' is-corner';
}
if ( in_array( $wpicp_wm_tone, array( 'light', 'strong', 'red' ), true ) ) {
	$wpicp_wm_cls .= ' is-' . $wpicp_wm_tone;
}

$wpicp_check_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>';

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '证照徽章', 'wpicp' ),
	__( '管理页脚权威信任徽标与电子营业执照防盗水印亮照。', 'wpicp' )
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
	echo Wenpai_Admin_Action::notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'level' => 'ok',
			'text'  => __( '多站点网络默认证照与徽章设置已保存。', 'wpicp' ),
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
	Wenpai_Admin_UI::next(
		array(
			'icon'    => 'image',
			'title'   => __( '当前子站点继承多站点网络配置', 'wpicp' ),
			'desc'    => __( '网络超级管理员已关闭子站点独立覆盖，本站自动沿用全网统一的证照与信任徽章。', 'wpicp' ),
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
			? ( 'presets' === $wpicp_gid ? 'badges.html' : 'badges-' . $wpicp_gid . '.html?section=' . $wpicp_gid )
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
		/* translators: %d: total preset badge count */
		$wpicp_preset_groups = array( 'all' => sprintf( __( '全部 (%d)', 'wpicp' ), count( $wpicp_all_presets ) ) );
		foreach ( $wpicp_all_presets as $wpicp_pitem ) {
			$wpicp_pg = (string) $wpicp_pitem['group'];
			if ( ! isset( $wpicp_preset_groups[ $wpicp_pg ] ) ) {
				$wpicp_preset_groups[ $wpicp_pg ] = $wpicp_pg;
			}
		}

		Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '官方监管与权威信任徽章', 'wpicp' ),
				'sub'               => __( '直接点选需要展示的权威徽标卡片，外链均自动采用 HTTPS。', 'wpicp' ),
				'tile'              => false,
				'head_control_html' => '<span class="meta" data-wpicp-badge-count>' . esc_html(
					sprintf(
						/* translators: 1: selected count, 2: total count */
						__( '已选 %1$d / %2$d', 'wpicp' ),
						count( $wpicp_sel_presets ),
						count( $wpicp_all_presets )
					)
				) . '</span>',
			)
		);
		?>
		<div class="wpicp-badges__bar">
			<div class="wpicp-badges__filters" role="group" aria-label="<?php esc_attr_e( '按机构分类筛选', 'wpicp' ); ?>">
				<?php foreach ( $wpicp_preset_groups as $wpicp_gk => $wpicp_gl ) : ?>
					<?php
					$wpicp_grp_btn = Wenpai_Admin_UI::button_html(
						array(
							'label'   => $wpicp_gl,
							'type'    => 'button',
							'variant' => 'all' === $wpicp_gk ? 'secondary' : 'ghost',
							'size'    => 'sm',
							'attrs'   => array(
								'data-wpicp-badge-group' => $wpicp_gk,
								'aria-pressed'           => 'all' === $wpicp_gk ? 'true' : 'false',
							),
						)
					);
					echo $wpicp_grp_btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
					?>
				<?php endforeach; ?>
			</div>
			<div class="wpicp-badges__right">
				<input type="search" class="wpicp-badges__search" placeholder="<?php esc_attr_e( '搜索徽标名称…', 'wpicp' ); ?>" aria-label="<?php esc_attr_e( '搜索徽标名称', 'wpicp' ); ?>" data-wpicp-badge-search>
				<button type="button" class="btn btn-ghost btn-sm" data-wpicp-badge-all><?php esc_html_e( '全选', 'wpicp' ); ?></button>
				<button type="button" class="btn btn-ghost btn-sm" data-wpicp-badge-clear><?php esc_html_e( '清空', 'wpicp' ); ?></button>
			</div>
		</div>
		<div class="wpicp-badges__grid" data-wpicp-badge-grid>
			<?php foreach ( $wpicp_all_presets as $wpicp_pkey => $wpicp_preset ) : ?>
				<?php
				$wpicp_pkey_str = (string) $wpicp_pkey;
				$wpicp_is_sel   = in_array( $wpicp_pkey_str, $wpicp_sel_presets, true );
				$wpicp_img_url  = BadgeCatalog::image_url( $wpicp_preset['file'] );
				?>
				<label class="wpicp-badges__card<?php echo $wpicp_is_sel ? ' is-selected' : ''; ?>" data-group="<?php echo esc_attr( $wpicp_preset['group'] ); ?>">
					<input type="checkbox" class="wpicp-badges__input" name="<?php echo esc_attr( $wpicp_option_name ); ?>[selected_presets][]" value="<?php echo esc_attr( $wpicp_pkey_str ); ?>"<?php checked( $wpicp_is_sel ); ?>>
					<span class="wpicp-badges__logo"><img src="<?php echo esc_url( $wpicp_img_url ); ?>" alt="<?php echo esc_attr( $wpicp_preset['title'] ); ?>"></span>
					<span class="wpicp-badges__body">
						<span class="wpicp-badges__title"><?php echo esc_html( $wpicp_preset['title'] ); ?></span>
						<span class="wpicp-badges__meta"><?php echo esc_html( $wpicp_preset['group'] ); ?></span>
					</span>
					<span class="wpicp-badges__check" aria-hidden="true"><?php echo $wpicp_check_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG icon. ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '勾选后即可在页脚「含信任徽章」版式或「信任徽章」区块中输出。', 'wpicp' ),
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
				'title'             => __( '自定义合作与安全认证徽章', 'wpicp' ),
				'sub'               => __( '点击左侧图标区从媒体库上传或替换图片，外链自动强制升级为 HTTPS。', 'wpicp' ),
				'tile'              => false,
				'head_control_html' => Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '添加自定义徽章', 'wpicp' ),
						'type'    => 'button',
						'variant' => 'secondary',
						'size'    => 'sm',
						'attrs'   => array( 'data-wpicp-add-badge' => '1' ),
					)
				),
			)
		);
		?>
		<input type="hidden" name="<?php echo esc_attr( $wpicp_option_name ); ?>[badges][__sentinel]" value="">
		<div class="wpicp-editor" data-wpicp-custom-badges data-option="<?php echo esc_attr( $wpicp_option_name ); ?>">
			<div class="wpicp-editor__media-head"<?php echo empty( $wpicp_custom_badges ) ? ' hidden' : ''; ?>>
				<span><?php esc_html_e( '徽章图标', 'wpicp' ); ?></span>
				<span><?php esc_html_e( '徽章名称', 'wpicp' ); ?></span>
				<span><?php esc_html_e( '核验跳转链接（自动 HTTPS）', 'wpicp' ); ?></span>
				<span><?php esc_html_e( '操作', 'wpicp' ); ?></span>
			</div>
			<?php foreach ( array_values( $wpicp_custom_badges ) as $wpicp_bidx => $wpicp_bitem ) : ?>
				<?php
				$wpicp_b_id    = is_array( $wpicp_bitem ) && isset( $wpicp_bitem['image_id'] ) ? absint( $wpicp_bitem['image_id'] ) : 0;
				$wpicp_b_title = is_array( $wpicp_bitem ) && isset( $wpicp_bitem['title'] ) ? (string) $wpicp_bitem['title'] : '';
				$wpicp_b_url   = is_array( $wpicp_bitem ) && isset( $wpicp_bitem['url'] ) ? (string) $wpicp_bitem['url'] : '';
				$wpicp_b_src   = $wpicp_b_id > 0 && function_exists( 'wp_get_attachment_image_url' )
					? (string) wp_get_attachment_image_url( $wpicp_b_id, 'medium' )
					: '';
				?>
				<div class="wpicp-editor__media-row" data-index="<?php echo esc_attr( (string) $wpicp_bidx ); ?>">
					<input type="hidden" data-subkey="image_id" name="<?php echo esc_attr( $wpicp_option_name . '[badges][' . $wpicp_bidx . '][image_id]' ); ?>" value="<?php echo esc_attr( (string) $wpicp_b_id ); ?>">
					<button type="button" class="wpicp-editor__thumb<?php echo '' !== $wpicp_b_src ? ' has-img' : ''; ?>" data-wpicp-pick-badge-img aria-label="<?php esc_attr_e( '选择徽章图片', 'wpicp' ); ?>">
						<?php if ( '' !== $wpicp_b_src ) : ?>
							<img src="<?php echo esc_url( $wpicp_b_src ); ?>" alt="">
						<?php else : ?>
							<?php esc_html_e( '选择图片', 'wpicp' ); ?>
						<?php endif; ?>
					</button>
					<input type="text" data-subkey="title" name="<?php echo esc_attr( $wpicp_option_name . '[badges][' . $wpicp_bidx . '][title]' ); ?>" value="<?php echo esc_attr( $wpicp_b_title ); ?>" placeholder="<?php esc_attr_e( '如：ISO 27001 信息安全认证', 'wpicp' ); ?>" aria-label="<?php esc_attr_e( '徽章名称', 'wpicp' ); ?>">
					<input type="url" data-subkey="url" name="<?php echo esc_attr( $wpicp_option_name . '[badges][' . $wpicp_bidx . '][url]' ); ?>" value="<?php echo esc_attr( $wpicp_b_url ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( '核验跳转链接', 'wpicp' ); ?>">
					<div class="wpicp-editor__acts">
						<button type="button" class="btn btn-ghost btn-sm" data-wpicp-pick-badge-img><?php esc_html_e( '更换', 'wpicp' ); ?></button>
						<button type="button" class="btn btn-ghost btn-sm" data-wpicp-del-badge><?php esc_html_e( '移除', 'wpicp' ); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="wpicp-editor__empty" data-wpicp-badges-empty<?php echo empty( $wpicp_custom_badges ) ? '' : ' hidden'; ?>>
			<div class="wpicp-editor__empty-main">
				<?php echo Wenpai_Admin_UI::tile_html( array( 'icon' => 'image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit HTML. ?>
				<div>
					<div class="wpicp-editor__empty-title"><?php esc_html_e( '暂未添加自定义合作徽章', 'wpicp' ); ?></div>
					<div class="wpicp-editor__empty-desc"><?php esc_html_e( '可上传行业协会、云安全或合作伙伴认证图标，并配置对应的官方核验链接。', 'wpicp' ); ?></div>
				</div>
			</div>
			<?php
			$wpicp_add_first_badge = Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '添加第一枚徽章', 'wpicp' ),
					'type'    => 'button',
					'variant' => 'secondary',
					'size'    => 'sm',
					'attrs'   => array( 'data-wpicp-add-badge' => '1' ),
				)
			);
			echo $wpicp_add_first_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
			?>
		</div>
		<?php
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '自定义徽章将紧随官方预设徽章在页脚与「信任徽章」区块中输出。', 'wpicp' ),
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

	<div class="wenpai-stack"<?php echo Wenpai_Admin_UI::group_attrs( 'certificates', $wpicp_section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit attribute helper. ?>>
		<?php
		$wpicp_cert_page_id    = absint( $wpicp_effective['certificates_page'] );
		$wpicp_cert_page_ready = $wpicp_cert_page_id > 0
			&& ( ! function_exists( 'get_post_status' ) || 'publish' === get_post_status( $wpicp_cert_page_id ) );
		$wpicp_cert_page_url   = $wpicp_cert_page_ready && function_exists( 'get_permalink' )
			? (string) get_permalink( $wpicp_cert_page_id )
			: '';
		$wpicp_cert_page_edit  = $wpicp_cert_page_ready && function_exists( 'get_edit_post_link' )
			? (string) get_edit_post_link( $wpicp_cert_page_id, 'raw' )
			: '';
		$wpicp_cert_page_title = $wpicp_cert_page_ready && function_exists( 'get_the_title' )
			? (string) get_the_title( $wpicp_cert_page_id )
			: __( '资质与证照公示', 'wpicp' );

		$wpicp_cert_cats = array(
			/* translators: %d: total uploaded certificate count */
			'all' => sprintf( __( '全部 (%d)', 'wpicp' ), count( $wpicp_certs ) ),
		);
		foreach ( $wpicp_certs as $wpicp_ci ) {
			$wpicp_ct = is_array( $wpicp_ci ) && ! empty( $wpicp_ci['type'] ) ? trim( (string) $wpicp_ci['type'] ) : __( '营业执照', 'wpicp' );
			if ( ! isset( $wpicp_cert_cats[ $wpicp_ct ] ) ) {
				$wpicp_cert_cats[ $wpicp_ct ] = $wpicp_ct;
			}
		}

		$wpicp_cert_head_actions = '<div class="wenpai-head-actions">';
		if ( ! $wpicp_is_network ) {
			if ( $wpicp_cert_page_ready && '' !== $wpicp_cert_page_url ) {
				$wpicp_cert_head_actions .= Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '预览前台合集页', 'wpicp' ),
						'href'    => $wpicp_cert_page_url,
						'variant' => 'ghost',
						'size'    => 'sm',
						'attrs'   => array(
							'target' => '_blank',
							'rel'    => 'noopener noreferrer',
						),
					)
				);
			} else {
				$wpicp_cert_head_actions .= Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '一键生成公示合集页', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'ghost',
						'size'    => 'sm',
						'attrs'   => array( 'form' => 'wpicp-create-cert-page-form' ),
					)
				);
			}
		}
		$wpicp_cert_head_actions .= Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '批量上传证照', 'wpicp' ),
				'type'    => 'button',
				'variant' => 'secondary',
				'size'    => 'sm',
				'attrs'   => array( 'data-wpicp-add-cert' => '1' ),
			)
		);
		$wpicp_cert_head_actions .= '</div>';

		Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '电子证照与营业执照复印件管理', 'wpicp' ),
				'sub'               => __( '支持批量上传营业执照、行业许可、软著商标与体系认证（最多 100 项），可自定义分类并实时预览输出水印。', 'wpicp' ),
				'tile'              => false,
				'head_control_html' => $wpicp_cert_head_actions,
			)
		);
		?>
		<div class="wpicp-badges__bar" data-wpicp-cert-toolbar<?php echo empty( $wpicp_certs ) ? ' hidden' : ''; ?>>
			<div class="wpicp-badges__filters" role="group" aria-label="<?php esc_attr_e( '按证照分类筛选', 'wpicp' ); ?>" data-wpicp-cert-filters>
				<?php foreach ( $wpicp_cert_cats as $wpicp_ck => $wpicp_cl ) : ?>
					<?php
					$wpicp_cbtn = Wenpai_Admin_UI::button_html(
						array(
							'label'   => $wpicp_cl,
							'type'    => 'button',
							'variant' => 'all' === $wpicp_ck ? 'secondary' : 'ghost',
							'size'    => 'sm',
							'attrs'   => array(
								'data-wpicp-cert-group' => $wpicp_ck,
								'aria-pressed'          => 'all' === $wpicp_ck ? 'true' : 'false',
							),
						)
					);
					echo $wpicp_cbtn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
					?>
				<?php endforeach; ?>
			</div>
			<div class="wpicp-badges__right">
				<input type="search" class="wpicp-badges__search" placeholder="<?php esc_attr_e( '搜索证照标题或编号…', 'wpicp' ); ?>" aria-label="<?php esc_attr_e( '搜索证照', 'wpicp' ); ?>" data-wpicp-cert-search>
			</div>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $wpicp_option_name ); ?>[certificates][__sentinel]" value="">
		<div class="wpicp-certificates__grid" data-wpicp-cert-grid data-option="<?php echo esc_attr( $wpicp_option_name ); ?>">
			<?php foreach ( array_values( $wpicp_certs ) as $wpicp_cidx => $wpicp_cert ) : ?>
				<?php
				$wpicp_c_id   = is_array( $wpicp_cert ) && isset( $wpicp_cert['image_id'] ) ? absint( $wpicp_cert['image_id'] ) : 0;
				$wpicp_c_tit  = is_array( $wpicp_cert ) && isset( $wpicp_cert['title'] ) ? (string) $wpicp_cert['title'] : '';
				$wpicp_c_type = is_array( $wpicp_cert ) && ! empty( $wpicp_cert['type'] ) ? (string) $wpicp_cert['type'] : __( '营业执照', 'wpicp' );
				$wpicp_c_num  = is_array( $wpicp_cert ) && isset( $wpicp_cert['number'] ) ? (string) $wpicp_cert['number'] : '';
				$wpicp_c_exp  = is_array( $wpicp_cert ) && isset( $wpicp_cert['expires_at'] ) ? (string) $wpicp_cert['expires_at'] : '';
				$wpicp_c_vis  = ! is_array( $wpicp_cert ) || ! array_key_exists( 'visible', $wpicp_cert ) || ! empty( $wpicp_cert['visible'] );
				$wpicp_c_src  = $wpicp_c_id > 0 && function_exists( 'wp_get_attachment_image_url' )
					? (string) wp_get_attachment_image_url( $wpicp_c_id, 'medium' )
					: '';
				if ( '' === $wpicp_c_src ) {
					$wpicp_c_src = BadgeCatalog::image_url( 'dzyyzz.jpg' );
				}
				?>
				<div class="wpicp-certificates__card" data-wpicp-cert-card="1" data-cert-cat="<?php echo esc_attr( $wpicp_c_type ); ?>" data-index="<?php echo esc_attr( (string) $wpicp_cidx ); ?>">
					<input type="hidden" data-subkey="image_id" name="<?php echo esc_attr( $wpicp_option_name . '[certificates][' . $wpicp_cidx . '][image_id]' ); ?>" value="<?php echo esc_attr( (string) $wpicp_c_id ); ?>">
					<input type="hidden" data-subkey="type" name="<?php echo esc_attr( $wpicp_option_name . '[certificates][' . $wpicp_cidx . '][type]' ); ?>" value="<?php echo esc_attr( $wpicp_c_type ); ?>">
					<input type="hidden" data-subkey="number" name="<?php echo esc_attr( $wpicp_option_name . '[certificates][' . $wpicp_cidx . '][number]' ); ?>" value="<?php echo esc_attr( $wpicp_c_num ); ?>">
					<input type="hidden" data-subkey="expires_at" name="<?php echo esc_attr( $wpicp_option_name . '[certificates][' . $wpicp_cidx . '][expires_at]' ); ?>" value="<?php echo esc_attr( $wpicp_c_exp ); ?>">
					<input type="hidden" data-subkey="visible" name="<?php echo esc_attr( $wpicp_option_name . '[certificates][' . $wpicp_cidx . '][visible]' ); ?>" value="<?php echo $wpicp_c_vis ? '1' : '0'; ?>">
					<div class="wpicp-certificates__stage">
						<img src="<?php echo esc_url( $wpicp_c_src ); ?>" alt="<?php echo esc_attr( $wpicp_c_tit ); ?>">
						<div class="<?php echo esc_attr( $wpicp_wm_cls ); ?>" data-wpicp-wm-layer aria-hidden="true"<?php echo $wpicp_wm_on ? '' : ' hidden'; ?>>
							<span><?php echo esc_html( $wpicp_wm_text ); ?></span>
							<?php if ( 'tile' === $wpicp_wm_layout ) : ?>
								<span><?php echo esc_html( $wpicp_wm_text ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div class="wpicp-certificates__meta">
						<span data-cert-type-out><?php echo esc_html( $wpicp_c_type ); ?></span>
						<span data-cert-exp-out><?php echo esc_html( '' !== $wpicp_c_exp ? $wpicp_c_exp : __( '长期有效', 'wpicp' ) ); ?></span>
					</div>
					<input type="text" data-subkey="title" name="<?php echo esc_attr( $wpicp_option_name . '[certificates][' . $wpicp_cidx . '][title]' ); ?>" value="<?php echo esc_attr( $wpicp_c_tit ); ?>" aria-label="<?php esc_attr_e( '证照标题', 'wpicp' ); ?>">
					<div class="wpicp-certificates__foot">
						<button type="button" class="btn btn-ghost btn-sm" data-wpicp-cert-edit><?php esc_html_e( '编辑信息', 'wpicp' ); ?></button>
						<button type="button" class="btn btn-ghost btn-sm" data-wpicp-del-cert><?php esc_html_e( '移除', 'wpicp' ); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
			<button type="button" class="wpicp-certificates__card wpicp-certificates__add" data-wpicp-add-cert>
				<?php echo class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'image' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG icon. ?>
				<span><?php esc_html_e( '上传证照复印件（可多选）', 'wpicp' ); ?></span>
			</button>
		</div>

		<dialog class="wenpai-native wenpai-dialog" id="wpicp-cert-dialog" aria-labelledby="wpicp-cert-dialog-title">
			<div class="wenpai-dialog__head">
				<h3 class="card-title" id="wpicp-cert-dialog-title"><?php esc_html_e( '编辑证照台账信息', 'wpicp' ); ?></h3>
			</div>
			<div class="wenpai-dialog__body wenpai-stack">
				<div class="wenpai-field">
					<label class="wenpai-field__label" for="wpicp-dlg-title"><?php esc_html_e( '证照标题', 'wpicp' ); ?></label>
					<div class="wenpai-field__control"><input id="wpicp-dlg-title" type="text" value=""></div>
				</div>
				<div class="wenpai-field">
					<label class="wenpai-field__label" for="wpicp-dlg-type"><?php esc_html_e( '证照分类（可自定义输入）', 'wpicp' ); ?></label>
					<div class="wenpai-field__control">
						<input id="wpicp-dlg-type" type="text" list="wpicp-cert-type-presets" placeholder="<?php esc_attr_e( '点选常用分类或直接输入任意分类名', 'wpicp' ); ?>">
						<datalist id="wpicp-cert-type-presets">
							<option value="<?php esc_attr_e( '营业执照', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '增值电信许可', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '网络文化许可', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '食品经营许可', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '出版物经营许可', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '医药/医疗器械资质', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '主体亮照', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '软件著作权', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '商标注册证', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( 'ISO 体系认证', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '高新技术/荣誉认证', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '行业授权书', 'wpicp' ); ?>">
							<option value="<?php esc_attr_e( '其他资质', 'wpicp' ); ?>">
						</datalist>
					</div>
				</div>
				<div class="wenpai-field">
					<label class="wenpai-field__label" for="wpicp-dlg-number"><?php esc_html_e( '证照编号', 'wpicp' ); ?></label>
					<div class="wenpai-field__control"><input id="wpicp-dlg-number" type="text" placeholder="<?php esc_attr_e( '统一社会信用代码、许可证号或证书编号', 'wpicp' ); ?>"></div>
				</div>
				<div class="wenpai-field">
					<label class="wenpai-field__label" for="wpicp-dlg-exp"><?php esc_html_e( '有效期（到期前 30 天提醒）', 'wpicp' ); ?></label>
					<div class="wenpai-field__control"><input id="wpicp-dlg-exp" type="text" placeholder="<?php esc_attr_e( '留空为长期有效，或填 YYYY-MM-DD', 'wpicp' ); ?>"></div>
				</div>
				<div class="wenpai-field">
					<label class="wenpai-field__label" for="wpicp-dlg-visible"><?php esc_html_e( '前台画廊展示', 'wpicp' ); ?></label>
					<div class="wenpai-field__control">
						<label><input id="wpicp-dlg-visible" type="checkbox" checked> <?php esc_html_e( '在亮照画廊、公示合集页与区块中公开展示此证照', 'wpicp' ); ?></label>
					</div>
				</div>
			</div>
			<div class="wenpai-dialog__foot">
				<button type="button" class="btn btn-ghost" data-wpicp-dlg-close><?php esc_html_e( '取消', 'wpicp' ); ?></button>
				<button type="button" class="btn btn-secondary" data-wpicp-dlg-pick><?php esc_html_e( '更换扫描件', 'wpicp' ); ?></button>
				<button type="button" class="btn btn-primary" data-wpicp-dlg-save><?php esc_html_e( '确定更新', 'wpicp' ); ?></button>
			</div>
		</dialog>
		<?php
		if ( ! $wpicp_is_network ) {
			$wpicp_cert_foot_actions = array();
			if ( $wpicp_cert_page_ready && '' !== $wpicp_cert_page_edit ) {
				$wpicp_cert_foot_actions[] = array(
					'label'   => __( '编辑合集页区块', 'wpicp' ),
					'href'    => $wpicp_cert_page_edit,
					'variant' => 'ghost',
					'size'    => 'sm',
				);
			}
			Wenpai_Admin_UI::card_foot(
				array(
					'start'   => true,
					'meta'    => $wpicp_cert_page_ready && '' !== $wpicp_cert_page_url
						? sprintf(
							/* translators: 1: page title, 2: page url */
							__( '前台合集页已绑定：%1$s（%2$s）', 'wpicp' ),
							$wpicp_cert_page_title,
							$wpicp_cert_page_url
						)
						: __( '点击右上角「一键生成公示合集页」可自动建页并在页脚显示「证照公示」入口。', 'wpicp' ),
					'actions' => $wpicp_cert_foot_actions,
				)
			);
		}
		Wenpai_Admin_UI::card_close();

		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '亮照输出水印与防盗展示设置', 'wpicp' ),
				'sub'   => __( '修改下方水印开关、文案、排布或浓度，上方证照卡片会即时更新水印预览。', 'wpicp' ),
				'tile'  => false,
			)
		);
		$settings->render_field( 'cert_watermark_enabled' );
		$settings->render_field( 'cert_watermark_text' );
		$settings->render_field( 'cert_watermark_layout' );
		$settings->render_field( 'cert_watermark_tone' );
		$settings->render_field( 'cert_watermark_engine' );
		$settings->render_field( 'cert_anti_save' );
		$settings->render_field( 'certificate_click_mode' );
		$settings->render_field( 'certificates_page' );
		$settings->render_field( 'default_image_size' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '服务端合成模式会按需输出带水印缓存副本并防止直接保存无水印原图，支持 [wpicp_certificates] 与区块展示。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存证照与水印设置', 'wpicp' ),
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
<?php if ( ! $wpicp_is_network ) : ?>
<form id="wpicp-create-cert-page-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
	<?php
	if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
		Wenpai_Admin_Action::fields( 'wpicp_create_certificates_page' );
	}
	?>
</form>
<?php endif; ?>
<?php
Wenpai_Admin_Shell::close( $shell );
