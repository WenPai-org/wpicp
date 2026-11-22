<?php
/**
 * Admin Compliance Audit Tab (`tab=audit`, sections: `access` | `adlaw`).
 *
 * @var Wenpai_Admin_Settings $settings
 * @var array<string, mixed>  $shell
 * @var array<string, string> $display
 * @package WenPai\ICP
 */

use WenPai\ICP\Compliance\AccessMonitor;
use WenPai\ICP\Compliance\AdLawScanner;
use WenPai\ICP\Compliance\WordYeahClient;
use WenPai\ICP\Options\SettingsStore;
use WenPai\ICP\Rest\AuditController;

defined( 'ABSPATH' ) || exit;

$wpicp_is_network = function_exists( 'is_network_admin' ) && is_network_admin();
$wpicp_effective  = SettingsStore::effective();
$wpicp_base_url   = isset( $shell['tabs'][4]['url'] ) ? (string) $shell['tabs'][4]['url'] : '#audit';
$wpicp_is_proto   = false !== strpos( $wpicp_base_url, '.html' );

$wpicp_sections = array(
	'access' => __( '备案与接入保活', 'wpicp' ),
	'adlaw'  => __( '《广告法》极限词巡检', 'wpicp' ),
);
$wpicp_section  = Wenpai_Admin_UI::section( $wpicp_sections, 'access' );

$wpicp_scan_meta  = get_option( AuditController::SUMMARY_OPTION, array() );
$wpicp_has_scan   = is_array( $wpicp_scan_meta ) && ! empty( $wpicp_scan_meta['last_scan_at'] );
$wpicp_last_hits  = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['total_hits'] ) ? (int) $wpicp_scan_meta['total_hits'] : 0;
$wpicp_scan_posts = is_array( $wpicp_scan_meta ) && isset( $wpicp_scan_meta['scanned_posts'] ) ? (int) $wpicp_scan_meta['scanned_posts'] : 0;
$wpicp_scan_at    = is_array( $wpicp_scan_meta ) && ! empty( $wpicp_scan_meta['last_scan_at'] )
	? ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', (int) $wpicp_scan_meta['last_scan_at'] ) : gmdate( 'Y-m-d H:i', (int) $wpicp_scan_meta['last_scan_at'] ) )
	: '';

$wpicp_form_action = $wpicp_is_network && function_exists( 'network_admin_url' )
	? network_admin_url( 'edit.php?action=wpicp_save_network' )
	: 'options.php';

$wpicp_head_action = '';
if ( ! $wpicp_is_network ) {
	if ( 'access' === $wpicp_section ) {
		$wpicp_head_action = Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '立即执行全部检测', 'wpicp' ),
				'type'    => 'button',
				'variant' => 'primary',
				'attrs'   => array( 'data-wpicp-run-access-check' => '1' ),
			)
		);
	} else {
		$wpicp_head_action = Wenpai_Admin_UI::button_html(
			array(
				'label'   => $wpicp_has_scan ? __( '重新扫描全站', 'wpicp' ) : __( '开始全站巡检', 'wpicp' ),
				'type'    => 'button',
				'variant' => 'primary',
				'attrs'   => array( 'data-wpicp-scan-start' => '1' ),
			)
		);
	}
}

Wenpai_Admin_Shell::open( $shell );
Wenpai_Admin_Shell::page_head(
	__( '合规巡检', 'wpicp' ),
	__( '由无言（WordYeah）提供合规规则与核查能力，经 api.wpcy.com 获取远程词库。', 'wpicp' ),
	$wpicp_head_action
);

echo $settings->notices_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only network save confirmation flag.
if ( $wpicp_is_network && isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	Wenpai_Admin_UI::notice(
		array(
			'level' => 'ok',
			'text'  => __( '多站点网络合规巡检配置已保存。', 'wpicp' ),
		)
	);
}

$wpicp_subnav_items = array();
foreach ( $wpicp_sections as $wpicp_sid => $wpicp_slabel ) {
	$wpicp_subnav_items[] = array(
		'id'      => $wpicp_sid,
		'label'   => $wpicp_slabel,
		'url'     => $wpicp_is_proto
			? ( 'access' === $wpicp_sid ? 'audit.html' : 'audit-adlaw.html?section=adlaw' )
			: add_query_arg( 'section', $wpicp_sid, remove_query_arg( 'section', $wpicp_base_url ) ),
		'current' => $wpicp_sid === $wpicp_section,
	);
}
?>
<div class="wenpai-stack"<?php echo 'adlaw' === $wpicp_section ? ' data-wpicp-scanner="1"' : ''; ?>>
<?php
Wenpai_Admin_UI::subnav(
	array(
		'label' => __( '合规巡检模块', 'wpicp' ),
		'items' => $wpicp_subnav_items,
	)
);

if ( 'access' === $wpicp_section ) :
	$wpicp_snap       = AccessMonitor::last_status();
	$wpicp_home_snap  = isset( $wpicp_snap['home'] ) && is_array( $wpicp_snap['home'] ) ? $wpicp_snap['home'] : array();
	$wpicp_probe_snap = isset( $wpicp_snap['keepalive'] ) && is_array( $wpicp_snap['keepalive'] ) ? $wpicp_snap['keepalive'] : array();
	$wpicp_logs       = AccessMonitor::recent_logs();
	$wpicp_keep_on    = ! empty( $wpicp_effective['keepalive_enabled'] ) && '' !== trim( (string) $wpicp_effective['keepalive_url'] );
	$wpicp_provider   = AccessMonitor::provider_label( (string) $wpicp_effective['access_provider'] );

	$wpicp_ok_logs = 0;
	foreach ( $wpicp_logs as $wpicp_log ) {
		if ( is_array( $wpicp_log ) && ! empty( $wpicp_log['ok'] ) ) {
			++$wpicp_ok_logs;
		}
	}

	Wenpai_Admin_UI::notice(
		array(
			'level' => 'info',
			'text'  => __( '合规边界提示：本模块由无言（WordYeah）提供合规核查能力，用于自查首页备案规范、监测 DNS 漂移与按天/周低频维持原接入商服务器访问日志，不替代新增接入、变更或注销备案法定义务。', 'wpicp' ),
		)
	);

	Wenpai_Admin_UI::stat_hub(
		array(
			'drawer_id' => 'wpicp-access-drawer',
			'stats'     => array(
				array(
					'label' => __( '首页合规检测', 'wpicp' ),
					'value' => ! empty( $wpicp_home_snap )
						? (int) ( $wpicp_home_snap['passed'] ?? 0 ) . ' / ' . (int) ( $wpicp_home_snap['total'] ?? 4 )
						: __( '未执行', 'wpicp' ),
					'unit'  => ! empty( $wpicp_home_snap ) ? ( ! empty( $wpicp_home_snap['ok'] ) ? __( '通过', 'wpicp' ) : __( '待修', 'wpicp' ) ) : '',
				),
				array(
					'label' => __( '保活子域名 DNS', 'wpicp' ),
					'value' => ! $wpicp_keep_on
						? __( '未开启', 'wpicp' )
						: ( empty( $wpicp_probe_snap )
							? __( '待探测', 'wpicp' )
							: ( ! empty( $wpicp_probe_snap['ip_matched'] ) ? __( '命中', 'wpicp' ) : __( '漂移', 'wpicp' ) ) ),
					'unit'  => $wpicp_keep_on ? $wpicp_provider : __( '主站直连', 'wpicp' ),
				),
				array(
					'label' => __( '近 10 次保活成功', 'wpicp' ),
					'value' => ! empty( $wpicp_logs ) ? $wpicp_ok_logs . ' / ' . count( $wpicp_logs ) : '0',
					'unit'  => __( '次', 'wpicp' ),
				),
				array(
					'label' => __( '保活周期', 'wpicp' ),
					'value' => $wpicp_keep_on ? AccessMonitor::interval_label( (string) $wpicp_effective['keepalive_interval'] ) : __( '未启用', 'wpicp' ),
					'unit'  => '',
				),
			),
		)
	);

	$wpicp_checked_sub = ! empty( $wpicp_snap['checked_at'] )
		? sprintf(
			/* translators: %s: last check time */
			__( '上次检测时间：%s · 可随时点击右上角「立即执行全部检测」触发实时核查。', 'wpicp' ),
			(string) $wpicp_snap['checked_at']
		)
		: __( '尚未执行首页与接入商实时检测，点击右上角「立即执行全部检测」获取首次结果。', 'wpicp' );

	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '首页备案与接入商实时体检', 'wpicp' ),
			'sub'   => $wpicp_checked_sub,
			'tile'  => false,
		)
	);
	if ( empty( $wpicp_home_snap['checks'] ) && empty( $wpicp_probe_snap ) ) {
		?>
		<div class="wpicp-editor__empty">
			<div class="wpicp-editor__empty-main">
				<?php echo Wenpai_Admin_UI::tile_html( array( 'icon' => 'shield-check' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit HTML. ?>
				<div>
					<div class="wpicp-editor__empty-title"><?php esc_html_e( '尚未执行首页备案与接入体检', 'wpicp' ); ?></div>
					<div class="wpicp-editor__empty-desc"><?php esc_html_e( '点击右上角「立即执行全部检测」，立即核查首页 ICP 备案号、工信部官方链接、网页标题及保活子域名 DNS 归属。', 'wpicp' ); ?></div>
				</div>
			</div>
			<span class="meta"><?php esc_html_e( '支持每日自动巡检', 'wpicp' ); ?></span>
		</div>
		<?php
	} else {
		$wpicp_access_feed = array();
		if ( ! empty( $wpicp_home_snap['checks'] ) && is_array( $wpicp_home_snap['checks'] ) ) {
			foreach ( $wpicp_home_snap['checks'] as $wpicp_hc ) {
				$wpicp_st            = (string) ( $wpicp_hc['state'] ?? 'warn' );
				$wpicp_access_feed[] = array(
					'tag'   => 'ok' === $wpicp_st ? __( '通过', 'wpicp' ) : ( 'bad' === $wpicp_st ? __( '异常', 'wpicp' ) : __( '待补', 'wpicp' ) ),
					'tone'  => 'ok' === $wpicp_st ? 'ok' : ( 'bad' === $wpicp_st ? 'bad' : 'warn' ),
					'title' => (string) ( $wpicp_hc['label'] ?? '' ),
					'when'  => 'ok' === $wpicp_st ? (string) ( $wpicp_hc['value'] ?? '' ) : '',
					'note'  => 'ok' === $wpicp_st ? '' : trim( (string) ( $wpicp_hc['value'] ?? '' ) . ' · ' . (string) ( $wpicp_hc['detail'] ?? '' ), ' ·' ),
				);
			}
		}
		if ( $wpicp_keep_on && ! empty( $wpicp_probe_snap ) ) {
			$wpicp_dns_ok        = ! empty( $wpicp_probe_snap['ip_matched'] );
			$wpicp_http_ok       = 200 === (int) ( $wpicp_probe_snap['http_code'] ?? 0 ) && empty( $wpicp_probe_snap['blocked'] ) && ! empty( $wpicp_probe_snap['keyword_ok'] );
			$wpicp_access_feed[] = array(
				'tag'   => $wpicp_dns_ok ? __( '通过', 'wpicp' ) : __( '漂移', 'wpicp' ),
				'tone'  => $wpicp_dns_ok ? 'ok' : 'bad',
				'title' => __( '接入商 DNS 解析 IP 校验（A / AAAA / CNAME）', 'wpicp' ),
				'when'  => $wpicp_dns_ok ? ( (string) ( $wpicp_probe_snap['host'] ?? '' ) . ' → ' . (string) ( $wpicp_probe_snap['primary_ip'] ?? '' ) ) : '',
				'note'  => $wpicp_dns_ok ? '' : ( (string) ( $wpicp_probe_snap['primary_ip'] ?? '' ) . ' ' . __( '未命中预期接入商 IP 段', 'wpicp' ) ),
			);
			$wpicp_access_feed[] = array(
				'tag'   => $wpicp_http_ok ? __( '通过', 'wpicp' ) : __( '异常', 'wpicp' ),
				'tone'  => $wpicp_http_ok ? 'ok' : 'bad',
				'title' => __( '保活子域名定时访问与关键词校验', 'wpicp' ),
				'when'  => $wpicp_http_ok ? sprintf( '200 OK · %dms', (int) ( $wpicp_probe_snap['latency_ms'] ?? 0 ) ) : '',
				'note'  => $wpicp_http_ok ? '' : (string) ( $wpicp_probe_snap['result_label'] ?? __( '访问失败', 'wpicp' ) ),
			);
		}
		Wenpai_Admin_UI::tag_feed( array( 'items' => $wpicp_access_feed ) );
	}
	Wenpai_Admin_UI::card_close();

	$wpicp_export_url = function_exists( 'wp_nonce_url' )
		? wp_nonce_url( admin_url( 'admin-post.php?action=wpicp_export_keepalive_html' ), 'wpicp_export_keepalive_html' )
		: '#export-keepalive';
	?>
	<form class="wenpai-stack" method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
		<?php
		if ( function_exists( 'settings_fields' ) ) {
			settings_fields( $settings->group() );
		}
		echo $settings->tab_input_html( 'audit_access' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
		?>
		<input type="hidden" name="_wpicp_section" value="access">
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '接入商合规与子域名定时保活配置', 'wpicp' ),
				'sub'               => __( '主站直连原备案服务器时无需开启保活；采用多云或 CDN 时，可配置原接入商内地服务器子域名并按天/周低频探测。', 'wpicp' ),
				'tile'              => false,
				'head_control_html' => Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '导出单文件保活页 (index.html)', 'wpicp' ),
						'href'    => $wpicp_export_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					)
				),
			)
		);
		$settings->render_field( 'access_provider' );
		$settings->render_field( 'home_check_enabled' );
		$settings->render_field( 'keepalive_enabled' );
		$settings->render_field( 'keepalive_url' );
		$settings->render_field( 'expected_provider_ips' );
		$settings->render_field( 'keepalive_interval' );
		$settings->render_field( 'keepalive_keyword' );
		$settings->render_field( 'alert_email' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '保活子域名对应页面须展示真实合规的主体与备案号，切勿放空白页或无关内容。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存合规与保活配置', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'secondary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>

	<?php
	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '最近保活访问与 DNS 核查日志', 'wpicp' ),
			'tile'  => false,
		)
	);
	if ( empty( $wpicp_logs ) ) :
		?>
		<p class="meta"><?php esc_html_e( '暂无子域名保活访问记录；开启子域名保活并执行探测后，将自动保留最近 10 次记录。', 'wpicp' ); ?></p>
	<?php else : ?>
		<div class="wenpai-table-wrap">
			<table class="tbl">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( '时间', 'wpicp' ); ?></th>
						<th scope="col"><?php esc_html_e( '目标地址', 'wpicp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'DNS 落地 IP', 'wpicp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'HTTP / 耗时', 'wpicp' ); ?></th>
						<th scope="col"><?php esc_html_e( '校验结果', 'wpicp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $wpicp_logs, 0, 10 ) as $wpicp_log ) : ?>
						<?php
						$wpicp_l_ok  = ! empty( $wpicp_log['ok'] );
						$wpicp_l_ip  = (string) ( $wpicp_log['primary_ip'] ?? '' );
						$wpicp_l_m   = ! empty( $wpicp_log['ip_matched'] ) ? __( '匹配', 'wpicp' ) : __( '不符', 'wpicp' );
						$wpicp_l_cod = (int) ( $wpicp_log['http_code'] ?? 0 );
						$wpicp_l_ms  = (int) ( $wpicp_log['latency_ms'] ?? 0 );
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $wpicp_log['time'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( (string) ( $wpicp_log['url'] ?? '' ) ); ?></code></td>
							<td><?php echo esc_html( $wpicp_l_ip . '（' . $wpicp_l_m . '）' ); ?></td>
							<td><?php echo esc_html( $wpicp_l_cod > 0 ? sprintf( '%d · %dms', $wpicp_l_cod, $wpicp_l_ms ) : __( '连接失败', 'wpicp' ) ); ?></td>
							<td>
								<?php
								$wpicp_pill_html = Wenpai_Admin_UI::pill_html(
									array(
										'text'  => (string) ( $wpicp_log['result_label'] ?? '' ),
										'state' => $wpicp_l_ok ? 'ok' : 'bad',
									)
								);
								echo $wpicp_pill_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
	<?php
	Wenpai_Admin_UI::card_close();

else :
	$wpicp_builtin_count = count( AdLawScanner::dictionary() );
	$wpicp_safe_count    = count( AdLawScanner::safe_compounds() );
	$wpicp_wy_cache      = WordYeahClient::cached();
	$wpicp_wy_remote_cnt = count( $wpicp_wy_cache['terms'] );
	$wpicp_total_terms   = $wpicp_builtin_count + $wpicp_wy_remote_cnt;
	$wpicp_wy_ver        = '' !== $wpicp_wy_cache['version'] ? $wpicp_wy_cache['version'] : __( '内置版', 'wpicp' );
	$wpicp_wy_unit       = '' !== $wpicp_wy_cache['version'] ? __( '已缓存', 'wpicp' ) : __( '待同步', 'wpicp' );

	Wenpai_Admin_UI::stat_hub(
		array(
			'drawer_id' => 'wpicp-adlaw-drawer',
			'stats'     => array(
				array(
					'label' => __( '生效违禁词库', 'wpicp' ),
					'value' => (string) $wpicp_total_terms,
					'unit'  => $wpicp_wy_remote_cnt > 0 ? __( '条（含远程）', 'wpicp' ) : __( '条（内置）', 'wpicp' ),
				),
				array(
					'label' => __( '安全复合词豁免', 'wpicp' ),
					'value' => (string) $wpicp_safe_count,
					'unit'  => __( '条', 'wpicp' ),
				),
				array(
					'label' => __( '词库版本（无言）', 'wpicp' ),
					'value' => $wpicp_wy_ver,
					'unit'  => $wpicp_wy_unit,
				),
				array(
					'label' => __( '上次巡检结果', 'wpicp' ),
					'value' => $wpicp_has_scan ? (string) $wpicp_last_hits : __( '未执行', 'wpicp' ),
					'unit'  => $wpicp_has_scan ? __( '处风险', 'wpicp' ) : '',
				),
			),
		)
	);

	if ( ! $wpicp_is_network ) :
		$wpicp_scan_sub = $wpicp_has_scan
			? sprintf(
				/* translators: 1: scan timestamp, 2: post count */
				__( '上次扫描时间：%1$s · 共扫描 %2$d 篇公开文章与页面', 'wpicp' ),
				$wpicp_scan_at,
				$wpicp_scan_posts
			)
			: __( '使用无言（WordYeah）本地缓存词库离线扫描，站点内容不上传外部服务器。', 'wpicp' );

		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '全站《广告法》极限词巡检结果', 'wpicp' ),
				'sub'   => $wpicp_scan_sub,
				'tile'  => false,
			)
		);
		?>
		<div class="wpicp-audit__progress" data-wpicp-scan-progress hidden>
			<?php
			Wenpai_Admin_UI::progressbar(
				array(
					'value' => 0,
					'label' => __( '全站巡检进度', 'wpicp' ),
				)
			);
			?>
		</div>
		<div class="wpicp-audit__results" data-wpicp-scan-results>
			<?php if ( ! $wpicp_has_scan ) : ?>
				<div class="wpicp-editor__empty">
					<div class="wpicp-editor__empty-main">
						<?php echo Wenpai_Admin_UI::tile_html( array( 'icon' => 'shield-check' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe kit HTML. ?>
						<div>
							<div class="wpicp-editor__empty-title"><?php esc_html_e( '尚未执行全站极限词巡检', 'wpicp' ); ?></div>
							<div class="wpicp-editor__empty-desc"><?php esc_html_e( '点击右上角「开始全站巡检」，将自动剥离脚本样式并检测文章、页面及站点副标题中的绝对化用语。', 'wpicp' ); ?></div>
						</div>
					</div>
					<span class="meta"><?php esc_html_e( '本地毫秒级离线扫描', 'wpicp' ); ?></span>
				</div>
			<?php elseif ( 0 === $wpicp_last_hits ) : ?>
				<?php
				Wenpai_Admin_UI::notice(
					array(
						'level' => 'ok',
						'text'  => sprintf(
							/* translators: %d: scanned post count */
							__( '已扫描 %d 篇公开内容，未发现《广告法》极限词风险表达。', 'wpicp' ),
							$wpicp_scan_posts
						),
					)
				);
				?>
			<?php else : ?>
				<?php
				Wenpai_Admin_UI::notice(
					array(
						'level' => 'warn',
						'text'  => sprintf(
							/* translators: %d: hit count */
							__( '上次扫描共发现 %d 处风险表达；点击右上角「重新扫描全站」即可展开逐条高亮摘录、直达编辑与一键加入白名单。', 'wpicp' ),
							$wpicp_last_hits
						),
					)
				);
				?>
			<?php endif; ?>
		</div>
		<?php
		Wenpai_Admin_UI::card_close();
	endif;

	$wpicp_wy_desc = sprintf(
		/* translators: 1: endpoint url, 2: sync status, 3: remote count, 4: builtin count */
		__( '数据源：%1$s · %2$s（远程 %3$d 条 + 内置 %4$d 条）', 'wpicp' ),
		WordYeahClient::endpoint_url(),
		'' !== $wpicp_wy_cache['synced_at']
			? sprintf(
				/* translators: %s: timestamp */
				__( '上次同步：%s', 'wpicp' ),
				$wpicp_wy_cache['synced_at']
			)
			: __( '尚未同步远程词库，当前使用内置词库', 'wpicp' ),
		$wpicp_wy_remote_cnt,
		$wpicp_builtin_count
	);

	Wenpai_Admin_UI::card_open(
		array(
			'title' => __( '无言（WordYeah）合规词库服务', 'wpicp' ),
			'sub'   => __( '词库由合规品牌无言（WordYeah）持续维护，仅单向拉取规则 JSON 至本地缓存，不回传任何站点文章内容。', 'wpicp' ),
			'tile'  => false,
		)
	);
	Wenpai_Admin_UI::row(
		array(
			'icon'         => 'cloud',
			'state'        => '' !== $wpicp_wy_cache['version'] ? 'ok' : '',
			'title'        => sprintf(
				/* translators: %s: ruleset version */
				__( '无言《广告法》极限词与安全复合词库（%s）', 'wpicp' ),
				$wpicp_wy_ver
			),
			'desc'         => $wpicp_wy_desc,
			'control_html' => Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '立即同步远程词库', 'wpicp' ),
					'type'    => 'button',
					'variant' => 'secondary',
					'attrs'   => array( 'data-wpicp-sync-wordyeah' => '1' ),
				)
			),
		)
	);
	Wenpai_Admin_UI::card_close();
	?>
	<form method="post" action="<?php echo esc_url( $wpicp_form_action ); ?>">
		<?php
		if ( function_exists( 'settings_fields' ) ) {
			settings_fields( $settings->group() );
		}
		echo $settings->tab_input_html( 'audit_adlaw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by kit.
		?>
		<input type="hidden" name="_wpicp_section" value="adlaw">
		<?php
		Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '巡检规则与豁免白名单', 'wpicp' ),
				'tile'  => false,
			)
		);
		$settings->render_field( 'ad_law_enabled' );
		$settings->render_field( 'ad_law_remote_sync' );
		$settings->render_field( 'ad_law_sync_interval' );
		$settings->render_field( 'ad_law_rule_groups' );
		$settings->render_field( 'ad_law_editor_panel' );
		$settings->render_field( 'ad_law_scan_post_types' );
		$settings->render_field( 'ad_law_allowlist' );
		Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '远程词库拉取失败时自动回退至内置基础词库；默认豁免「第一时间」「唯一标识」等安全复合词。', 'wpicp' ),
				'actions' => array(
					array(
						'label'   => __( '保存巡检配置', 'wpicp' ),
						'type'    => 'submit',
						'variant' => 'secondary',
					),
				),
			)
		);
		Wenpai_Admin_UI::card_close();
		?>
	</form>
<?php endif; ?>
</div>
<?php
Wenpai_Admin_Shell::close( $shell );
