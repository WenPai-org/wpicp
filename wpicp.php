<?php
/**
 * Plugin Name: WPICP - WordPress ICP备案插件
 * Plugin URI: https://example.com/wpicp
 * Description: 帮助中国WordPress站点进行ICP备案管理和显示的插件
 * Version: 1.0.0
 * Author: WordPress开发者
 * Author URI: https://example.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wpicp
 * Domain Path: /languages
 */

// 如果直接访问此文件，则退出
if (!defined('WPINC')) {
    die;
}

// 定义插件版本
define('WPICP_VERSION', '1.0.0');

// 定义插件目录
define('WPICP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// 定义插件URL
define('WPICP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 插件激活函数
 */
function activate_wpicp() {
    require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
    $core = new WPICP_Core();
    $core->create_database_tables();

    // 添加默认选项
    $default_options = array(
        'icp_number' => '',
        'display_location' => 'footer',
        'display_style' => 'default',
        'verification_method' => 'manual',
        'api_key' => '',
        'custom_link' => 'https://beian.miit.gov.cn/',
        'enable_sensitive_check' => 'no',
        // 新增选项
        'police_number' => '',
        'police_link' => 'http://www.beian.gov.cn/',
        'status_notification' => 'yes',
        'verification_api_provider' => 'none',
        'verification_api_key' => '',
        'beian_query_method' => 'manual',
        'beian_query_api_key' => '',
        'check_on_publish' => 'no',
        'scheduled_check' => 'no'
    );

    add_option('wpicp_options', $default_options);
}
register_activation_hook(__FILE__, 'activate_wpicp');

/**
 * 插件停用函数
 */
function deactivate_wpicp() {
    // 停用时的操作
    flush_rewrite_rules();

    // 清除计划任务
    wp_clear_scheduled_hook('wpicp_daily_status_check');
    wp_clear_scheduled_hook('wpicp_weekly_compliance_check');
}
register_deactivation_hook(__FILE__, 'deactivate_wpicp');

/**
 * 加载插件文本域
 */
function wpicp_load_textdomain() {
    load_plugin_textdomain('wpicp', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'wpicp_load_textdomain');

/**
 * 加载插件组件
 */
require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
require_once WPICP_PLUGIN_DIR . 'admin/class-wpicp-admin.php';
require_once WPICP_PLUGIN_DIR . 'public/class-wpicp-public.php';

/**
 * 加载样式
 */
function wpicp_enqueue_styles() {
    wp_enqueue_style('wpicp-style', WPICP_PLUGIN_URL . 'style.css', array(), WPICP_VERSION);
}
add_action('wp_enqueue_scripts', 'wpicp_enqueue_styles');
add_action('admin_enqueue_scripts', 'wpicp_enqueue_styles');

/**
 * 初始化插件组件
 */
function run_wpicp() {
    new WPICP_Admin();
    new WPICP_Public(); // 这里使用WPICP_Public类名
}
add_action('plugins_loaded', 'run_wpicp');
