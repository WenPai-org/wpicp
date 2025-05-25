<?php
/**
 * 插件的管理面板功能
 *
 * @package WPICP
 * @subpackage WPICP/admin
 */

// 如果直接访问此文件，则退出
if (!defined('WPINC')) {
    die;
}

/**
 * 管理面板类
 */
class WPICP_Admin {

    /**
     * 初始化类并设置属性
     */
    public function __construct() {
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));

        // 添加设置链接
        add_filter('plugin_action_links_' . plugin_basename(WPICP_PLUGIN_DIR . 'wpicp.php'),
            array($this, 'add_action_links')
        );

        // 添加管理员通知
        add_action('admin_notices', array($this, 'display_admin_notices'));

        // 注册状态检查钩子
        add_action('wpicp_daily_status_check', array($this, 'schedule_status_check'));

        // 确保状态检查定时任务已安排
        if (!wp_next_scheduled('wpicp_daily_status_check')) {
            wp_schedule_event(time(), 'daily', 'wpicp_daily_status_check');
        }

        // 注册内容检查钩子
        add_action('save_post', array($this, 'check_post_content'), 10, 3);
        add_action('comment_post', array($this, 'check_comment_content'), 10, 3);

        // 添加后台内容过滤
        add_filter('content_save_pre', array($this, 'filter_post_content'));
        add_filter('comment_text', array($this, 'filter_comment_content'));

        // 新增实名信息表单处理
        add_action('admin_post_wpicp_save_real_name', array($this, 'handle_real_name_form'));

        // 文件上传处理
        add_action('admin_post_wpicp_upload_verification', array($this, 'handle_verification_upload'));

        // 敏感词管理
        add_action('admin_post_wpicp_import_sensitive_words', array($this, 'handle_sensitive_words_import'));
        add_action('admin_post_wpicp_export_sensitive_words', array($this, 'handle_sensitive_words_export'));
    }

    /**
     * 添加管理菜单
     */
    public function add_plugin_admin_menu() {
        // 主菜单
        add_menu_page(
            __('ICP备案管理', 'wpicp'),
            __('ICP备案', 'wpicp'),
            'manage_options',
            'wpicp',
            array($this, 'display_plugin_admin_page'),
            'dashicons-shield',
            100
        );

        // 子菜单 - 设置
        add_submenu_page(
            'wpicp',
            __('ICP备案设置', 'wpicp'),
            __('设置', 'wpicp'),
            'manage_options',
            'wpicp',
            array($this, 'display_plugin_admin_page')
        );

        // 子菜单 - 备案信息管理
        add_submenu_page(
            'wpicp',
            __('备案信息管理', 'wpicp'),
            __('备案信息', 'wpicp'),
            'manage_options',
            'wpicp-management',
            array($this, 'display_icp_management_page')
        );

        // 新增 - 子菜单 - 实名认证
        add_submenu_page(
            'wpicp',
            __('实名认证', 'wpicp'),
            __('实名认证', 'wpicp'),
            'manage_options',
            'wpicp-verification',
            array($this, 'display_verification_page')
        );

        // 新增 - 子菜单 - 备案状态
        add_submenu_page(
            'wpicp',
            __('备案状态', 'wpicp'),
            __('备案状态', 'wpicp'),
            'manage_options',
            'wpicp-status',
            array($this, 'display_status_page')
        );

        // 子菜单 - 合规检查
        add_submenu_page(
            'wpicp',
            __('合规检查', 'wpicp'),
            __('合规检查', 'wpicp'),
            'manage_options',
            'wpicp-compliance',
            array($this, 'display_compliance_check_page')
        );

        // 新增 - 子菜单 - 敏感词管理
        add_submenu_page(
            'wpicp',
            __('敏感词管理', 'wpicp'),
            __('敏感词管理', 'wpicp'),
            'manage_options',
            'wpicp-sensitive',
            array($this, 'display_sensitive_words_page')
        );

        // 新增 - 子菜单 - 备案指南
        add_submenu_page(
            'wpicp',
            __('备案指南', 'wpicp'),
            __('备案指南', 'wpicp'),
            'manage_options',
            'wpicp-guide',
            array($this, 'display_beian_guide_page')
        );

        // 新增 - 子菜单 - 常见问题
        add_submenu_page(
            'wpicp',
            __('常见问题', 'wpicp'),
            __('常见问题', 'wpicp'),
            'manage_options',
            'wpicp-faq',
            array($this, 'display_faq_page')
        );
    }

    /**
     * 注册插件设置
     */
    public function register_settings() {
        register_setting(
            'wpicp_options_group',
            'wpicp_options',
            array($this, 'sanitize_options')
        );

        // 基本设置部分
        add_settings_section(
            'wpicp_basic_section',
            __('基本设置', 'wpicp'),
            array($this, 'print_basic_section_info'),
            'wpicp'
        );

        add_settings_field(
            'icp_number',
            __('ICP备案号', 'wpicp'),
            array($this, 'icp_number_callback'),
            'wpicp',
            'wpicp_basic_section'
        );

        add_settings_field(
            'custom_link',
            __('ICP链接地址', 'wpicp'),
            array($this, 'custom_link_callback'),
            'wpicp',
            'wpicp_basic_section'
        );

        // 新增 - 公安备案设置
        add_settings_field(
            'police_number',
            __('公安备案号', 'wpicp'),
            array($this, 'police_number_callback'),
            'wpicp',
            'wpicp_basic_section'
        );

        add_settings_field(
            'police_link',
            __('公安备案链接', 'wpicp'),
            array($this, 'police_link_callback'),
            'wpicp',
            'wpicp_basic_section'
        );

        // 显示设置部分
        add_settings_section(
            'wpicp_display_section',
            __('显示设置', 'wpicp'),
            array($this, 'print_display_section_info'),
            'wpicp'
        );

        add_settings_field(
            'display_location',
            __('显示位置', 'wpicp'),
            array($this, 'display_location_callback'),
            'wpicp',
            'wpicp_display_section'
        );

        add_settings_field(
            'display_style',
            __('显示样式', 'wpicp'),
            array($this, 'display_style_callback'),
            'wpicp',
            'wpicp_display_section'
        );

        // 新增 - 状态通知设置
        add_settings_field(
            'status_notification',
            __('状态通知', 'wpicp'),
            array($this, 'status_notification_callback'),
            'wpicp',
            'wpicp_display_section'
        );

        // API设置部分
        add_settings_section(
            'wpicp_api_section',
            __('API设置', 'wpicp'),
            array($this, 'print_api_section_info'),
            'wpicp'
        );

        add_settings_field(
            'verification_method',
            __('验证方式', 'wpicp'),
            array($this, 'verification_method_callback'),
            'wpicp',
            'wpicp_api_section'
        );

        add_settings_field(
            'api_key',
            __('API密钥', 'wpicp'),
            array($this, 'api_key_callback'),
            'wpicp',
            'wpicp_api_section'
        );

        // 新增 - 实名验证API设置
        add_settings_field(
            'verification_api_provider',
            __('实名验证API提供商', 'wpicp'),
            array($this, 'verification_api_provider_callback'),
            'wpicp',
            'wpicp_api_section'
        );

        add_settings_field(
            'verification_api_key',
            __('实名验证API密钥', 'wpicp'),
            array($this, 'verification_api_key_callback'),
            'wpicp',
            'wpicp_api_section'
        );

        // 新增 - 备案查询API设置
        add_settings_field(
            'beian_query_method',
            __('备案查询方式', 'wpicp'),
            array($this, 'beian_query_method_callback'),
            'wpicp',
            'wpicp_api_section'
        );

        add_settings_field(
            'beian_query_api_key',
            __('备案查询API密钥', 'wpicp'),
            array($this, 'beian_query_api_key_callback'),
            'wpicp',
            'wpicp_api_section'
        );

        // 合规设置部分
        add_settings_section(
            'wpicp_compliance_section',
            __('合规设置', 'wpicp'),
            array($this, 'print_compliance_section_info'),
            'wpicp'
        );

        add_settings_field(
            'enable_sensitive_check',
            __('启用敏感内容检查', 'wpicp'),
            array($this, 'enable_sensitive_check_callback'),
            'wpicp',
            'wpicp_compliance_section'
        );

        // 新增 - 内容发布检查设置
        add_settings_field(
            'check_on_publish',
            __('发布前检查内容', 'wpicp'),
            array($this, 'check_on_publish_callback'),
            'wpicp',
            'wpicp_compliance_section'
        );

        // 新增 - 定期检查设置
        add_settings_field(
            'scheduled_check',
            __('定期检查网站', 'wpicp'),
            array($this, 'scheduled_check_callback'),
            'wpicp',
            'wpicp_compliance_section'
        );
    }

    /**
     * 选项数据净化
     */
    public function sanitize_options($input) {
        $new_input = array();

        if(isset($input['icp_number']))
            $new_input['icp_number'] = sanitize_text_field($input['icp_number']);

        if(isset($input['display_location']))
            $new_input['display_location'] = sanitize_text_field($input['display_location']);

        if(isset($input['display_style']))
            $new_input['display_style'] = sanitize_text_field($input['display_style']);

        if(isset($input['verification_method']))
            $new_input['verification_method'] = sanitize_text_field($input['verification_method']);

        if(isset($input['api_key']))
            $new_input['api_key'] = sanitize_text_field($input['api_key']);

        if(isset($input['custom_link']))
            $new_input['custom_link'] = esc_url_raw($input['custom_link']);

        if(isset($input['enable_sensitive_check']))
            $new_input['enable_sensitive_check'] = sanitize_text_field($input['enable_sensitive_check']);

        // 新增字段验证
        if(isset($input['police_number']))
            $new_input['police_number'] = sanitize_text_field($input['police_number']);

        if(isset($input['police_link']))
            $new_input['police_link'] = esc_url_raw($input['police_link']);

        if(isset($input['status_notification']))
            $new_input['status_notification'] = sanitize_text_field($input['status_notification']);

        if(isset($input['verification_api_provider']))
            $new_input['verification_api_provider'] = sanitize_text_field($input['verification_api_provider']);

        if(isset($input['verification_api_key']))
            $new_input['verification_api_key'] = sanitize_text_field($input['verification_api_key']);

        if(isset($input['beian_query_method']))
            $new_input['beian_query_method'] = sanitize_text_field($input['beian_query_method']);

        if(isset($input['beian_query_api_key']))
            $new_input['beian_query_api_key'] = sanitize_text_field($input['beian_query_api_key']);

        if(isset($input['check_on_publish']))
            $new_input['check_on_publish'] = sanitize_text_field($input['check_on_publish']);

        if(isset($input['scheduled_check']))
            $new_input['scheduled_check'] = sanitize_text_field($input['scheduled_check']);

        return $new_input;
    }

    /**
     * 打印基本设置部分信息
     */
    public function print_basic_section_info() {
        echo '<p>' . __('请输入您的ICP备案基本信息。', 'wpicp') . '</p>';
    }

    /**
     * 打印显示设置部分信息
     */
    public function print_display_section_info() {
        echo '<p>' . __('控制ICP备案信息在前台的显示方式。', 'wpicp') . '</p>';
    }

    /**
     * 打印API设置部分信息
     */
    public function print_api_section_info() {
        echo '<p>' . __('设置验证和查询方式。', 'wpicp') . '</p>';
    }

    /**
     * 打印合规设置部分信息
     */
    public function print_compliance_section_info() {
        echo '<p>' . __('网站内容合规性检查设置。', 'wpicp') . '</p>';
    }

    /**
     * ICP备案号设置字段回调
     */
    public function icp_number_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['icp_number']) ? $options['icp_number'] : '';
        echo '<input type="text" id="icp_number" name="wpicp_options[icp_number]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('请输入您的ICP备案号，例如：京ICP备12345678号-1', 'wpicp') . '</p>';
    }

    /**
     * 自定义链接设置字段回调
     */
    public function custom_link_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['custom_link']) ? $options['custom_link'] : 'https://beian.miit.gov.cn/';
        echo '<input type="url" id="custom_link" name="wpicp_options[custom_link]" value="' . esc_url($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('自定义ICP备案链接，默认为工信部网站', 'wpicp') . '</p>';
    }

    /**
     * 新增 - 公安备案号设置字段回调
     */
    public function police_number_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['police_number']) ? $options['police_number'] : '';
        echo '<input type="text" id="police_number" name="wpicp_options[police_number]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('请输入您的公安备案号，例如：京公网安备11010502030216号', 'wpicp') . '</p>';
    }

    /**
     * 新增 - 公安备案链接设置字段回调
     */
    public function police_link_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['police_link']) ? $options['police_link'] : 'http://www.beian.gov.cn/';
        echo '<input type="url" id="police_link" name="wpicp_options[police_link]" value="' . esc_url($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('自定义公安备案链接，默认为公安部网站', 'wpicp') . '</p>';
    }

    /**
     * 显示位置设置字段回调
     */
    public function display_location_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['display_location']) ? $options['display_location'] : 'footer';
        ?>
        <select id="display_location" name="wpicp_options[display_location]">
            <option value="footer" <?php selected($value, 'footer'); ?>><?php _e('页脚', 'wpicp'); ?></option>
            <option value="sidebar" <?php selected($value, 'sidebar'); ?>><?php _e('侧边栏小工具', 'wpicp'); ?></option>
            <option value="manual" <?php selected($value, 'manual'); ?>><?php _e('手动插入 [wpicp_info] 短代码', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('选择ICP备案信息显示位置', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 显示样式设置字段回调
     */
    public function display_style_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['display_style']) ? $options['display_style'] : 'default';
        ?>
        <select id="display_style" name="wpicp_options[display_style]">
            <option value="default" <?php selected($value, 'default'); ?>><?php _e('默认样式', 'wpicp'); ?></option>
            <option value="simple" <?php selected($value, 'simple'); ?>><?php _e('简洁样式', 'wpicp'); ?></option>
            <option value="dark" <?php selected($value, 'dark'); ?>><?php _e('深色样式', 'wpicp'); ?></option>
            <option value="custom" <?php selected($value, 'custom'); ?>><?php _e('自定义样式', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('选择ICP备案信息显示样式', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 新增 - 状态通知设置字段回调
     */
    public function status_notification_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['status_notification']) ? $options['status_notification'] : 'admin_only';
        ?>
        <select id="status_notification" name="wpicp_options[status_notification]">
            <option value="admin_only" <?php selected($value, 'admin_only'); ?>><?php _e('仅管理员可见', 'wpicp'); ?></option>
            <option value="all_users" <?php selected($value, 'all_users'); ?>><?php _e('所有已登录用户可见', 'wpicp'); ?></option>
            <option value="public" <?php selected($value, 'public'); ?>><?php _e('所有访问者可见', 'wpicp'); ?></option>
            <option value="none" <?php selected($value, 'none'); ?>><?php _e('不显示', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('选择备案状态通知的显示范围', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 验证方式设置字段回调
     */
    public function verification_method_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['verification_method']) ? $options['verification_method'] : 'manual';
        ?>
        <select id="verification_method" name="wpicp_options[verification_method]">
            <option value="manual" <?php selected($value, 'manual'); ?>><?php _e('手动设置', 'wpicp'); ?></option>
            <option value="api" <?php selected($value, 'api'); ?>><?php _e('使用第三方API', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('选择备案信息验证方式', 'wpicp'); ?></p>
        <?php
    }

    /**
     * API密钥设置字段回调
     */
    public function api_key_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['api_key']) ? $options['api_key'] : '';
        echo '<input type="text" id="api_key" name="wpicp_options[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('如使用API验证，请输入API密钥', 'wpicp') . '</p>';
    }

    /**
     * 新增 - 实名验证API提供商设置字段回调
     */
    public function verification_api_provider_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['verification_api_provider']) ? $options['verification_api_provider'] : '';
        ?>
        <select id="verification_api_provider" name="wpicp_options[verification_api_provider]">
            <option value="" <?php selected($value, ''); ?>><?php _e('不使用API', 'wpicp'); ?></option>
            <option value="aliyun" <?php selected($value, 'aliyun'); ?>><?php _e('阿里云API', 'wpicp'); ?></option>
            <option value="tencent" <?php selected($value, 'tencent'); ?>><?php _e('腾讯云API', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('选择实名验证API提供商', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 新增 - 实名验证API密钥设置字段回调
     */
    public function verification_api_key_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['verification_api_key']) ? $options['verification_api_key'] : '';
        echo '<input type="text" id="verification_api_key" name="wpicp_options[verification_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('如使用实名验证API，请输入API密钥', 'wpicp') . '</p>';
    }

    /**
     * 新增 - 备案查询方式设置字段回调
     */
    public function beian_query_method_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['beian_query_method']) ? $options['beian_query_method'] : 'manual';
        ?>
        <select id="beian_query_method" name="wpicp_options[beian_query_method]">
            <option value="manual" <?php selected($value, 'manual'); ?>><?php _e('手动设置', 'wpicp'); ?></option>
            <option value="api" <?php selected($value, 'api'); ?>><?php _e('使用第三方API', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('选择备案状态查询方式', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 新增 - 备案查询API密钥设置字段回调
     */
    public function beian_query_api_key_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['beian_query_api_key']) ? $options['beian_query_api_key'] : '';
        echo '<input type="text" id="beian_query_api_key" name="wpicp_options[beian_query_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('如使用备案查询API，请输入API密钥', 'wpicp') . '</p>';
    }

    /**
     * 启用敏感内容检查设置字段回调
     */
    public function enable_sensitive_check_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['enable_sensitive_check']) ? $options['enable_sensitive_check'] : 'no';
        ?>
        <select id="enable_sensitive_check" name="wpicp_options[enable_sensitive_check]">
            <option value="yes" <?php selected($value, 'yes'); ?>><?php _e('是', 'wpicp'); ?></option>
            <option value="no" <?php selected($value, 'no'); ?>><?php _e('否', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('是否启用敏感内容检查功能', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 新增 - 发布前检查设置字段回调
     */
    public function check_on_publish_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['check_on_publish']) ? $options['check_on_publish'] : 'warning';
        ?>
        <select id="check_on_publish" name="wpicp_options[check_on_publish]">
            <option value="no" <?php selected($value, 'no'); ?>><?php _e('不检查', 'wpicp'); ?></option>
            <option value="warning" <?php selected($value, 'warning'); ?>><?php _e('仅警告', 'wpicp'); ?></option>
            <option value="block" <?php selected($value, 'block'); ?>><?php _e('阻止发布', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('发布内容前是否检查敏感内容', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 新增 - 定期检查设置字段回调
     */
    public function scheduled_check_callback() {
        $options = get_option('wpicp_options');
        $value = isset($options['scheduled_check']) ? $options['scheduled_check'] : 'weekly';
        ?>
        <select id="scheduled_check" name="wpicp_options[scheduled_check]">
            <option value="no" <?php selected($value, 'no'); ?>><?php _e('不检查', 'wpicp'); ?></option>
            <option value="daily" <?php selected($value, 'daily'); ?>><?php _e('每天', 'wpicp'); ?></option>
            <option value="weekly" <?php selected($value, 'weekly'); ?>><?php _e('每周', 'wpicp'); ?></option>
            <option value="monthly" <?php selected($value, 'monthly'); ?>><?php _e('每月', 'wpicp'); ?></option>
        </select>
        <p class="description"><?php _e('定期自动检查网站内容合规性', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 显示插件管理页面
     */
    public function display_plugin_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php
                    settings_fields('wpicp_options_group');
                    do_settings_sections('wpicp');
                    submit_button();
                ?>
            </form>

            <div class="wpicp-admin-info">
                <h2><?php _e('ICP备案指南', 'wpicp'); ?></h2>
                <p><?php _e('根据中国法律法规，所有在中国境内提供服务的网站都必须进行ICP备案。', 'wpicp'); ?></p>
                <p><?php _e('您可以通过以下步骤进行ICP备案：', 'wpicp'); ?></p>
                <ol>
                    <li><?php _e('联系您的网站托管服务商，咨询备案流程', 'wpicp'); ?></li>
                    <li><?php _e('准备备案所需的资料（个人身份证、企业营业执照等）', 'wpicp'); ?></li>
                    <li><?php _e('填写并提交备案信息', 'wpicp'); ?></li>
                    <li><?php _e('等待审核通过', 'wpicp'); ?></li>
                    <li><?php _e('获得备案号后，在本插件中填写相关信息', 'wpicp'); ?></li>
                </ol>
                <p><a href="https://beian.miit.gov.cn/" target="_blank"><?php _e('访问工信部ICP备案管理系统', 'wpicp'); ?></a></p>
                <p><a href="http://www.beian.gov.cn/" target="_blank"><?php _e('访问全国互联网安全管理服务平台', 'wpicp'); ?></a></p>
                <p><a href="<?php echo admin_url('admin.php?page=wpicp-guide'); ?>"><?php _e('查看详细备案指南', 'wpicp'); ?></a></p>
            </div>

            <?php
            // 显示备案状态
            $this->display_status_summary();
            ?>
        </div>
        <?php
    }

    /**
     * 显示ICP备案信息管理页面
     */
    public function display_icp_management_page() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 处理表单提交
        if(isset($_POST['wpicp_save_record']) && check_admin_referer('wpicp_save_record', 'wpicp_nonce')) {
            $name = isset($_POST['wpicp_name']) ? sanitize_text_field($_POST['wpicp_name']) : '';
            $icp_number = isset($_POST['wpicp_icp_number']) ? sanitize_text_field($_POST['wpicp_icp_number']) : '';
            $domain = isset($_POST['wpicp_domain']) ? sanitize_text_field($_POST['wpicp_domain']) : '';
            $approval_date = isset($_POST['wpicp_approval_date']) ? sanitize_text_field($_POST['wpicp_approval_date']) : '';

            if(!empty($name) && !empty($icp_number)) {
                $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;

                if($record_id > 0) {
                    $core->update_icp_record($record_id, $name, $icp_number, $domain, $approval_date);
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('ICP备案记录已更新！', 'wpicp') . '</p></div>';
                } else {
                    $core->add_icp_record($name, $icp_number, $domain, $approval_date);
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('ICP备案记录已添加！', 'wpicp') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('名称和备案号为必填项！', 'wpicp') . '</p></div>';
            }
        }

        // 处理删除请求
        if(isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['record']) && check_admin_referer('wpicp_delete_record')) {
            $record_id = intval($_GET['record']);
            $core->delete_icp_record($record_id);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('ICP备案记录已删除！', 'wpicp') . '</p></div>';
        }

        // 获取编辑的记录
        $edit_record = null;
        if(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['record'])) {
            $record_id = intval($_GET['record']);
            $edit_record = $core->get_icp_record($record_id);
        }

        // 获取所有记录
        $records = $core->get_all_icp_records();

        // 获取历史记录
        $history_record_id = isset($_GET['action']) && $_GET['action'] == 'history' && isset($_GET['record']) ? intval($_GET['record']) : 0;
        $history_records = $history_record_id ? $core->get_record_history($history_record_id) : array();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if($history_record_id && !empty($history_records)): ?>
                <div class="wpicp-admin-history">
                    <h2><?php _e('备案记录历史', 'wpicp'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpicp-management')); ?>" class="button"><?php _e('返回', 'wpicp'); ?></a>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('操作时间', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('操作类型', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('操作人', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('详情', 'wpicp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history_records as $history): ?>
                                <tr>
                                    <td><?php echo esc_html($history->created_at); ?></td>
                                    <td>
                                        <?php
                                        switch($history->action) {
                                            case 'create':
                                                _e('创建', 'wpicp');
                                                break;
                                            case 'update':
                                                _e('更新', 'wpicp');
                                                break;
                                            case 'delete':
                                                _e('删除', 'wpicp');
                                                break;
                                            default:
                                                echo esc_html($history->action);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($history->user_name); ?></td>
                                    <td>
                                        <?php
                                        if($history->action == 'create') {
                                            echo sprintf(__('创建备案记录：%s (%s)', 'wpicp'),
                                                esc_html($history->data['name']),
                                                esc_html($history->data['icp_number'])
                                            );
                                        } elseif($history->action == 'update') {
                                            echo __('更新备案记录，变更前：', 'wpicp') . '<br>';
                                            echo sprintf(__('名称：%s，备案号：%s', 'wpicp'),
                                                esc_html($history->data['old']['name']),
                                                esc_html($history->data['old']['icp_number'])
                                            ) . '<br>';
                                            echo __('变更后：', 'wpicp') . '<br>';
                                            echo sprintf(__('名称：%s，备案号：%s', 'wpicp'),
                                                esc_html($history->data['new']['name']),
                                                esc_html($history->data['new']['icp_number'])
                                            );
                                        } elseif($history->action == 'delete') {
                                            echo sprintf(__('删除备案记录：%s (%s)', 'wpicp'),
                                                esc_html($history->data['name']),
                                                esc_html($history->data['icp_number'])
                                            );
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>

                <div class="wpicp-admin-form">
                    <h2><?php echo $edit_record ? __('编辑ICP备案记录', 'wpicp') : __('添加ICP备案记录', 'wpicp'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('wpicp_save_record', 'wpicp_nonce'); ?>
                        <?php if($edit_record): ?>
                            <input type="hidden" name="record_id" value="<?php echo esc_attr($edit_record->id); ?>" />
                        <?php endif; ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('名称', 'wpicp'); ?></th>
                                <td>
                                    <input type="text" name="wpicp_name" value="<?php echo $edit_record ? esc_attr($edit_record->name) : ''; ?>" class="regular-text" required />
                                    <p class="description"><?php _e('网站所有者名称（个人或企业）', 'wpicp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('ICP备案号', 'wpicp'); ?></th>
                                <td>
                                    <input type="text" name="wpicp_icp_number" value="<?php echo $edit_record ? esc_attr($edit_record->icp_number) : ''; ?>" class="regular-text" required />
                                    <p class="description"><?php _e('完整的ICP备案号，例如：京ICP备12345678号-1', 'wpicp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('域名', 'wpicp'); ?></th>
                                <td>
                                    <input type="text" name="wpicp_domain" value="<?php echo $edit_record ? esc_attr($edit_record->domain) : ''; ?>" class="regular-text" />
                                    <p class="description"><?php _e('备案关联的域名', 'wpicp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('批准日期', 'wpicp'); ?></th>
                                <td>
                                    <input type="date" name="wpicp_approval_date" value="<?php echo $edit_record ? esc_attr($edit_record->approval_date) : ''; ?>" class="regular-text" />
                                    <p class="description"><?php _e('ICP备案批准日期', 'wpicp'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button($edit_record ? __('更新记录', 'wpicp') : __('添加记录', 'wpicp'), 'primary', 'wpicp_save_record'); ?>
                        <?php if($edit_record): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpicp-management')); ?>" class="button"><?php _e('取消', 'wpicp'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="wpicp-admin-records">
                    <h2><?php _e('ICP备案记录列表', 'wpicp'); ?></h2>

                    <?php if(empty($records)): ?>
                        <p><?php _e('暂无ICP备案记录。', 'wpicp'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col"><?php _e('ID', 'wpicp'); ?></th>
                                    <th scope="col"><?php _e('名称', 'wpicp'); ?></th>
                                    <th scope="col"><?php _e('备案号', 'wpicp'); ?></th>
                                    <th scope="col"><?php _e('域名', 'wpicp'); ?></th>
                                    <th scope="col"><?php _e('批准日期', 'wpicp'); ?></th>
                                    <th scope="col"><?php _e('操作', 'wpicp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($records as $record): ?>
                                    <tr>
                                        <td><?php echo esc_html($record->id); ?></td>
                                        <td><?php echo esc_html($record->name); ?></td>
                                        <td><?php echo esc_html($record->icp_number); ?></td>
                                        <td><?php echo esc_html($record->domain); ?></td>
                                        <td><?php echo esc_html($record->approval_date); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpicp-management&action=edit&record=' . $record->id)); ?>" class="button button-small"><?php _e('编辑', 'wpicp'); ?></a>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpicp-management&action=history&record=' . $record->id)); ?>" class="button button-small"><?php _e('历史', 'wpicp'); ?></a>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpicp-management&action=delete&record=' . $record->id), 'wpicp_delete_record'); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('确定要删除这条记录吗？', 'wpicp'); ?>');"><?php _e('删除', 'wpicp'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="wpicp-export-tools">
                            <h3><?php _e('导出工具', 'wpicp'); ?></h3>
                            <p><?php _e('导出ICP备案记录为CSV格式：', 'wpicp'); ?></p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpicp-management&action=export'), 'wpicp_export_records'); ?>" class="button"><?php _e('导出为CSV', 'wpicp'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        // 处理导出请求
        if(isset($_GET['action']) && $_GET['action'] == 'export' && check_admin_referer('wpicp_export_records')) {
            $core->export_icp_records();
            exit;
        }
    }

    /**
     * 新增 - 显示实名认证页面
     */
    public function display_verification_page() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 获取实名认证信息（如果有）
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $real_name_info = $core->get_real_name_info_by_domain($domain);

        // 获取编辑的记录
        $edit_record = null;
        if(isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            $record_id = intval($_GET['id']);
            $edit_record = $core->get_real_name_info($record_id);
        }

        // 获取所有实名认证记录
        $all_records = $core->get_all_real_name_info();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wpicp-admin-info">
                <p><?php _e('实名认证是备案过程中的重要环节，请填写真实、准确的信息。', 'wpicp'); ?></p>
                <p><?php _e('注意：证件号码等敏感信息将被加密存储，保护您的隐私。', 'wpicp'); ?></p>
            </div>

            <div class="wpicp-admin-form">
                <h2><?php echo $edit_record ? __('编辑实名认证信息', 'wpicp') : __('提交实名认证信息', 'wpicp'); ?></h2>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpicp_save_real_name" />
                    <?php wp_nonce_field('wpicp_save_real_name', 'wpicp_nonce'); ?>
                    <?php if($edit_record): ?>
                        <input type="hidden" name="record_id" value="<?php echo esc_attr($edit_record->id); ?>" />
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('主体类型', 'wpicp'); ?></th>
                            <td>
                                <select id="entity_type" name="entity_type" required>
                                    <option value="personal" <?php echo $edit_record && $edit_record->entity_type == 'personal' ? 'selected' : ''; ?>><?php _e('个人', 'wpicp'); ?></option>
                                    <option value="enterprise" <?php echo $edit_record && $edit_record->entity_type == 'enterprise' ? 'selected' : ''; ?>><?php _e('企业', 'wpicp'); ?></option>
                                    <option value="organization" <?php echo $edit_record && $edit_record->entity_type == 'organization' ? 'selected' : ''; ?>><?php _e('政府/事业单位/组织', 'wpicp'); ?></option>
                                </select>
                                <p class="description"><?php _e('选择备案主体类型', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('主体名称', 'wpicp'); ?></th>
                            <td>
                                <input type="text" id="entity_name" name="entity_name" value="<?php echo $edit_record ? esc_attr($edit_record->entity_name) : ''; ?>" class="regular-text" required />
                                <p class="description"><?php _e('个人填写姓名，企业填写企业全称', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('证件类型', 'wpicp'); ?></th>
                            <td>
                                <select id="id_type" name="id_type" required>
                                    <option value="id_card" <?php echo $edit_record && $edit_record->id_type == 'id_card' ? 'selected' : ''; ?>><?php _e('居民身份证', 'wpicp'); ?></option>
                                    <option value="business_license" <?php echo $edit_record && $edit_record->id_type == 'business_license' ? 'selected' : ''; ?>><?php _e('营业执照', 'wpicp'); ?></option>
                                    <option value="organization_code" <?php echo $edit_record && $edit_record->id_type == 'organization_code' ? 'selected' : ''; ?>><?php _e('组织机构代码证', 'wpicp'); ?></option>
                                    <option value="passport" <?php echo $edit_record && $edit_record->id_type == 'passport' ? 'selected' : ''; ?>><?php _e('护照', 'wpicp'); ?></option>
                                </select>
                                <p class="description"><?php _e('选择证件类型', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('证件号码', 'wpicp'); ?></th>
                            <td>
                                <input type="text" id="id_number" name="id_number" value="" class="regular-text" <?php echo !$edit_record ? 'required' : ''; ?> />
                                <?php if($edit_record): ?>
                                    <p class="description"><?php _e('如果不修改证件号码，请留空', 'wpicp'); ?></p>
                                <?php else: ?>
                                    <p class="description"><?php _e('请输入证件号码，将会被加密存储', 'wpicp'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('联系电话', 'wpicp'); ?></th>
                            <td>
                                <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo $edit_record ? esc_attr($edit_record->contact_phone) : ''; ?>" class="regular-text" required />
                                <p class="description"><?php _e('请输入可以接收验证码的手机号', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('电子邮箱', 'wpicp'); ?></th>
                            <td>
                                <input type="email" id="contact_email" name="contact_email" value="<?php echo $edit_record ? esc_attr($edit_record->contact_email) : ''; ?>" class="regular-text" required />
                                <p class="description"><?php _e('请输入常用电子邮箱', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('网站域名', 'wpicp'); ?></th>
                            <td>
                                <input type="text" id="domain" name="domain" value="<?php echo $edit_record ? esc_attr($edit_record->domain) : esc_attr($domain); ?>" class="regular-text" required />
                                <p class="description"><?php _e('请输入需要备案的域名', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('服务器所在地', 'wpicp'); ?></th>
                            <td>
                                <select id="server_location" name="server_location" required>
                                    <option value="" disabled <?php echo !$edit_record || empty($edit_record->server_location) ? 'selected' : ''; ?>><?php _e('请选择', 'wpicp'); ?></option>
                                    <option value="beijing" <?php echo $edit_record && $edit_record->server_location == 'beijing' ? 'selected' : ''; ?>><?php _e('北京', 'wpicp'); ?></option>
                                    <option value="shanghai" <?php echo $edit_record && $edit_record->server_location == 'shanghai' ? 'selected' : ''; ?>><?php _e('上海', 'wpicp'); ?></option>
                                    <option value="guangdong" <?php echo $edit_record && $edit_record->server_location == 'guangdong' ? 'selected' : ''; ?>><?php _e('广东', 'wpicp'); ?></option>
                                    <option value="zhejiang" <?php echo $edit_record && $edit_record->server_location == 'zhejiang' ? 'selected' : ''; ?>><?php _e('浙江', 'wpicp'); ?></option>
                                    <option value="jiangsu" <?php echo $edit_record && $edit_record->server_location == 'jiangsu' ? 'selected' : ''; ?>><?php _e('江苏', 'wpicp'); ?></option>
                                    <option value="other" <?php echo $edit_record && $edit_record->server_location == 'other' ? 'selected' : ''; ?>><?php _e('其他', 'wpicp'); ?></option>
                                </select>
                                <p class="description"><?php _e('选择服务器物理所在地', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('验证方式', 'wpicp'); ?></th>
                            <td>
                                <select id="verification_method" name="verification_method">
                                    <option value="manual" <?php echo $edit_record && $edit_record->verification_method == 'manual' ? 'selected' : ''; ?>><?php _e('上传证件照片（人工审核）', 'wpicp'); ?></option>
                                    <option value="api" <?php echo $edit_record && $edit_record->verification_method == 'api' ? 'selected' : ''; ?>><?php _e('在线API验证', 'wpicp'); ?></option>
                                </select>
                                <p class="description"><?php _e('选择实名认证验证方式', 'wpicp'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button($edit_record ? __('更新信息', 'wpicp') : __('保存信息', 'wpicp')); ?>
                    <?php if($edit_record): ?>
                        <a href="<?php echo admin_url('admin.php?page=wpicp-verification'); ?>" class="button"><?php _e('取消', 'wpicp'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if($edit_record): ?>
                <div class="wpicp-admin-form">
                    <h2><?php _e('上传验证材料', 'wpicp'); ?></h2>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="wpicp_upload_verification" />
                        <?php wp_nonce_field('wpicp_upload_verification', 'wpicp_nonce'); ?>
                        <input type="hidden" name="record_id" value="<?php echo esc_attr($edit_record->id); ?>" />

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('证件照片', 'wpicp'); ?></th>
                                <td>
                                    <input type="file" id="verification_proof" name="verification_proof" accept="image/*" />
                                    <p class="description">
                                        <?php
                                        if($edit_record->entity_type == 'personal') {
                                            _e('请上传身份证正反面照片合并成一张图片', 'wpicp');
                                        } elseif($edit_record->entity_type == 'enterprise') {
                                            _e('请上传营业执照副本照片', 'wpicp');
                                        } else {
                                            _e('请上传组织机构代码证照片', 'wpicp');
                                        }
                                        ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('上传证件', 'wpicp')); ?>
                    </form>
                </div>
            <?php endif; ?>

            <?php if($edit_record && $edit_record->verification_method == 'api'): ?>
                <div class="wpicp-admin-form">
                    <h2><?php _e('API验证', 'wpicp'); ?></h2>

                    <p><?php _e('使用第三方API进行实名验证。点击下方按钮开始验证流程。', 'wpicp'); ?></p>

                    <?php
                    $options = get_option('wpicp_options');
                    $api_provider = isset($options['verification_api_provider']) ? $options['verification_api_provider'] : '';
                    $api_key = isset($options['verification_api_key']) ? $options['verification_api_key'] : '';

                    if(empty($api_provider) || empty($api_key)):
                    ?>
                        <div class="notice notice-error">
                            <p><?php _e('API验证未配置。请先在设置页面配置API提供商和密钥。', 'wpicp'); ?></p>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <?php wp_nonce_field('wpicp_api_verify', 'wpicp_nonce'); ?>
                            <input type="hidden" name="record_id" value="<?php echo esc_attr($edit_record->id); ?>" />
                            <input type="hidden" name="verify_action" value="api_verify" />
                            <p>
                                <button type="submit" class="button button-primary"><?php _e('开始API验证', 'wpicp'); ?></button>
                            </p>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="wpicp-admin-records">
                <h2><?php _e('实名认证信息列表', 'wpicp'); ?></h2>

                <?php if(empty($all_records)): ?>
                    <p><?php _e('暂无实名认证信息记录。', 'wpicp'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('ID', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('主体类型', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('名称', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('域名', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('验证状态', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('验证方法', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('验证时间', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('操作', 'wpicp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($all_records as $record): ?>
                                <tr>
                                    <td><?php echo esc_html($record->id); ?></td>
                                    <td>
                                        <?php
                                        switch($record->entity_type) {
                                            case 'personal':
                                                _e('个人', 'wpicp');
                                                break;
                                            case 'enterprise':
                                                _e('企业', 'wpicp');
                                                break;
                                            case 'organization':
                                                _e('组织/机构', 'wpicp');
                                                break;
                                            default:
                                                echo esc_html($record->entity_type);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($record->entity_name); ?></td>
                                    <td><?php echo esc_html($record->domain); ?></td>
                                    <td>
                                        <?php
                                        switch($record->verification_status) {
                                            case 'pending':
                                                echo '<span class="wpicp-status wpicp-status-pending">' . __('待验证', 'wpicp') . '</span>';
                                                break;
                                            case 'verified':
                                                echo '<span class="wpicp-status wpicp-status-success">' . __('已验证', 'wpicp') . '</span>';
                                                break;
                                            case 'rejected':
                                                echo '<span class="wpicp-status wpicp-status-error">' . __('验证失败', 'wpicp') . '</span>';
                                                break;
                                            default:
                                                echo esc_html($record->verification_status);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        switch($record->verification_method) {
                                            case 'manual':
                                                _e('人工审核', 'wpicp');
                                                break;
                                            case 'api':
                                                _e('API验证', 'wpicp');
                                                break;
                                            default:
                                                echo esc_html($record->verification_method);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo !empty($record->verification_date) ? esc_html($record->verification_date) : '-'; ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpicp-verification&action=edit&id=' . $record->id)); ?>" class="button button-small"><?php _e('编辑', 'wpicp'); ?></a>
                                        <?php if($record->verification_status != 'verified'): ?>
                                            <form method="post" style="display:inline-block">
                                                <?php wp_nonce_field('wpicp_approve_verification', 'wpicp_nonce'); ?>
                                                <input type="hidden" name="record_id" value="<?php echo esc_attr($record->id); ?>" />
                                                <input type="hidden" name="verify_action" value="approve" />
                                                <button type="submit" class="button button-small"><?php _e('通过验证', 'wpicp'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpicp-verification&action=delete&id=' . $record->id), 'wpicp_delete_verification'); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('确定要删除这条记录吗？', 'wpicp'); ?>');"><?php _e('删除', 'wpicp'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
        </div>
        <?php

        // 处理实名验证审批
        if(isset($_POST['verify_action']) && $_POST['verify_action'] == 'approve' && check_admin_referer('wpicp_approve_verification', 'wpicp_nonce')) {
            $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
            if($record_id > 0) {
                $core->verify_real_name_info($record_id, 'manual', 'verified', '');
                echo '<div class="notice notice-success is-dismissible"><p>' . __('验证已通过！', 'wpicp') . '</p></div>';
                echo '<script>location.reload();</script>';
            }
        }

        // 处理API验证
        if(isset($_POST['verify_action']) && $_POST['verify_action'] == 'api_verify' && check_admin_referer('wpicp_api_verify', 'wpicp_nonce')) {
            $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
            if($record_id > 0) {
                $record = $core->get_real_name_info($record_id);

                if($record) {
                    // 执行API验证
                    $verify_result = $core->verify_identity_with_api(
                        $record->entity_name,
                        $record->id_type,
                        $record->id_number
                    );

                    if($verify_result['success']) {
                        $status = isset($verify_result['data']['verified']) && $verify_result['data']['verified'] ? 'verified' : 'rejected';
                        $core->verify_real_name_info($record_id, 'api', $status, json_encode($verify_result));

                        if($status == 'verified') {
                            echo '<div class="notice notice-success is-dismissible"><p>' . __('API验证成功！', 'wpicp') . '</p></div>';
                        } else {
                            echo '<div class="notice notice-error is-dismissible"><p>' . __('API验证未通过：', 'wpicp') . esc_html($verify_result['message']) . '</p></div>';
                        }

                        echo '<script>location.reload();</script>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('API调用失败：', 'wpicp') . esc_html($verify_result['message']) . '</p></div>';
                    }
                }
            }
        }

        // 处理删除请求
        if(isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && check_admin_referer('wpicp_delete_verification')) {
            $record_id = intval($_GET['id']);
            $core->delete_real_name_info($record_id);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('实名认证记录已删除！', 'wpicp') . '</p></div>';
            echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=wpicp-verification')) . '";</script>';
        }
    }

    /**
     * 新增 - 显示备案状态页面
     */
    public function display_status_page() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 处理手动设置备案状态
        if(isset($_POST['wpicp_set_status']) && check_admin_referer('wpicp_set_status', 'wpicp_nonce')) {
            $domain = sanitize_text_field($_POST['domain']);
            $icp_status = sanitize_text_field($_POST['icp_status']);
            $police_status = sanitize_text_field($_POST['police_status']);
            $icp_number = sanitize_text_field($_POST['icp_number']);
            $police_number = sanitize_text_field($_POST['police_number']);

            $core->set_manual_status($domain, $icp_status, $police_status, $icp_number, $police_number);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('备案状态已更新！', 'wpicp') . '</p></div>';
        }

        // 当前站点域名
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);

        // 获取状态信息
        $status = $core->check_domain_icp_status($current_domain);
        $status_data = $status ? maybe_unserialize($status->check_result) : array();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wpicp-admin-info">
                <p><?php _e('此页面显示网站的ICP备案和公安备案状态。', 'wpicp'); ?></p>
                <p><?php _e('根据中国法律法规，网站必须完成ICP备案和公安备案才能在中国境内合法运营。', 'wpicp'); ?></p>
            </div>

            <?php $this->display_status_summary(); ?>

            <div class="wpicp-admin-form">
                <h2><?php _e('手动设置备案状态', 'wpicp'); ?></h2>
                <p><?php _e('如果自动检测的备案状态不准确，您可以手动设置。', 'wpicp'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('wpicp_set_status', 'wpicp_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('域名', 'wpicp'); ?></th>
                            <td>
                                <input type="text" name="domain" value="<?php echo esc_attr($current_domain); ?>" class="regular-text" required />
                                <p class="description"><?php _e('需要设置备案状态的域名', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('ICP备案状态', 'wpicp'); ?></th>
                            <td>
                                <select name="icp_status">
                                    <option value="registered" <?php selected($status && $status->icp_status == 'registered'); ?>><?php _e('已备案', 'wpicp'); ?></option>
                                    <option value="not_registered" <?php selected($status && $status->icp_status == 'not_registered'); ?>><?php _e('未备案', 'wpicp'); ?></option>
                                    <option value="processing" <?php selected($status && $status->icp_status == 'processing'); ?>><?php _e('备案中', 'wpicp'); ?></option>
                                    <option value="unknown" <?php selected(!$status || $status->icp_status == 'unknown'); ?>><?php _e('未知', 'wpicp'); ?></option>
                                </select>
                                <p class="description"><?php _e('设置ICP备案状态', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('ICP备案号', 'wpicp'); ?></th>
                            <td>
                                <input type="text" name="icp_number" value="<?php echo isset($status_data['icp_number']) ? esc_attr($status_data['icp_number']) : ''; ?>" class="regular-text" />
                                <p class="description"><?php _e('如已备案，请填写ICP备案号', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('公安备案状态', 'wpicp'); ?></th>
                            <td>
                                <select name="police_status">
                                    <option value="registered" <?php selected($status && $status->police_status == 'registered'); ?>><?php _e('已备案', 'wpicp'); ?></option>
                                    <option value="not_registered" <?php selected($status && $status->police_status == 'not_registered'); ?>><?php _e('未备案', 'wpicp'); ?></option>
                                    <option value="processing" <?php selected($status && $status->police_status == 'processing'); ?>><?php _e('备案中', 'wpicp'); ?></option>
                                    <option value="unknown" <?php selected(!$status || $status->police_status == 'unknown'); ?>><?php _e('未知', 'wpicp'); ?></option>
                                </select>
                                <p class="description"><?php _e('设置公安备案状态', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('公安备案号', 'wpicp'); ?></th>
                            <td>
                                <input type="text" name="police_number" value="<?php echo isset($status_data['police_number']) ? esc_attr($status_data['police_number']) : ''; ?>" class="regular-text" />
                                <p class="description"><?php _e('如已备案，请填写公安备案号', 'wpicp'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('保存备案状态', 'wpicp'), 'primary', 'wpicp_set_status'); ?>
                </form>
            </div>

            <div class="wpicp-admin-info">
                <h2><?php _e('备案状态检查历史', 'wpicp'); ?></h2>
                <?php if($status): ?>
                    <p><?php printf(__('最后检查时间: %s', 'wpicp'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($status->last_check))); ?></p>
                <?php else: ?>
                    <p><?php _e('尚未进行备案状态检查。', 'wpicp'); ?></p>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="check_status_now" value="1" />
                    <?php wp_nonce_field('wpicp_check_status_now', 'wpicp_nonce'); ?>
                    <p><button type="submit" class="button"><?php _e('立即检查状态', 'wpicp'); ?></button></p>
                </form>
            </div>
        </div>
        <?php

        // 处理立即检查请求
        if(isset($_POST['check_status_now']) && check_admin_referer('wpicp_check_status_now', 'wpicp_nonce')) {
            // 清除缓存，强制重新检查
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpicp_status';
            $wpdb->delete($table_name, array('domain' => $current_domain));

            // 重新检查
            $core->check_domain_icp_status($current_domain);

            echo '<div class="notice notice-success is-dismissible"><p>' . __('备案状态已重新检查！', 'wpicp') . '</p></div>';
            echo '<script>location.reload();</script>';
        }
    }

    /**
     * 显示合规检查页面
     */
    public function display_compliance_check_page() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        $options = get_option('wpicp_options');
        $enable_check = isset($options['enable_sensitive_check']) && $options['enable_sensitive_check'] == 'yes';

        // 处理开始检查请求
        $check_results = null;
        if(isset($_POST['wpicp_start_check']) && check_admin_referer('wpicp_compliance_check', 'wpicp_nonce')) {
            $check_results = $core->check_site_compliance();
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if(!$enable_check): ?>
                <div class="notice notice-warning">
                    <p><?php _e('合规检查功能当前未启用。请在设置页面启用此功能。', 'wpicp'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=wpicp'); ?>" class="button button-primary"><?php _e('前往设置', 'wpicp'); ?></a></p>
                </div>
            <?php else: ?>
                <p><?php _e('此功能可以帮助检查您的网站内容是否符合中国互联网法律法规的要求。', 'wpicp'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('wpicp_compliance_check', 'wpicp_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('检查范围', 'wpicp'); ?></th>
                            <td>
                                <label><input type="checkbox" name="check_posts" value="1" checked /> <?php _e('文章内容', 'wpicp'); ?></label><br>
                                <label><input type="checkbox" name="check_pages" value="1" checked /> <?php _e('页面内容', 'wpicp'); ?></label><br>
                                <label><input type="checkbox" name="check_comments" value="1" checked /> <?php _e('评论内容', 'wpicp'); ?></label><br>
                                <label><input type="checkbox" name="check_links" value="1" checked /> <?php _e('外部链接', 'wpicp'); ?></label><br>
                                <label><input type="checkbox" name="check_beian_info" value="1" checked /> <?php _e('备案信息', 'wpicp'); ?></label>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('开始检查', 'wpicp'), 'primary', 'wpicp_start_check'); ?>
                </form>

                <?php if($check_results): ?>
                    <div class="wpicp-check-results">
                        <h2><?php _e('检查结果', 'wpicp'); ?></h2>

                        <?php if(empty($check_results['issues'])): ?>
                            <div class="notice notice-success">
                                <p><?php _e('恭喜！未发现任何合规问题。', 'wpicp'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="notice notice-error">
                                <p><?php printf(_n('发现 %d 个潜在的合规问题。', '发现 %d 个潜在的合规问题。', count($check_results['issues']), 'wpicp'), count($check_results['issues'])); ?></p>
                            </div>

                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php _e('问题类型', 'wpicp'); ?></th>
                                        <th scope="col"><?php _e('位置', 'wpicp'); ?></th>
                                        <th scope="col"><?php _e('详情', 'wpicp'); ?></th>
                                        <th scope="col"><?php _e('建议', 'wpicp'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($check_results['issues'] as $issue): ?>
                                        <tr>
                                            <td><?php echo esc_html($issue['type']); ?></td>
                                            <td><?php echo esc_html($issue['location']); ?></td>
                                            <td><?php echo esc_html($issue['details']); ?></td>
                                            <td><?php echo esc_html($issue['suggestion']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <div class="wpicp-check-summary">
                            <h3><?php _e('检查摘要', 'wpicp'); ?></h3>
                            <p><?php _e('检查完成时间：', 'wpicp'); ?> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></p>
                            <p><?php _e('检查项目数量：', 'wpicp'); ?> <?php echo esc_html($check_results['total_checked']); ?></p>
                            <p><?php _e('发现问题数量：', 'wpicp'); ?> <?php echo esc_html(count($check_results['issues'])); ?></p>
                        </div>

                        <div class="wpicp-check-export">
                            <h3><?php _e('导出检查结果', 'wpicp'); ?></h3>
                            <form method="post" action="">
                                <input type="hidden" name="export_check_results" value="1" />
                                <input type="hidden" name="results_data" value="<?php echo esc_attr(json_encode($check_results)); ?>" />
                                <?php wp_nonce_field('wpicp_export_check_results', 'wpicp_nonce'); ?>
                                <button type="submit" class="button"><?php _e('导出为CSV', 'wpicp'); ?></button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php

        // 处理导出检查结果
        if(isset($_POST['export_check_results']) && check_admin_referer('wpicp_export_check_results', 'wpicp_nonce')) {
            $results_data = json_decode(stripslashes($_POST['results_data']), true);

            if(!$results_data) {
                return;
            }

            $filename = 'wpicp_compliance_check_' . date('Y-m-d') . '.csv';

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $output = fopen('php://output', 'w');

            // 输出CSV标头
            fputcsv($output, array('问题类型', '位置', '详情', '建议'));

            // 输出数据行
            foreach($results_data['issues'] as $issue) {
                fputcsv($output, array(
                    $issue['type'],
                    $issue['location'],
                    $issue['details'],
                    $issue['suggestion']
                ));
            }

            fclose($output);
            exit;
        }
    }

    /**
     * 新增 - 显示敏感词管理页面
     */
    public function display_sensitive_words_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_sensitive_words';

        // 处理表单提交
        if(isset($_POST['wpicp_add_word']) && check_admin_referer('wpicp_manage_words', 'wpicp_nonce')) {
            $word = sanitize_text_field($_POST['word']);
            $category = sanitize_text_field($_POST['category']);
            $level = intval($_POST['level']);

            if(!empty($word)) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE word = %s",
                    $word
                ));

                if($exists) {
                    $wpdb->update(
                        $table_name,
                        array(
                            'category' => $category,
                            'level' => $level
                        ),
                        array('word' => $word)
                    );
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('敏感词已更新！', 'wpicp') . '</p></div>';
                } else {
                    $wpdb->insert(
                        $table_name,
                        array(
                            'word' => $word,
                            'category' => $category,
                            'level' => $level
                        )
                    );
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('敏感词已添加！', 'wpicp') . '</p></div>';
                }

                // 清除缓存
                delete_transient('wpicp_sensitive_words');
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('敏感词不能为空！', 'wpicp') . '</p></div>';
            }
        }

        // 处理删除请求
        if(isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && check_admin_referer('wpicp_delete_word')) {
            $word_id = intval($_GET['id']);
            $wpdb->delete($table_name, array('id' => $word_id));
            delete_transient('wpicp_sensitive_words');
            echo '<div class="notice notice-success is-dismissible"><p>' . __('敏感词已删除！', 'wpicp') . '</p></div>';
        }

        // 获取敏感词列表
        $words = $wpdb->get_results("SELECT * FROM $table_name ORDER BY category, level DESC, word", ARRAY_A);

        // 获取词语类别
        $categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_name ORDER BY category");
        if(!in_array('general', $categories)) {
            $categories[] = 'general';
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wpicp-admin-info">
                <p><?php _e('在此页面管理网站内容检查使用的敏感词库。', 'wpicp'); ?></p>
                <p><?php _e('敏感词越多，检查越全面，但可能影响检查性能。', 'wpicp'); ?></p>
            </div>

            <div class="wpicp-admin-form">
                <h2><?php _e('添加敏感词', 'wpicp'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('wpicp_manage_words', 'wpicp_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('敏感词', 'wpicp'); ?></th>
                            <td>
                                <input type="text" name="word" class="regular-text" required />
                                <p class="description"><?php _e('输入需要检查的敏感词', 'wpicp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('类别', 'wpicp'); ?></th>
                            <td>
                                <select name="category">
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                                    <?php endforeach; ?>
                                    <option value="custom"><?php _e('自定义...', 'wpicp'); ?></option>
                                </select>
                                <input type="text" id="custom_category" placeholder="<?php _e('自定义类别', 'wpicp'); ?>" style="display:none;" />
                                <p class="description"><?php _e('选择敏感词类别', 'wpicp'); ?></p>
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var categorySelect = document.querySelector('select[name="category"]');
                                    var customInput = document.getElementById('custom_category');

                                    categorySelect.addEventListener('change', function() {
                                        if(this.value === 'custom') {
                                            customInput.style.display = 'inline-block';
                                            customInput.focus();
                                        } else {
                                            customInput.style.display = 'none';
                                        }
                                    });

                                    customInput.addEventListener('change', function() {
                                        if(this.value.trim() !== '') {
                                            var option = new Option(this.value, this.value);
                                            option.selected = true;
                                            categorySelect.add(option);
                                            customInput.style.display = 'none';
                                        }
                                    });
                                });
                                </script>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('级别', 'wpicp'); ?></th>
                            <td>
                                <select name="level">
                                    <option value="1"><?php _e('低（仅提示）', 'wpicp'); ?></option>
                                    <option value="2"><?php _e('中（警告）', 'wpicp'); ?></option>
                                    <option value="3" selected><?php _e('高（阻止）', 'wpicp'); ?></option>
                                </select>
                                <p class="description"><?php _e('选择敏感词检查级别', 'wpicp'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('添加敏感词', 'wpicp'), 'primary', 'wpicp_add_word'); ?>
                </form>
            </div>

            <div class="wpicp-admin-tools">
                <h2><?php _e('批量管理', 'wpicp'); ?></h2>

                <div class="wpicp-tool-box">
                    <h3><?php _e('导入敏感词', 'wpicp'); ?></h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="wpicp_import_sensitive_words" />
                        <?php wp_nonce_field('wpicp_import_sensitive_words', 'wpicp_nonce'); ?>
                        <p>
                            <input type="file" name="words_file" accept=".csv,.txt" required />
                            <p class="description"><?php _e('上传CSV文件（格式：敏感词,类别,级别）', 'wpicp'); ?></p>
                        </p>
                        <?php submit_button(__('导入', 'wpicp'), 'secondary', 'submit', false); ?>
                    </form>
                </div>

                <div class="wpicp-tool-box">
                    <h3><?php _e('导出敏感词', 'wpicp'); ?></h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="wpicp_export_sensitive_words" />
                        <?php wp_nonce_field('wpicp_export_sensitive_words', 'wpicp_nonce'); ?>
                        <?php submit_button(__('导出全部', 'wpicp'), 'secondary', 'submit', false); ?>
                    </form>
                </div>
            </div>

            <div class="wpicp-admin-records">
                <h2><?php _e('敏感词列表', 'wpicp'); ?></h2>

                <?php if(empty($words)): ?>
                    <p><?php _e('暂无敏感词。', 'wpicp'); ?></p>
                <?php else: ?>
                    <p><?php printf(__('当前共有 %d 个敏感词。', 'wpicp'), count($words)); ?></p>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('ID', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('敏感词', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('类别', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('级别', 'wpicp'); ?></th>
                                <th scope="col"><?php _e('操作', 'wpicp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($words as $word): ?>
                                <tr>
                                    <td><?php echo esc_html($word['id']); ?></td>
                                    <td><?php echo esc_html($word['word']); ?></td>
                                    <td><?php echo esc_html($word['category']); ?></td>
                                    <td>
                                        <?php
                                        switch($word['level']) {
                                            case 1:
                                                echo '<span class="wpicp-level wpicp-level-low">' . __('低', 'wpicp') . '</span>';
                                                break;
                                            case 2:
                                                echo '<span class="wpicp-level wpicp-level-medium">' . __('中', 'wpicp') . '</span>';
                                                break;
                                            case 3:
                                                echo '<span class="wpicp-level wpicp-level-high">' . __('高', 'wpicp') . '</span>';
                                                break;
                                            default:
                                                echo esc_html($word['level']);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wpicp-sensitive&action=delete&id=' . $word['id']), 'wpicp_delete_word'); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('确定要删除这个敏感词吗？', 'wpicp'); ?>');"><?php _e('删除', 'wpicp'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 新增 - 显示备案指南页面
     */
    public function display_beian_guide_page() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 处理省份选择
        $province = isset($_GET['province']) ? sanitize_text_field($_GET['province']) : '';
        $guide_data = $core->get_beian_guide_data($province);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wpicp-admin-info">
                <p><?php _e('此页面提供ICP备案和公安备案的详细流程指引。', 'wpicp'); ?></p>
                <p><?php _e('请按照指引完成网站备案，确保网站合法运营。', 'wpicp'); ?></p>
            </div>

            <?php if(!empty($province) && isset($guide_data['province'])): ?>
                <div class="wpicp-guide-nav">
                    <p><a href="<?php echo admin_url('admin.php?page=wpicp-guide'); ?>" class="button"><?php _e('返回通用指南', 'wpicp'); ?></a></p>
                </div>

                <div class="wpicp-province-guide">
                    <h2><?php echo esc_html($guide_data['province']['title']); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('管理机构', 'wpicp'); ?></th>
                            <td><?php echo esc_html($guide_data['province']['authority']); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('办公地址', 'wpicp'); ?></th>
                            <td><?php echo esc_html($guide_data['province']['address']); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('联系电话', 'wpicp'); ?></th>
                            <td><?php echo esc_html($guide_data['province']['phone']); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('官方网站', 'wpicp'); ?></th>
                            <td><a href="<?php echo esc_url($guide_data['province']['website']); ?>" target="_blank"><?php echo esc_html($guide_data['province']['website']); ?></a></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('审核时间', 'wpicp'); ?></th>
                            <td><?php echo esc_html($guide_data['province']['processing_time']); ?></td>
                        </tr>
                    </table>

                    <h3><?php _e('地区特殊要求', 'wpicp'); ?></h3>
                    <ul>
                        <?php foreach($guide_data['province']['special_requirements'] as $req): ?>
                            <li><?php echo esc_html($req); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="wpicp-province-selection">
                    <h2><?php _e('选择省份查看具体备案指南', 'wpicp'); ?></h2>
                    <ul class="wpicp-province-list">
                        <?php foreach($guide_data['provinces'] as $prov): ?>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=wpicp-guide&province=' . $prov)); ?>" class="button"><?php echo esc_html($prov); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="wpicp-general-guide">
                <h2><?php echo esc_html($guide_data['general']['title']); ?></h2>
                <p><?php echo esc_html($guide_data['general']['description']); ?></p>

                <div class="wpicp-guide-steps">
                    <?php foreach($guide_data['general']['steps'] as $step): ?>
                        <div class="wpicp-guide-step">
                            <h3><?php echo esc_html($step['title']); ?></h3>
                            <div class="wpicp-step-content"><?php echo wp_kses_post($step['content']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="wpicp-guide-notes">
                    <h3><?php _e('重要提示', 'wpicp'); ?></h3>
                    <ul>
                        <?php foreach($guide_data['general']['important_notes'] as $note): ?>
                            <li><?php echo esc_html($note); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="wpicp-useful-links">
                    <h3><?php _e('常用链接', 'wpicp'); ?></h3>
                    <ul>
                        <?php foreach($guide_data['general']['useful_links'] as $link): ?>
                            <li><a href="<?php echo esc_url($link['url']); ?>" target="_blank"><?php echo esc_html($link['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 新增 - 显示常见问题页面
     */
    public function display_faq_page() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        $faqs = $core->get_faq_data();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wpicp-admin-info">
                <p><?php _e('此页面解答关于ICP备案和公安备案的常见问题。', 'wpicp'); ?></p>
            </div>

            <div class="wpicp-faq-list">
                <?php foreach($faqs as $index => $faq): ?>
                    <div class="wpicp-faq-item">
                        <h3 class="wpicp-faq-question"><?php echo esc_html($faq['question']); ?></h3>
                        <div class="wpicp-faq-answer"><?php echo wp_kses_post($faq['answer']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wpicp-more-help">
                <h2><?php _e('需要更多帮助？', 'wpicp'); ?></h2>
                <p><?php _e('如果您有其他问题或需要协助，可以：', 'wpicp'); ?></p>
                <ul>
                    <li><?php _e('查看详细的', 'wpicp'); ?> <a href="<?php echo admin_url('admin.php?page=wpicp-guide'); ?>"><?php _e('备案指南', 'wpicp'); ?></a></li>
                    <li><?php _e('咨询您的网站服务提供商', 'wpicp'); ?></li>
                    <li><?php _e('访问', 'wpicp'); ?> <a href="https://beian.miit.gov.cn/" target="_blank"><?php _e('工信部ICP备案管理系统', 'wpicp'); ?></a></li>
                    <li><?php _e('访问', 'wpicp'); ?> <a href="http://www.beian.gov.cn/" target="_blank"><?php _e('全国互联网安全管理服务平台', 'wpicp'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * 新增 - 显示备案状态摘要
     */
    private function display_status_summary() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $status_info = $core->get_status_message($domain);

        $status_class = '';
        switch($status_info['type']) {
            case 'success':
                $status_class = 'wpicp-status-summary-success';
                break;
            case 'error':
                $status_class = 'wpicp-status-summary-error';
                break;
            case 'warning':
            case 'info':
            default:
                $status_class = 'wpicp-status-summary-warning';
        }
        ?>
        <div class="wpicp-status-summary <?php echo $status_class; ?>">
            <h2><?php _e('备案状态摘要', 'wpicp'); ?></h2>
            <p class="wpicp-status-message"><?php echo esc_html($status_info['message']); ?></p>

            <table class="wpicp-status-details">
                <tr>
                    <th><?php _e('ICP备案状态', 'wpicp'); ?></th>
                    <td>
                        <?php
                        switch($status_info['icp_status']) {
                            case 'registered':
                                echo '<span class="wpicp-status wpicp-status-success">' . __('已备案', 'wpicp') . '</span>';
                                break;
                            case 'not_registered':
                                echo '<span class="wpicp-status wpicp-status-error">' . __('未备案', 'wpicp') . '</span>';
                                break;
                            default:
                                echo '<span class="wpicp-status wpicp-status-warning">' . __('未知', 'wpicp') . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if(isset($status_info['icp_number']) && !empty($status_info['icp_number'])): ?>
                            <?php echo esc_html($status_info['icp_number']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('公安备案状态', 'wpicp'); ?></th>
                    <td>
                        <?php
                        switch($status_info['police_status']) {
                            case 'registered':
                                echo '<span class="wpicp-status wpicp-status-success">' . __('已备案', 'wpicp') . '</span>';
                                break;
                            case 'not_registered':
                                echo '<span class="wpicp-status wpicp-status-error">' . __('未备案', 'wpicp') . '</span>';
                                break;
                            default:
                                echo '<span class="wpicp-status wpicp-status-warning">' . __('未知', 'wpicp') . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if(isset($status_info['police_number']) && !empty($status_info['police_number'])): ?>
                            <?php echo esc_html($status_info['police_number']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div class="wpicp-status-actions">
                <a href="<?php echo admin_url('admin.php?page=wpicp-status'); ?>" class="button"><?php _e('查看详细状态', 'wpicp'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=wpicp-guide'); ?>" class="button"><?php _e('备案指南', 'wpicp'); ?></a>
                <?php if($status_info['icp_status'] != 'registered' || $status_info['police_status'] != 'registered'): ?>
                    <a href="<?php echo admin_url('admin.php?page=wpicp-verification'); ?>" class="button button-primary"><?php _e('开始实名认证', 'wpicp'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 添加插件设置链接
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wpicp') . '">' . __('设置', 'wpicp') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * 显示管理员通知
     */
    public function display_admin_notices() {
        // 只在管理页面显示
        if(!is_admin()) {
            return;
        }

        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 获取当前域名
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $status_info = $core->get_status_message($domain);

        // 如果ICP或公安备案未完成，显示通知
        if($status_info['icp_status'] != 'registered' || $status_info['police_status'] != 'registered') {
            // 检查是否已经关闭通知
            $notice_dismissed = get_transient('wpicp_admin_notice_dismissed');

            if(!$notice_dismissed) {
                $notice_class = $status_info['icp_status'] != 'registered' ? 'notice-error' : 'notice-warning';
                ?>
                <div class="notice <?php echo $notice_class; ?> is-dismissible wpicp-admin-notice">
                    <p><strong><?php _e('ICP备案提醒：', 'wpicp'); ?></strong> <?php echo esc_html($status_info['message']); ?></p>
                    <p><?php _e('在中国境内提供服务的网站必须完成ICP备案和公安备案。', 'wpicp'); ?></p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=wpicp-guide'); ?>" class="button button-primary"><?php _e('查看备案指南', 'wpicp'); ?></a>
                        <a href="<?php echo admin_url('admin.php?page=wpicp-verification'); ?>" class="button"><?php _e('开始实名认证', 'wpicp'); ?></a>
                        <a href="#" class="button wpicp-dismiss-notice" data-days="7"><?php _e('暂不提醒（7天）', 'wpicp'); ?></a>
                    </p>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var dismissButton = document.querySelector('.wpicp-dismiss-notice');
                    if(dismissButton) {
                        dismissButton.addEventListener('click', function(e) {
                            e.preventDefault();
                            var days = this.dataset.days;

                            // AJAX请求忽略通知
                            var data = {
                                'action': 'wpicp_dismiss_notice',
                                'days': days,
                                'nonce': '<?php echo wp_create_nonce('wpicp_dismiss_notice'); ?>'
                            };

                            fetch(ajaxurl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams(data)
                            })
                            .then(response => response.json())
                            .then(data => {
                                if(data.success) {
                                    var notice = document.querySelector('.wpicp-admin-notice');
                                    notice.style.display = 'none';
                                }
                            });
                        });
                    }
                });
                </script>
                <?php
            }
        }
    }

    /**
     * 执行定时状态检查
     */
    public function schedule_status_check() {
        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 获取当前站点域名
        $domain = parse_url(get_site_url(), PHP_URL_HOST);

        // 检查备案状态
        $core->check_domain_icp_status($domain);

        // 如果启用了自动检查网站内容
        $options = get_option('wpicp_options');
        if(isset($options['enable_sensitive_check']) && $options['enable_sensitive_check'] == 'yes' &&
           isset($options['scheduled_check']) && $options['scheduled_check'] != 'no') {

            // 记录上次检查时间
            $last_check = get_option('wpicp_last_scheduled_check');
            $now = time();

            $check_interval = DAY_IN_SECONDS; // 默认一天

            switch($options['scheduled_check']) {
                case 'daily':
                    $check_interval = DAY_IN_SECONDS;
                    break;
                case 'weekly':
                    $check_interval = 7 * DAY_IN_SECONDS;
                    break;
                case 'monthly':
                    $check_interval = 30 * DAY_IN_SECONDS;
                    break;
            }

            // 如果到了检查时间
            if(!$last_check || ($now - $last_check) >= $check_interval) {
                // 构造POST数据
                $_POST['check_posts'] = 1;
                $_POST['check_pages'] = 1;
                $_POST['check_comments'] = 1;
                $_POST['check_links'] = 1;
                $_POST['check_beian_info'] = 1;

                // 执行合规检查
                $check_results = $core->check_site_compliance();

                // 保存检查结果
                update_option('wpicp_last_check_results', $check_results);
                update_option('wpicp_last_scheduled_check', $now);

                // 如果有问题，发送邮件通知
                if(!empty($check_results['issues'])) {
                    $this->send_compliance_notification($check_results);
                }
            }
        }
    }

    /**
     * 新增 - 发送合规检查通知邮件
     */
    private function send_compliance_notification($check_results) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $issues_count = count($check_results['issues']);

        $subject = sprintf(__('[%s] 网站合规性检查发现 %d 个问题', 'wpicp'), $site_name, $issues_count);

        $message = sprintf(__('网站"%s"的自动合规性检查发现了 %d 个潜在问题。', 'wpicp'), $site_name, $issues_count) . "\n\n";
        $message .= __('问题摘要：', 'wpicp') . "\n";

        foreach($check_results['issues'] as $index => $issue) {
            if($index < 10) { // 只显示前10个问题
                $message .= sprintf(
                    "%d. [%s] %s - %s\n",
                    $index + 1,
                    $issue['type'],
                    $issue['location'],
                    $issue['details']
                );
            }
        }

        if($issues_count > 10) {
            $message .= sprintf(__('... 和其他 %d 个问题。', 'wpicp'), $issues_count - 10) . "\n";
        }

        $message .= "\n" . __('请登录管理后台查看详细信息并采取措施解决这些问题。', 'wpicp') . "\n";
        $message .= admin_url('admin.php?page=wpicp-compliance') . "\n\n";
        $message .= __('此邮件由系统自动发送，请勿回复。', 'wpicp');

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * 检查发布的文章内容
     */
    public function check_post_content($post_id, $post, $update) {
        // 如果是自动保存或者不是发布状态，则跳过
        if(wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || $post->post_status != 'publish') {
            return;
        }

        $options = get_option('wpicp_options');

        // 检查是否启用了敏感内容检查和发布前检查
        if(isset($options['enable_sensitive_check']) && $options['enable_sensitive_check'] == 'yes' &&
           isset($options['check_on_publish']) && $options['check_on_publish'] != 'no') {

            require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
            $core = new WPICP_Core();

            // 检查标题和内容
            $content_issues = $core->check_content_for_sensitive_words($post->post_title . ' ' . $post->post_content);

            if(!empty($content_issues)) {
                // 创建警告消息
                $warning = __('警告：文章中包含敏感内容：', 'wpicp') . "\n";

                foreach($content_issues as $issue) {
                    $warning .= sprintf(
                        "- %s (%s，级别：%d)\n",
                        $issue['word'],
                        $issue['category'],
                        $issue['level']
                    );
                }

                // 如果设置为阻止发布
                if($options['check_on_publish'] == 'block') {
                    // 将文章状态改为草稿
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_status' => 'draft'
                    ));

                    // 添加错误消息
                    add_filter('redirect_post_location', function($location) use ($warning) {
                        return add_query_arg('wpicp_content_blocked', urlencode($warning), $location);
                    });
                } else {
                    // 只显示警告
                    add_filter('redirect_post_location', function($location) use ($warning) {
                        return add_query_arg('wpicp_content_warning', urlencode($warning), $location);
                    });
                }
            }
        }
    }

    /**
     * 检查评论内容
     */
    public function check_comment_content($comment_id, $comment_approved, $comment_data) {
        // 如果评论已经被屏蔽，跳过
        if($comment_approved === 'spam' || $comment_approved === 'trash') {
            return;
        }

        $options = get_option('wpicp_options');

        // 检查是否启用了敏感内容检查
        if(isset($options['enable_sensitive_check']) && $options['enable_sensitive_check'] == 'yes') {
            require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
            $core = new WPICP_Core();

            // 获取评论内容
            $comment = get_comment($comment_id);

            // 检查评论内容
            $content_issues = $core->check_content_for_sensitive_words($comment->comment_content);

            if(!empty($content_issues)) {
                // 更改评论状态为待审核
                wp_set_comment_status($comment_id, '0');

                // 添加说明
                $note = __('【自动审核】检测到敏感内容：', 'wpicp') . "\n";

                foreach($content_issues as $issue) {
                    $note .= sprintf(
                        "- %s (%s，级别：%d)\n",
                        $issue['word'],
                        $issue['category'],
                        $issue['level']
                    );
                }

                // 添加元数据
                add_comment_meta($comment_id, 'wpicp_sensitive_content', $note);
            }
        }
    }

    /**
     * 过滤文章内容
     */
    public function filter_post_content($content) {
        $options = get_option('wpicp_options');

        // 检查是否启用了敏感内容检查
        if(isset($options['enable_sensitive_check']) && $options['enable_sensitive_check'] == 'yes') {
            require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
            $core = new WPICP_Core();

            // 检查内容
            $content_issues = $core->check_content_for_sensitive_words($content);

            if(!empty($content_issues)) {
                // 在编辑界面添加警告
                if(is_admin()) {
                    $warning = '<div class="notice notice-warning inline"><p><strong>' . __('警告：检测到敏感内容：', 'wpicp') . '</strong></p><ul>';

                    foreach($content_issues as $issue) {
                        $warning .= sprintf(
                            '<li>%s (%s，级别：%d)</li>',
                            esc_html($issue['word']),
                            esc_html($issue['category']),
                            esc_html($issue['level'])
                        );
                    }

                    $warning .= '</ul></div>';

                    // 将警告添加到内容的顶部
                    $content = $warning . $content;
                }
            }
        }

        return $content;
    }

    /**
     * 过滤评论内容
     */
    public function filter_comment_content($content) {
        $options = get_option('wpicp_options');

        // 检查是否启用了敏感内容检查
        if(isset($options['enable_sensitive_check']) && $options['enable_sensitive_check'] == 'yes' && is_admin()) {
            // 获取当前评论ID
            $comment_id = 0;
            if(isset($_GET['c']) && is_numeric($_GET['c'])) {
                $comment_id = intval($_GET['c']);
            } elseif(isset($_GET['comment']) && is_numeric($_GET['comment'])) {
                $comment_id = intval($_GET['comment']);
            }

            // 如果有敏感内容标记，显示警告
            if($comment_id > 0) {
                $sensitive_content = get_comment_meta($comment_id, 'wpicp_sensitive_content', true);

                if(!empty($sensitive_content)) {
                    $warning = '<div class="notice notice-warning inline"><p><strong>' . __('系统检测：', 'wpicp') . '</strong> ' . nl2br(esc_html($sensitive_content)) . '</p></div>';

                    // 将警告添加到内容的顶部
                    $content = $warning . $content;
                }
            }
        }

        return $content;
    }

    /**
     * 新增 - 处理实名信息表单提交
     */
    public function handle_real_name_form() {
        // 验证nonce
        if(!isset($_POST['wpicp_nonce']) || !wp_verify_nonce($_POST['wpicp_nonce'], 'wpicp_save_real_name')) {
            wp_die(__('安全验证失败，请重试。', 'wpicp'));
        }

        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 收集表单数据
        $data = array(
            'entity_type' => sanitize_text_field($_POST['entity_type']),
            'entity_name' => sanitize_text_field($_POST['entity_name']),
            'id_type' => sanitize_text_field($_POST['id_type']),
            'contact_phone' => sanitize_text_field($_POST['contact_phone']),
            'contact_email' => sanitize_text_field($_POST['contact_email']),
            'domain' => sanitize_text_field($_POST['domain']),
            'server_location' => sanitize_text_field($_POST['server_location']),
            'verification_status' => 'pending',
            'verification_method' => sanitize_text_field($_POST['verification_method'])
        );

        // 如果提供了ID号码，添加到数据中
        if(!empty($_POST['id_number'])) {
            $data['id_number'] = sanitize_text_field($_POST['id_number']);
        }

        // 保存或更新数据
        if(isset($_POST['record_id']) && !empty($_POST['record_id'])) {
            $record_id = intval($_POST['record_id']);
            $success = $core->update_real_name_info($record_id, $data);
            $message = __('实名认证信息已更新。', 'wpicp');
        } else {
            $record_id = $core->add_real_name_info($data);
            $success = ($record_id !== false);
            $message = __('实名认证信息已保存。请上传验证材料或进行API验证。', 'wpicp');
        }

        // 重定向到结果页面
        if($success) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-verification',
                'action' => 'edit',
                'id' => $record_id,
                'message' => urlencode($message)
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-verification',
                'error' => urlencode(__('保存信息时出错，请重试。', 'wpicp'))
            ), admin_url('admin.php')));
        }

        exit;
    }

    /**
     * 新增 - 处理验证文件上传
     */
    public function handle_verification_upload() {
        // 验证nonce
        if(!isset($_POST['wpicp_nonce']) || !wp_verify_nonce($_POST['wpicp_nonce'], 'wpicp_upload_verification')) {
            wp_die(__('安全验证失败，请重试。', 'wpicp'));
        }

        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;

        if($record_id <= 0) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-verification',
                'error' => urlencode(__('无效的记录ID。', 'wpicp'))
            ), admin_url('admin.php')));
            exit;
        }

        // 检查文件上传
        if(!isset($_FILES['verification_proof']) || $_FILES['verification_proof']['error'] != UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-verification',
                'action' => 'edit',
                'id' => $record_id,
                'error' => urlencode(__('文件上传失败，请重试。', 'wpicp'))
            ), admin_url('admin.php')));
            exit;
        }

        // 设置允许的文件类型
        $allowed_types = array('jpg', 'jpeg', 'png', 'pdf');
        $file_type = pathinfo($_FILES['verification_proof']['name'], PATHINFO_EXTENSION);

        if(!in_array(strtolower($file_type), $allowed_types)) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-verification',
                'action' => 'edit',
                'id' => $record_id,
                'error' => urlencode(__('不支持的文件类型，请上传JPG、PNG或PDF文件。', 'wpicp'))
            ), admin_url('admin.php')));
            exit;
        }

        // 上传文件
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/wpicp-verification/';

        // 创建目录（如果不存在）
        if(!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // 创建索引文件防止目录浏览
        if(!file_exists($target_dir . 'index.php')) {
            file_put_contents($target_dir . 'index.php', '<?php // Silence is golden.');
        }

        // 生成安全的文件名
        $file_name = 'verification_' . $record_id . '_' . time() . '.' . $file_type;
        $target_file = $target_dir . $file_name;

        // 移动上传的文件
        if(move_uploaded_file($_FILES['verification_proof']['tmp_name'], $target_file)) {
            // 更新数据库
            $file_url = $upload_dir['baseurl'] . '/wpicp-verification/' . $file_name;
            $success = $core->verify_real_name_info($record_id, 'manual', 'pending', $file_url);

            if($success) {
                wp_redirect(add_query_arg(array(
                    'page' => 'wpicp-verification',
                    'action' => 'edit',
                    'id' => $record_id,
                    'message' => urlencode(__('验证文件已上传，等待审核。', 'wpicp'))
                ), admin_url('admin.php')));
            } else {
                wp_redirect(add_query_arg(array(
                    'page' => 'wpicp-verification',
                    'action' => 'edit',
                    'id' => $record_id,
                    'error' => urlencode(__('更新数据库时出错。', 'wpicp'))
                ), admin_url('admin.php')));
            }
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-verification',
                'action' => 'edit',
                'id' => $record_id,
                'error' => urlencode(__('移动上传文件时出错。', 'wpicp'))
            ), admin_url('admin.php')));
        }

        exit;
    }

    /**
     * 新增 - 处理敏感词导入
     */
    public function handle_sensitive_words_import() {
        // 验证nonce
        if(!isset($_POST['wpicp_nonce']) || !wp_verify_nonce($_POST['wpicp_nonce'], 'wpicp_import_sensitive_words')) {
            wp_die(__('安全验证失败，请重试。', 'wpicp'));
        }

        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 检查文件上传
        if(!isset($_FILES['words_file']) || $_FILES['words_file']['error'] != UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-sensitive',
                'error' => urlencode(__('文件上传失败，请重试。', 'wpicp'))
            ), admin_url('admin.php')));
            exit;
        }

        // 读取文件内容
        $file_type = pathinfo($_FILES['words_file']['name'], PATHINFO_EXTENSION);
        $words_array = array();

        if($file_type == 'csv') {
            $file = fopen($_FILES['words_file']['tmp_name'], 'r');

            while(($line = fgetcsv($file)) !== false) {
                if(count($line) >= 1) {
                    $word_data = array(
                        'word' => $line[0],
                        'category' => isset($line[1]) ? $line[1] : 'general',
                        'level' => isset($line[2]) ? intval($line[2]) : 1
                    );

                    $words_array[] = $word_data;
                }
            }

            fclose($file);
        } elseif($file_type == 'txt') {
            $content = file_get_contents($_FILES['words_file']['tmp_name']);
            $lines = explode("\n", $content);

            foreach($lines as $line) {
                $line = trim($line);
                if(!empty($line)) {
                    $parts = explode(',', $line);

                    $word_data = array(
                        'word' => $parts[0],
                        'category' => isset($parts[1]) ? $parts[1] : 'general',
                        'level' => isset($parts[2]) ? intval($parts[2]) : 1
                    );

                    $words_array[] = $word_data;
                }
            }
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'wpicp-sensitive',
                'error' => urlencode(__('不支持的文件类型，请上传CSV或TXT文件。', 'wpicp'))
            ), admin_url('admin.php')));
            exit;
        }

        // 导入敏感词
        $count = $core->import_sensitive_words($words_array);

        wp_redirect(add_query_arg(array(
            'page' => 'wpicp-sensitive',
            'message' => urlencode(sprintf(__('成功导入 %d 个敏感词。', 'wpicp'), $count))
        ), admin_url('admin.php')));

        exit;
    }

    /**
     * 新增 - 处理敏感词导出
     */
    public function handle_sensitive_words_export() {
        // 验证nonce
        if(!isset($_POST['wpicp_nonce']) || !wp_verify_nonce($_POST['wpicp_nonce'], 'wpicp_export_sensitive_words')) {
            wp_die(__('安全验证失败，请重试。', 'wpicp'));
        }

        require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
        $core = new WPICP_Core();

        // 导出敏感词
        $core->export_sensitive_words();
        exit;
    }
}
