<?php
/**
 * 在插件卸载时执行的操作
 *
 * @package WPICP
 */

// 如果未通过WordPress调用卸载，则退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 删除插件选项
delete_option('wpicp_options');
delete_option('wpicp_last_scheduled_check');
delete_option('wpicp_last_check_results');

// 删除所有临时选项
delete_transient('wpicp_sensitive_words');
delete_transient('wpicp_verification_result');
delete_transient('wpicp_admin_notice_dismissed');

// 删除任何以wpicp_开头的选项
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'wpicp_%'");

// 删除插件创建的表
$tables = array(
    'wpicp_records',
    'wpicp_sensitive_words',
    'wpicp_real_name_info',
    'wpicp_record_history',
    'wpicp_status'
);

foreach($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . $table);
}

// 清除用户元数据
$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE 'wpicp_%'");

// 清除评论元数据
$wpdb->query("DELETE FROM $wpdb->commentmeta WHERE meta_key LIKE 'wpicp_%'");

// 清除已计划的事件
wp_clear_scheduled_hook('wpicp_daily_verification');
wp_clear_scheduled_hook('wpicp_daily_status_check');

// 删除上传的文件
$upload_dir = wp_upload_dir();
$verification_dir = $upload_dir['basedir'] . '/wpicp-verification/';

if(file_exists($verification_dir)) {
    // 删除目录中的所有文件
    $files = glob($verification_dir . '*');
    foreach($files as $file) {
        if(is_file($file)) {
            unlink($file);
        }
    }

    // 删除目录
    rmdir($verification_dir);
}
