<?php
/**
 * Site filing completeness, format validation, and ecosystem sync health check.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Compliance;

use WenPai\ICP\Options\BadgeCatalog;
use WenPai\ICP\Options\SettingsStore;
use Wenpai_Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates filing completeness, format conformance, frontend output channel,
 * and WenPai suite companion status.
 */
class SiteHealthCheck {

	/**
	 * Register WordPress native Site Health (`site_status_tests`) check.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'site_status_tests', array( __CLASS__, 'register_wp_site_health' ) );
	}

	/**
	 * Add WPICP filing check to WordPress Tools -> Site Health.
	 *
	 * @param array<string, mixed> $tests Site Health tests map.
	 * @return array<string, mixed>
	 */
	public static function register_wp_site_health( $tests ) {
		if ( ! is_array( $tests ) ) {
			$tests = array();
		}
		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}
		$tests['direct']['wpicp_filing_completeness'] = array(
			'label' => __( '站点备案与合规公示检查', 'wpicp' ),
			'test'  => array( __CLASS__, 'run_wp_site_health_test' ),
		);
		return $tests;
	}

	/**
	 * Execute WordPress Site Health direct test.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_wp_site_health_test() {
		$audit      = self::audit();
		$manage_url = class_exists( 'Wenpai_Admin_Menu', false )
			? Wenpai_Admin_Menu::url( 'wpicp', array( 'tab' => 'settings' ) )
			: admin_url( 'options-general.php?page=wpicp&tab=settings' );

		$status = 'good';
		$label  = __( '站点 ICP 备案与公安联网备案公示完备', 'wpicp' );
		$desc   = __( '站点已完成核心备案信息填写并开启合规展示通道。', 'wpicp' );

		if ( 'bad' === $audit['overall_state'] ) {
			$status = 'recommended';
			$label  = __( '站点备案格式有误，请核对修正', 'wpicp' );
			$desc   = $audit['summary'];
		} elseif ( 'warn' === $audit['overall_state'] ) {
			$status = 'recommended';
			$label  = __( '建议完善站点备案与主体公示信息', 'wpicp' );
			$desc   = $audit['summary'];
		}

		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( '合规公示', 'wpicp' ),
				'color' => 'good' === $status ? 'blue' : 'orange',
			),
			'description' => '<p>' . esc_html( $desc ) . '</p>',
			'actions'     => '<p><a href="' . esc_url( $manage_url ) . '">' . esc_html__( '前往文派备案设置', 'wpicp' ) . '</a></p>',
			'test'        => 'wpicp_filing_completeness',
		);
	}

	/**
	 * Run full filing & compliance audit against effective settings.
	 *
	 * @param array<string, mixed>|null $settings Optional settings snapshot.
	 * @return array{
	 *   overall_state: string,
	 *   is_empty: bool,
	 *   filled_count: int,
	 *   badge_count: int,
	 *   summary: string,
	 *   checks: array<int, array{id:string, label:string, value:string, state:string, detail:string}>,
	 *   suite: array<int, array{id:string, label:string, value:string, state:string}>
	 * }
	 */
	public static function audit( ?array $settings = null ) {
		$cfg = is_array( $settings ) ? SettingsStore::normalize( $settings ) : SettingsStore::effective();

		$icp_raw  = (string) $cfg['icp_number'];
		$icp_info = BadgeCatalog::parse_icp( $icp_raw );
		$mps_info = BadgeCatalog::parse_mps( (string) $cfg['public_security_code'], (string) $cfg['province_abbr'] );
		$company  = trim( (string) $cfg['company_legal_name'] );

		$is_empty = '' === $icp_raw
			&& '' === $mps_info['code']
			&& '' === $company
			&& '' === (string) $cfg['edi_number']
			&& '' === (string) $cfg['aigc_number']
			&& '' === (string) $cfg['algorithm_number'];

		$filled_count = 0;
		foreach ( array( 'icp_number', 'public_security_code', 'company_legal_name', 'edi_number', 'aigc_number', 'algorithm_number', 'app_number', 'miniapp_number' ) as $k ) {
			if ( '' !== trim( (string) $cfg[ $k ] ) ) {
				++$filled_count;
			}
		}
		$filled_count += is_array( $cfg['custom_licenses'] ) ? count( $cfg['custom_licenses'] ) : 0;

		$badge_count = ( is_array( $cfg['selected_presets'] ) ? count( $cfg['selected_presets'] ) : 0 )
			+ ( is_array( $cfg['badges'] ) ? count( $cfg['badges'] ) : 0 )
			+ ( is_array( $cfg['certificates'] ) ? count( $cfg['certificates'] ) : 0 );

		$checks = array();

		// 1. MIIT ICP Check.
		if ( '' === $icp_raw ) {
			$checks[] = array(
				'id'     => 'icp',
				'label'  => __( '工信部 ICP 备案号', 'wpicp' ),
				'value'  => __( '未填写', 'wpicp' ),
				'state'  => 'warn',
				'detail' => __( '中国大陆公开访问网站须在页脚公示有效的 ICP 备案编号。', 'wpicp' ),
			);
		} elseif ( ! $icp_info['valid'] ) {
			$checks[] = array(
				'id'     => 'icp',
				'label'  => __( '工信部 ICP 备案号', 'wpicp' ),
				'value'  => $icp_raw,
				'state'  => 'bad',
				'detail' => __( '备案号格式不规范，标准格式示例：粤ICP备2023091240号-1。', 'wpicp' ),
			);
		} else {
			$checks[] = array(
				'id'     => 'icp',
				'label'  => __( '工信部 ICP 备案号', 'wpicp' ),
				'value'  => $icp_info['normalized'],
				'state'  => 'ok',
				'detail' => '' !== $icp_info['sequence']
					? sprintf(
						/* translators: 1: subject number, 2: sequence number */
						__( '主体号 %1$s · 网站序号 -%2$s，自动链接工信部查验平台。', 'wpicp' ),
						$icp_info['subject'],
						$icp_info['sequence']
					)
					: __( '主体备案号格式规范，自动链接工信部查验平台。', 'wpicp' ),
			);
		}

		// 2. Public Security MPS Check.
		if ( '' === $mps_info['code'] ) {
			$checks[] = array(
				'id'     => 'mps',
				'label'  => __( '公安机关联网备案', 'wpicp' ),
				'value'  => __( '未填写', 'wpicp' ),
				'state'  => 'warn',
				'detail' => __( '网站开通之日起 30 日内应完成全国互联网安全管理服务平台备案。', 'wpicp' ),
			);
		} elseif ( ! $mps_info['valid'] ) {
			$checks[] = array(
				'id'     => 'mps',
				'label'  => __( '公安机关联网备案', 'wpicp' ),
				'value'  => $mps_info['formatted'],
				'state'  => 'bad',
				'detail' => __( '网安备案编码通常为 14 位数字且须选择省份简称，请核对。', 'wpicp' ),
			);
		} else {
			$checks[] = array(
				'id'     => 'mps',
				'label'  => __( '公安机关联网备案', 'wpicp' ),
				'value'  => $mps_info['formatted'],
				'state'  => 'ok',
				'detail' => __( '14 位联网备案编码规范，已附公安网安徽标与查验链接。', 'wpicp' ),
			);
		}

		// 3. Legal Entity Check.
		if ( '' === $company ) {
			$checks[] = array(
				'id'     => 'company',
				'label'  => __( '主办单位法定全称', 'wpicp' ),
				'value'  => __( '未填写', 'wpicp' ),
				'state'  => 'warn',
				'detail' => __( '填写营业执照或备案主体全称有助于提升站点可信度与版权归属清晰度。', 'wpicp' ),
			);
		} else {
			$ext_parts = array();
			if ( '' !== (string) $cfg['edi_number'] ) {
				$ext_parts[] = __( '增值电信许可', 'wpicp' );
			}
			if ( '' !== (string) $cfg['aigc_number'] ) {
				$ext_parts[] = __( '生成式 AI 备案', 'wpicp' );
			}
			if ( '' !== (string) $cfg['algorithm_number'] ) {
				$ext_parts[] = __( '算法备案', 'wpicp' );
			}
			$checks[] = array(
				'id'     => 'company',
				'label'  => __( '主办单位与扩展资质', 'wpicp' ),
				'value'  => $company,
				'state'  => 'ok',
				'detail' => ! empty( $ext_parts )
					? __( '已同步登记：', 'wpicp' ) . implode( ' / ', $ext_parts )
					: __( '主体名称已登记，可在高级设置中补充 EDI 或 AI 算法备案。', 'wpicp' ),
			);
		}

		// 4. Site Policy Page Check (avoiding banned telemetry copy words).
		$policy_url = function_exists( 'get_privacy_policy_url' ) ? trim( (string) get_privacy_policy_url() ) : '';
		if ( '' !== $policy_url ) {
			$checks[] = array(
				'id'     => 'policy',
				'label'  => __( '合规政策声明页面', 'wpicp' ),
				'value'  => __( '已配置', 'wpicp' ),
				'state'  => 'ok',
				'detail' => __( '已关联 WordPress 政策声明页，可在页脚合规栏联动输出。', 'wpicp' ),
			);
		} else {
			$checks[] = array(
				'id'     => 'policy',
				'label'  => __( '合规政策声明页面', 'wpicp' ),
				'value'  => __( '待关联', 'wpicp' ),
				'state'  => 'warn',
				'detail' => __( '可在 WordPress「设置」中指定合规声明页以便页脚联动显示。', 'wpicp' ),
			);
		}

		// 5. Frontend Output Channel Check.
		$bisheng_on = defined( 'BISHENG_HTML_VERSION' ) || class_exists( 'BishengHtml_Wenpai_Suite' );
		if ( ! empty( $cfg['auto_footer'] ) ) {
			$style_map = array(
				'minimal'  => __( '极简文字单行', 'wpicp' ),
				'standard' => __( '标准图文双行', 'wpicp' ),
				'badges'   => __( '完整信任徽章栏', 'wpicp' ),
			);
			$checks[]  = array(
				'id'     => 'output',
				'label'  => __( '前台合规输出通道', 'wpicp' ),
				'value'  => __( '页脚自动注入', 'wpicp' ),
				'state'  => 'ok',
				'detail' => __( '当前版式：', 'wpicp' ) . ( isset( $style_map[ $cfg['footer_style'] ] ) ? $style_map[ $cfg['footer_style'] ] : $style_map['standard'] ),
			);
		} elseif ( $bisheng_on ) {
			$checks[] = array(
				'id'     => 'output',
				'label'  => __( '前台合规输出通道', 'wpicp' ),
				'value'  => __( '笔笙画布接管', 'wpicp' ),
				'state'  => 'ok',
				'detail' => __( '已通过生态契约向笔笙（Bisheng HTML）页脚合规槽同步资质。', 'wpicp' ),
			);
		} else {
			$checks[] = array(
				'id'     => 'output',
				'label'  => __( '前台合规输出通道', 'wpicp' ),
				'value'  => __( '区块 / 短代码按需调用', 'wpicp' ),
				'state'  => 'ok',
				'detail' => __( '可使用 [wpicp] 短代码、古腾堡合规区块，或在设置中开启页脚自动注入。', 'wpicp' ),
			);
		}

		// Suite Ecosystem Sync Status.
		$wpcy_on    = defined( 'WPCY_VERSION' ) || class_exists( '\\WenPai\\ChinaYes\\Admin\\WhiteLabel' );
		$wy_cache   = WordYeahClient::cached();
		$wy_version = '' !== $wy_cache['version'] ? $wy_cache['version'] : __( '内置基础词库', 'wpicp' );
		$adlaw_mod  = SettingsStore::module_enabled( 'module_adlaw', $cfg );
		$wy_remote  = $adlaw_mod && ! empty( $cfg['ad_law_remote_sync'] );

		$suite = array(
			array(
				'id'    => 'wp-core',
				'label' => __( 'WordPress 核心备案项', 'wpicp' ),
				'value' => __( '双向实时同步', 'wpicp' ),
				'state' => 'ok',
			),
			array(
				'id'    => 'wordyeah',
				'label' => __( '无言（WordYeah）合规', 'wpicp' ),
				'value' => ! $adlaw_mod
					? __( '未开启', 'wpicp' )
					: ( $wy_remote ? $wy_version . ' · api.wpcy.com' : __( '本地内置模式', 'wpicp' ) ),
				'state' => $wy_remote ? 'ok' : '',
			),
			array(
				'id'    => 'wpcy',
				'label' => __( '文派叶子（WPCY）', 'wpicp' ),
				'value' => $wpcy_on ? __( '服务页已协同', 'wpicp' ) : __( '未启用', 'wpicp' ),
				'state' => $wpcy_on ? 'ok' : '',
			),
			array(
				'id'    => 'bisheng',
				'label' => __( '笔笙（Bisheng HTML）', 'wpicp' ),
				'value' => $bisheng_on ? __( '资质与词库已共享', 'wpicp' ) : __( '待接入', 'wpicp' ),
				'state' => $bisheng_on ? 'ok' : '',
			),
		);

		// Check expiring certificates (< 30 days) only when badges module is active.
		$expiring_certs = array();
		$now_ts         = time();
		if ( SettingsStore::module_enabled( 'module_badges', $cfg ) && ! empty( $cfg['certificates'] ) && is_array( $cfg['certificates'] ) ) {
			foreach ( $cfg['certificates'] as $cert ) {
				if ( ! is_array( $cert ) || empty( $cert['expires_at'] ) ) {
					continue;
				}
				$exp_str = trim( (string) $cert['expires_at'] );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $exp_str ) ) {
					$exp_ts = strtotime( $exp_str . ' 23:59:59' );
					$day_s  = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
					if ( false !== $exp_ts && ( $exp_ts - $now_ts ) <= ( 30 * $day_s ) ) {
						$expiring_certs[] = ( '' !== (string) ( $cert['title'] ?? '' ) ? (string) $cert['title'] : __( '电子证照', 'wpicp' ) ) . '（' . $exp_str . '）';
					}
				}
			}
		}
		if ( ! empty( $expiring_certs ) ) {
			$checks[] = array(
				'id'     => 'cert_expiry',
				'label'  => __( '电子证照有效期提醒', 'wpicp' ),
				'value'  => sprintf(
					/* translators: %d: expiring certificate count */
					__( '%d 项即将或已到期', 'wpicp' ),
					count( $expiring_certs )
				),
				'state'  => 'warn',
				'detail' => implode( '、', $expiring_certs ),
			);
		}

		$has_bad  = false;
		$has_warn = false;
		foreach ( $checks as $c ) {
			if ( 'bad' === $c['state'] ) {
				$has_bad = true;
			} elseif ( 'warn' === $c['state'] ) {
				$has_warn = true;
			}
		}

		$overall = $has_bad ? 'bad' : ( $has_warn ? 'warn' : 'ok' );
		if ( $is_empty ) {
			$summary = __( '尚未填写 ICP 备案号与主体信息，请前往设置页完成登记。', 'wpicp' );
		} elseif ( $has_bad ) {
			$summary = __( '检测到备案编号格式有误，请核对工信部或公安网安备案号。', 'wpicp' );
		} elseif ( $has_warn ) {
			$summary = sprintf(
				/* translators: %d: filled item count */
				__( '已登记 %d 项资质，部分推荐公示项待完善。', 'wpicp' ),
				$filled_count
			);
		} else {
			$summary = sprintf(
				/* translators: %d: filled item count */
				__( '已完整登记 %d 项合规资质并实时同步全站。', 'wpicp' ),
				$filled_count
			);
		}

		return array(
			'overall_state' => $overall,
			'is_empty'      => $is_empty,
			'filled_count'  => $filled_count,
			'badge_count'   => $badge_count,
			'summary'       => $summary,
			'checks'        => $checks,
			'suite'         => $suite,
		);
	}
}
