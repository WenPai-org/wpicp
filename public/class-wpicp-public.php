<?php
/**
 * 插件的前端功能
 *
 * @package WPICP
 * @subpackage WPICP/public
 */

// 如果直接访问此文件，则退出
if (!defined('WPINC')) {
    die;
}

/**
 * ICP信息小工具类
 */
class WPICP_Widget extends WP_Widget {
    /**
     * 初始化小工具
     */
    public function __construct() {
        parent::__construct(
            'wpicp_widget',
            __('ICP备案信息', 'wpicp'),
            array('description' => __('显示ICP备案信息', 'wpicp'))
        );
    }

    /**
     * 前端显示小工具
     */
    public function widget($args, $instance) {
        $options = get_option('wpicp_options');

        if(empty($options['icp_number'])) {
            return;
        }

        $title = !empty($instance['title']) ? apply_filters('widget_title', $instance['title']) : __('ICP备案信息', 'wpicp');
        $icp_number = esc_html($options['icp_number']);
        $custom_link = !empty($options['custom_link']) ? esc_url($options['custom_link']) : 'https://beian.miit.gov.cn/';
        $style_class = !empty($options['display_style']) ? 'wpicp-style-' . esc_attr($options['display_style']) : 'wpicp-style-default';

        // 显示公安备案信息
        $police_number = isset($options['police_number']) ? esc_html($options['police_number']) : '';
        $police_link = isset($options['police_link']) ? esc_url($options['police_link']) : 'http://www.beian.gov.cn/';

        echo $args['before_widget'];

        if(!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        echo '<div class="wpicp-widget ' . $style_class . '">';
        echo '<a href="' . $custom_link . '" target="_blank" rel="nofollow noopener">' . $icp_number . '</a>';

        // 如果有公安备案号，也显示
        if(!empty($police_number)) {
            echo '<br>';
            echo '<a href="' . $police_link . '" target="_blank" rel="nofollow noopener">' . $police_number . '</a>';
        }

        echo '</div>';

        echo $args['after_widget'];
    }

    /**
     * 后台显示表单
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('ICP备案信息', 'wpicp');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('标题:', 'wpicp'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p><?php _e('ICP备案信息将从插件设置中获取。', 'wpicp'); ?></p>
        <?php
    }

    /**
     * 保存小工具选项
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

/**
 * 前端类 - 注意修改类名为WPICP_Public以匹配主文件中的引用
 */
class WPICP_Public {

    /**
     * 初始化类并设置属性
     */
    public function __construct() {
        // 根据设置决定在何处显示ICP信息
        $options = get_option('wpicp_options');
        $display_location = isset($options['display_location']) ? $options['display_location'] : 'footer';

        if($display_location == 'footer') {
            add_action('wp_footer', array($this, 'display_icp_info_footer'), 20);
        } elseif($display_location == 'sidebar') {
            add_action('widgets_init', array($this, 'register_icp_widget'));
        }

        // 注册短代码
        add_shortcode('wpicp_info', array($this, 'icp_info_shortcode'));

        // 添加备案状态检查
        add_action('wp_head', array($this, 'check_icp_status'));
    }

    /**
     * 在页脚显示ICP信息
     */
    public function display_icp_info_footer() {
        $options = get_option('wpicp_options');

        if(empty($options['icp_number'])) {
            return;
        }

        $icp_number = esc_html($options['icp_number']);
        $custom_link = !empty($options['custom_link']) ? esc_url($options['custom_link']) : 'https://beian.miit.gov.cn/';
        $style_class = !empty($options['display_style']) ? 'wpicp-style-' . esc_attr($options['display_style']) : 'wpicp-style-default';

        // 显示公安备案信息
        $police_number = isset($options['police_number']) ? esc_html($options['police_number']) : '';
        $police_link = isset($options['police_link']) ? esc_url($options['police_link']) : 'http://www.beian.gov.cn/';

        echo '<div class="wpicp-footer ' . $style_class . '">';
        echo '<a href="' . $custom_link . '" target="_blank" rel="nofollow noopener">' . $icp_number . '</a>';

        // 如果有公安备案号，也显示
        if(!empty($police_number)) {
            echo ' | <a href="' . $police_link . '" target="_blank" rel="nofollow noopener">' . $police_number . '</a>';
        }

        echo '</div>';
    }

    /**
     * 注册ICP信息小工具
     */
    public function register_icp_widget() {
        register_widget('WPICP_Widget');
    }

    /**
     * ICP信息短代码
     */
    public function icp_info_shortcode($atts) {
        $options = get_option('wpicp_options');

        if(empty($options['icp_number'])) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'style' => isset($options['display_style']) ? $options['display_style'] : 'default',
            ),
            $atts,
            'wpicp_info'
        );

        $icp_number = esc_html($options['icp_number']);
        $custom_link = !empty($options['custom_link']) ? esc_url($options['custom_link']) : 'https://beian.miit.gov.cn/';
        $style_class = 'wpicp-style-' . esc_attr($atts['style']);

        // 显示公安备案信息
        $police_number = isset($options['police_number']) ? esc_html($options['police_number']) : '';
        $police_link = isset($options['police_link']) ? esc_url($options['police_link']) : 'http://www.beian.gov.cn/';

        $output = '<div class="wpicp-shortcode ' . $style_class . '">';
        $output .= '<a href="' . $custom_link . '" target="_blank" rel="nofollow noopener">' . $icp_number . '</a>';

        // 如果有公安备案号，也显示
        if(!empty($police_number)) {
            $output .= ' | <a href="' . $police_link . '" target="_blank" rel="nofollow noopener">' . $police_number . '</a>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * 检查ICP备案状态
     */
    public function check_icp_status() {
        $options = get_option('wpicp_options');

        // 如果已启用状态通知
        if(isset($options['status_notification']) && $options['status_notification'] == 'yes') {
            $domain = parse_url(get_site_url(), PHP_URL_HOST);

            require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
            $core = new WPICP_Core();
            $status_message = $core->get_status_message($domain);

            // 如果未备案并且是管理员，显示前台提醒
            if(current_user_can('manage_options') &&
               ($status_message['icp_status'] == 'not_registered' || $status_message['icp_status'] == 'unknown')) {

                if(!isset($_COOKIE['wpicp_notice_dismissed'])) {
                    ?>
                    <div class="wpicp-admin-notice wpicp-status-notice">
                        <p><?php echo esc_html($status_message['message']); ?> <a href="<?php echo admin_url('admin.php?page=wpicp-guide'); ?>"><?php _e('查看备案指南', 'wpicp'); ?></a></p>
                        <button class="wpicp-notice-dismiss"><?php _e('忽略', 'wpicp'); ?></button>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var dismissButton = document.querySelector('.wpicp-notice-dismiss');
                        if(dismissButton) {
                            dismissButton.addEventListener('click', function() {
                                var notice = this.parentNode;
                                notice.style.display = 'none';
                                document.cookie = 'wpicp_notice_dismissed=1; path=/; max-age=86400';
                            });
                        }
                    });
                    </script>
                    <style>
                    .wpicp-status-notice {
                        background-color: #f8d7da;
                        color: #721c24;
                        padding: 10px 15px;
                        border-left: 4px solid #f5c6cb;
                        margin: 10px 0;
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        z-index: 9999;
                        max-width: 300px;
                    }
                    .wpicp-notice-dismiss {
                        background: none;
                        border: none;
                        color: #721c24;
                        cursor: pointer;
                        float: right;
                        font-size: 16px;
                    }
                    </style>
                    <?php
                }
            }
        }

        // 如果没有设置ICP备案号，显示提醒
        if(empty($options['icp_number']) && current_user_can('manage_options') && !is_admin()) {
            echo '<!-- WPICP提醒：尚未设置ICP备案号，请在管理后台设置 -->';

            if(!isset($_COOKIE['wpicp_notice_dismissed'])) {
                ?>
                <div class="wpicp-admin-notice">
                    <p><?php _e('提醒：您尚未设置ICP备案号。根据中国法律，网站需要进行ICP备案。', 'wpicp'); ?> <a href="<?php echo admin_url('admin.php?page=wpicp'); ?>"><?php _e('立即设置', 'wpicp'); ?></a></p>
                    <button class="wpicp-notice-dismiss"><?php _e('忽略', 'wpicp'); ?></button>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var dismissButton = document.querySelector('.wpicp-notice-dismiss');
                    if(dismissButton) {
                        dismissButton.addEventListener('click', function() {
                            var notice = this.parentNode;
                            notice.style.display = 'none';
                            document.cookie = 'wpicp_notice_dismissed=1; path=/; max-age=86400';
                        });
                    }
                });
                </script>
                <style>
                .wpicp-admin-notice {
                    background-color: #f8d7da;
                    color: #721c24;
                    padding: 10px 15px;
                    border-left: 4px solid #f5c6cb;
                    margin: 10px 0;
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 9999;
                    max-width: 300px;
                }
                .wpicp-notice-dismiss {
                    background: none;
                    border: none;
                    color: #721c24;
                    cursor: pointer;
                    float: right;
                    font-size: 16px;
                }
                </style>
                <?php
            }
        }

        // 验证方式是API，调用验证
        if(isset($options['verification_method']) && $options['verification_method'] == 'api' && !empty($options['icp_number']) && !empty($options['api_key'])) {
            require_once WPICP_PLUGIN_DIR . 'includes/class-wpicp-core.php';
            $core = new WPICP_Core();
            $core->verify_icp_status();
        }
    }
}
