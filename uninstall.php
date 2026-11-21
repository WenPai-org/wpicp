<?php
/**
 * Uninstall handler for WPICP.
 *
 * Strictly preserves user filing data unless `clean_on_uninstall` was explicitly enabled.
 * WordPress Core `zh_cn_l10n_icp_num` and media library attachments are never deleted.
 *
 * @package WenPai\ICP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wpicp_clean_site = static function () {
	$settings = get_option( 'wpicp_settings', array() );
	if ( ! is_array( $settings ) || empty( $settings['clean_on_uninstall'] ) ) {
		return;
	}
	delete_option( 'wpicp_settings' );
	delete_option( 'wpicp_audit_summary' );
	delete_option( 'wpicp_migrated_at' );
	delete_option( 'wpicp_legacy_takeover_notice' );
};

if ( is_multisite() ) {
	$wpicp_net = get_site_option( 'wpicp_network_settings', array() );
	if ( is_array( $wpicp_net ) && ! empty( $wpicp_net['clean_on_uninstall'] ) ) {
		delete_site_option( 'wpicp_network_settings' );
		delete_site_option( 'wpicp_legacy_takeover_notice' );
	}

	$wpicp_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wpicp_site_ids as $wpicp_site_id ) {
		switch_to_blog( (int) $wpicp_site_id );
		$wpicp_clean_site();
		restore_current_blog();
	}
} else {
	$wpicp_clean_site();
}
