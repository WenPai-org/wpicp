<?php
/**
 * 插件的核心功能
 *
 * @package WPICP
 * @subpackage WPICP/includes
 */

// 如果直接访问此文件，则退出
if (!defined('WPINC')) {
    die;
}

/**
 * 核心功能类
 */
class WPICP_Core {

    /**
     * 敏感词列表
     */
    private $sensitive_words = array();

    /**
     * 构造函数
     */
    public function __construct() {
        // 加载敏感词列表
        $this->load_sensitive_words();
    }

    /**
     * 创建数据库表
     */
    public function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 创建ICP备案记录表
        $table_name = $wpdb->prefix . 'wpicp_records';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            icp_number varchar(100) NOT NULL,
            domain varchar(255) DEFAULT '' NOT NULL,
            approval_date date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 创建敏感词表
        $table_name = $wpdb->prefix . 'wpicp_sensitive_words';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            word varchar(255) NOT NULL,
            category varchar(100) DEFAULT 'general' NOT NULL,
            level tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY word (word)
        ) $charset_collate;";

        dbDelta($sql);

        // 新增 - 创建实名认证信息表
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_name varchar(255) NOT NULL,
            id_type varchar(50) NOT NULL,
            id_number varchar(255) NOT NULL,
            contact_phone varchar(50) NOT NULL,
            contact_email varchar(100) NOT NULL,
            domain varchar(255) NOT NULL,
            server_location varchar(255) NOT NULL,
            verification_status varchar(50) DEFAULT 'pending' NOT NULL,
            verification_method varchar(50) DEFAULT 'manual' NOT NULL,
            verification_date datetime DEFAULT NULL,
            verification_proof varchar(255) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta($sql);

        // 新增 - 创建备案历史记录表
        $table_name = $wpdb->prefix . 'wpicp_record_history';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            record_id mediumint(9) NOT NULL,
            action varchar(50) NOT NULL,
            data text NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY record_id (record_id)
        ) $charset_collate;";

        dbDelta($sql);

        // 新增 - 创建备案状态表
        $table_name = $wpdb->prefix . 'wpicp_status';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            icp_status varchar(50) DEFAULT 'unknown' NOT NULL,
            police_status varchar(50) DEFAULT 'unknown' NOT NULL,
            last_check datetime DEFAULT NULL,
            check_result text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY domain (domain)
        ) $charset_collate;";

        dbDelta($sql);

        // 初始化敏感词数据
        $this->initialize_sensitive_words();
    }

    /**
     * 初始化敏感词数据
     */
    private function initialize_sensitive_words() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_sensitive_words';

        // 检查表是否为空
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if($count == 0) {
            // 添加一些默认敏感词
            $default_words = array(
                array('word' => '政治敏感词1', 'category' => 'politics', 'level' => 3),
                array('word' => '政治敏感词2', 'category' => 'politics', 'level' => 3),
                array('word' => '违法词1', 'category' => 'illegal', 'level' => 3),
                array('word' => '违法词2', 'category' => 'illegal', 'level' => 2),
                array('word' => '色情词1', 'category' => 'adult', 'level' => 2),
                array('word' => '色情词2', 'category' => 'adult', 'level' => 2),
                array('word' => '广告词1', 'category' => 'spam', 'level' => 1),
                array('word' => '广告词2', 'category' => 'spam', 'level' => 1),
                // 新增更多敏感词
                array('word' => '非法组织', 'category' => 'politics', 'level' => 3),
                array('word' => '颠覆', 'category' => 'politics', 'level' => 3),
                array('word' => '暴力恐怖', 'category' => 'violence', 'level' => 3),
                array('word' => '赌博', 'category' => 'gambling', 'level' => 3),
                array('word' => '毒品', 'category' => 'drugs', 'level' => 3),
                array('word' => '非法交易', 'category' => 'illegal', 'level' => 3),
                array('word' => '侵权内容', 'category' => 'copyright', 'level' => 2),
                array('word' => '淫秽色情', 'category' => 'adult', 'level' => 3),
                array('word' => '诈骗', 'category' => 'scam', 'level' => 3),
                array('word' => '虚假信息', 'category' => 'fake', 'level' => 2)
            );

            foreach($default_words as $word) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'word' => $word['word'],
                        'category' => $word['category'],
                        'level' => $word['level'],
                    )
                );
            }
        }
    }

    /**
     * 加载敏感词列表
     */
    private function load_sensitive_words() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_sensitive_words';

        // 从缓存获取敏感词
        $sensitive_words = get_transient('wpicp_sensitive_words');

        if($sensitive_words === false) {
            // 如果缓存不存在，从数据库加载
            $results = $wpdb->get_results("SELECT word, category, level FROM $table_name", ARRAY_A);

            if($results) {
                $sensitive_words = array();
                foreach($results as $row) {
                    $sensitive_words[] = array(
                        'word' => $row['word'],
                        'category' => $row['category'],
                        'level' => intval($row['level']),
                    );
                }

                // 保存到缓存，缓存一天
                set_transient('wpicp_sensitive_words', $sensitive_words, DAY_IN_SECONDS);
            } else {
                $sensitive_words = array();
            }
        }

        $this->sensitive_words = $sensitive_words;
    }

    /**
     * 添加ICP备案记录
     */
    public function add_icp_record($name, $icp_number, $domain = '', $approval_date = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_records';

        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'icp_number' => $icp_number,
                'domain' => $domain,
                'approval_date' => $approval_date,
            )
        );

        if($result !== false) {
            $record_id = $wpdb->insert_id;
            $this->add_record_history($record_id, 'create', array(
                'name' => $name,
                'icp_number' => $icp_number,
                'domain' => $domain,
                'approval_date' => $approval_date
            ));
        }

        return $result !== false;
    }

    /**
     * 更新ICP备案记录
     */
    public function update_icp_record($id, $name, $icp_number, $domain = '', $approval_date = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_records';

        $old_record = $this->get_icp_record($id);

        $result = $wpdb->update(
            $table_name,
            array(
                'name' => $name,
                'icp_number' => $icp_number,
                'domain' => $domain,
                'approval_date' => $approval_date,
            ),
            array('id' => $id)
        );

        if($result !== false) {
            $this->add_record_history($id, 'update', array(
                'old' => array(
                    'name' => $old_record->name,
                    'icp_number' => $old_record->icp_number,
                    'domain' => $old_record->domain,
                    'approval_date' => $old_record->approval_date
                ),
                'new' => array(
                    'name' => $name,
                    'icp_number' => $icp_number,
                    'domain' => $domain,
                    'approval_date' => $approval_date
                )
            ));
        }

        return $result !== false;
    }

    /**
     * 删除ICP备案记录
     */
    public function delete_icp_record($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_records';

        $old_record = $this->get_icp_record($id);

        $result = $wpdb->delete(
            $table_name,
            array('id' => $id)
        );

        if($result !== false && $old_record) {
            $this->add_record_history($id, 'delete', array(
                'name' => $old_record->name,
                'icp_number' => $old_record->icp_number,
                'domain' => $old_record->domain,
                'approval_date' => $old_record->approval_date
            ));
        }

        return $result !== false;
    }

    /**
     * 获取单条ICP备案记录
     */
    public function get_icp_record($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_records';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));

        return $result;
    }

    /**
     * 获取所有ICP备案记录
     */
    public function get_all_icp_records() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_records';

        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        return $results;
    }

    /**
     * 导出ICP备案记录
     */
    public function export_icp_records() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_records';

        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);

        if(!$results) {
            wp_die(__('没有记录可供导出', 'wpicp'));
        }

        $filename = 'wpicp_records_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // 输出CSV标头
        fputcsv($output, array('ID', '名称', 'ICP备案号', '域名', '批准日期', '创建时间', '更新时间'));

        // 输出数据行
        foreach($results as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * 新增 - 添加实名认证信息
     */
    public function add_real_name_info($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';

        // 加密敏感信息
        if(!empty($data['id_number'])) {
            $data['id_number'] = $this->encrypt_data($data['id_number']);
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'entity_type' => $data['entity_type'],
                'entity_name' => $data['entity_name'],
                'id_type' => $data['id_type'],
                'id_number' => $data['id_number'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'domain' => $data['domain'],
                'server_location' => $data['server_location'],
                'verification_status' => $data['verification_status'],
                'verification_method' => $data['verification_method']
            )
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * 新增 - 更新实名认证信息
     */
    public function update_real_name_info($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';

        // 如果ID号码已更改，则重新加密
        if(!empty($data['id_number']) && strpos($data['id_number'], '$P$') !== 0) {
            $data['id_number'] = $this->encrypt_data($data['id_number']);
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $id)
        );

        return $result !== false;
    }

    /**
     * 新增 - 获取实名认证信息
     */
    public function get_real_name_info($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));

        return $result;
    }

    /**
     * 新增 - 获取实名认证信息（通过域名）
     */
    public function get_real_name_info_by_domain($domain) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE domain = %s",
            $domain
        ));

        return $result;
    }

    /**
     * 新增 - 获取所有实名认证信息
     */
    public function get_all_real_name_info() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';

        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        return $results;
    }

    /**
     * 新增 - 删除实名认证信息
     */
    public function delete_real_name_info($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';

        $result = $wpdb->delete(
            $table_name,
            array('id' => $id)
        );

        return $result !== false;
    }

    /**
     * 新增 - 验证实名认证信息
     */
    public function verify_real_name_info($id, $verification_method, $verification_status, $verification_proof = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_real_name_info';

        $data = array(
            'verification_status' => $verification_status,
            'verification_method' => $verification_method,
            'verification_date' => current_time('mysql'),
            'verification_proof' => $verification_proof
        );

        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $id)
        );

        return $result !== false;
    }

    /**
     * 新增 - 调用第三方API验证实名信息
     */
    public function verify_identity_with_api($name, $id_type, $id_number) {
        $options = get_option('wpicp_options');
        $api_key = isset($options['verification_api_key']) ? $options['verification_api_key'] : '';
        $api_provider = isset($options['verification_api_provider']) ? $options['verification_api_provider'] : '';

        if(empty($api_key) || empty($api_provider)) {
            return array(
                'success' => false,
                'message' => __('API密钥或提供商未配置', 'wpicp')
            );
        }

        // 从缓存中获取结果
        $cache_key = 'wpicp_identity_verify_' . md5($name . $id_type . $id_number);
        $cached_result = get_transient($cache_key);

        if($cached_result !== false) {
            return $cached_result;
        }

        // 这里应该是实际的API调用，根据不同的服务提供商调用不同的接口
        // 这里使用模拟数据演示
        switch($api_provider) {
            case 'aliyun':
                $result = $this->call_aliyun_verify_api($name, $id_type, $id_number, $api_key);
                break;
            case 'tencent':
                $result = $this->call_tencent_verify_api($name, $id_type, $id_number, $api_key);
                break;
            default:
                $result = array(
                    'success' => false,
                    'message' => __('不支持的API提供商', 'wpicp')
                );
        }

        // 缓存结果30天，避免频繁调用API
        set_transient($cache_key, $result, 30 * DAY_IN_SECONDS);

        return $result;
    }

    /**
     * 新增 - 调用阿里云实名认证API（模拟）
     */
    private function call_aliyun_verify_api($name, $id_type, $id_number, $api_key) {
        // 实际应用中，应该使用阿里云SDK调用实名认证API
        // 这里返回模拟数据
        $test_id_number = '123456789012345678'; // 模拟ID号码用于测试

        if($id_number == $test_id_number) {
            return array(
                'success' => true,
                'message' => __('身份验证成功', 'wpicp'),
                'data' => array(
                    'verified' => true,
                    'score' => 98
                )
            );
        } else {
            return array(
                'success' => true,
                'message' => __('身份验证失败，信息不匹配', 'wpicp'),
                'data' => array(
                    'verified' => false,
                    'score' => 30
                )
            );
        }
    }

    /**
     * 新增 - 调用腾讯云实名认证API（模拟）
     */
    private function call_tencent_verify_api($name, $id_type, $id_number, $api_key) {
        // 实际应用中，应该使用腾讯云SDK调用实名认证API
        // 这里返回模拟数据
        $test_id_number = '123456789012345678'; // 模拟ID号码用于测试

        if($id_number == $test_id_number) {
            return array(
                'success' => true,
                'message' => __('身份验证成功', 'wpicp'),
                'data' => array(
                    'verified' => true,
                    'similarity' => 95
                )
            );
        } else {
            return array(
                'success' => true,
                'message' => __('身份验证失败，信息不匹配', 'wpicp'),
                'data' => array(
                    'verified' => false,
                    'similarity' => 40
                )
            );
        }
    }

    /**
     * 新增 - 检查域名备案状态
     */
    public function check_domain_icp_status($domain) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_status';

        // 从缓存/数据库获取上次检查结果
        $status = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE domain = %s",
            $domain
        ));

        $now = current_time('mysql');
        $last_check_time = $status ? strtotime($status->last_check) : 0;
        $check_interval = 24 * 60 * 60; // 24小时

        // 如果没有记录或者上次检查时间超过24小时，重新检查
        if(!$status || (time() - $last_check_time) > $check_interval) {
            $options = get_option('wpicp_options');
            $api_key = isset($options['beian_query_api_key']) ? $options['beian_query_api_key'] : '';

            if(!empty($api_key)) {
                // 实际应用中，应该调用备案状态查询API
                // 这里使用模拟数据
                $check_result = $this->query_icp_status_with_api($domain, $api_key);
                $icp_status = isset($check_result['icp_status']) ? $check_result['icp_status'] : 'unknown';
                $police_status = isset($check_result['police_status']) ? $check_result['police_status'] : 'unknown';
            } else {
                // 手动设置的状态
                $manual_status = get_option('wpicp_manual_status_' . md5($domain), array());
                $icp_status = isset($manual_status['icp_status']) ? $manual_status['icp_status'] : 'unknown';
                $police_status = isset($manual_status['police_status']) ? $manual_status['police_status'] : 'unknown';
                $check_result = $manual_status;
            }

            // 更新或插入状态记录
            if($status) {
                $wpdb->update(
                    $table_name,
                    array(
                        'icp_status' => $icp_status,
                        'police_status' => $police_status,
                        'last_check' => $now,
                        'check_result' => maybe_serialize($check_result),
                        'updated_at' => $now
                    ),
                    array('id' => $status->id)
                );
            } else {
                $wpdb->insert(
                    $table_name,
                    array(
                        'domain' => $domain,
                        'icp_status' => $icp_status,
                        'police_status' => $police_status,
                        'last_check' => $now,
                        'check_result' => maybe_serialize($check_result),
                        'created_at' => $now,
                        'updated_at' => $now
                    )
                );
            }

            // 获取最新记录
            $status = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE domain = %s",
                $domain
            ));
        }

        return $status;
    }

    /**
     * 新增 - API查询ICP备案状态（模拟）
     */
    private function query_icp_status_with_api($domain, $api_key) {
        // 这里应该是实际的API调用，使用第三方服务查询备案状态
        // 这里使用模拟数据演示
        $test_domains = array(
            'example.com' => array(
                'icp_status' => 'registered',
                'icp_number' => '京ICP备12345678号-1',
                'entity_name' => '示例科技有限公司',
                'approval_date' => '2021-01-15',
                'police_status' => 'registered',
                'police_number' => '京公网安备11010802030294号'
            ),
            'example.org' => array(
                'icp_status' => 'not_registered',
                'police_status' => 'not_registered'
            ),
            'example.net' => array(
                'icp_status' => 'registered',
                'icp_number' => '沪ICP备87654321号-1',
                'entity_name' => '网络科技有限公司',
                'approval_date' => '2020-11-20',
                'police_status' => 'not_registered'
            )
        );

        // 如果是测试域名之一，返回预设数据
        if(isset($test_domains[$domain])) {
            return $test_domains[$domain];
        }

        // 对于其他域名，返回随机状态
        $random = rand(1, 100);
        if($random > 70) {
            return array(
                'icp_status' => 'registered',
                'icp_number' => '粤ICP备' . rand(10000000, 99999999) . '号-1',
                'entity_name' => '随机公司名称' . rand(1, 100),
                'approval_date' => date('Y-m-d', strtotime('-' . rand(30, 600) . ' days')),
                'police_status' => ($random > 85) ? 'registered' : 'not_registered',
                'police_number' => ($random > 85) ? '粤公网安备' . rand(1000000000, 9999999999) . '号' : ''
            );
        } else {
            return array(
                'icp_status' => 'not_registered',
                'police_status' => 'not_registered'
            );
        }
    }

    /**
     * 新增 - 手动设置备案状态
     */
    public function set_manual_status($domain, $icp_status, $police_status, $icp_number = '', $police_number = '') {
        $status_data = array(
            'icp_status' => $icp_status,
            'police_status' => $police_status,
            'icp_number' => $icp_number,
            'police_number' => $police_number,
            'last_updated' => current_time('mysql')
        );

        update_option('wpicp_manual_status_' . md5($domain), $status_data);

        // 更新状态表
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_status';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE domain = %s",
            $domain
        ));

        $now = current_time('mysql');

        if($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'icp_status' => $icp_status,
                    'police_status' => $police_status,
                    'last_check' => $now,
                    'check_result' => maybe_serialize($status_data),
                    'updated_at' => $now
                ),
                array('id' => $existing->id)
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'domain' => $domain,
                    'icp_status' => $icp_status,
                    'police_status' => $police_status,
                    'last_check' => $now,
                    'check_result' => maybe_serialize($status_data),
                    'created_at' => $now,
                    'updated_at' => $now
                )
            );
        }

        return true;
    }

    /**
     * 新增 - 获取备案状态提示消息
     */
    public function get_status_message($domain = '') {
        if(empty($domain)) {
            $domain = parse_url(get_site_url(), PHP_URL_HOST);
        }

        $status = $this->check_domain_icp_status($domain);

        if(!$status) {
            return array(
                'type' => 'warning',
                'message' => sprintf(__('未找到域名 %s 的备案信息，请尽快进行ICP备案。', 'wpicp'), $domain),
                'icp_status' => 'unknown',
                'police_status' => 'unknown'
            );
        }

        $check_result = maybe_unserialize($status->check_result);
        $icp_number = isset($check_result['icp_number']) ? $check_result['icp_number'] : '';
        $police_number = isset($check_result['police_number']) ? $check_result['police_number'] : '';

        // 备案状态提示
        if($status->icp_status == 'registered' && $status->police_status == 'registered') {
            return array(
                'type' => 'success',
                'message' => sprintf(__('域名 %s 已完成ICP备案和公安备案。', 'wpicp'), $domain),
                'icp_status' => 'registered',
                'police_status' => 'registered',
                'icp_number' => $icp_number,
                'police_number' => $police_number
            );
        } elseif($status->icp_status == 'registered') {
            return array(
                'type' => 'info',
                'message' => sprintf(__('域名 %s 已完成ICP备案，但尚未完成公安备案。', 'wpicp'), $domain),
                'icp_status' => 'registered',
                'police_status' => 'not_registered',
                'icp_number' => $icp_number
            );
        } else {
            return array(
                'type' => 'error',
                'message' => sprintf(__('域名 %s 尚未完成ICP备案，请尽快办理。', 'wpicp'), $domain),
                'icp_status' => 'not_registered',
                'police_status' => 'not_registered'
            );
        }
    }

    /**
     * 新增 - 添加备案记录历史
     */
    private function add_record_history($record_id, $action, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_record_history';

        $user_id = get_current_user_id();

        $result = $wpdb->insert(
            $table_name,
            array(
                'record_id' => $record_id,
                'action' => $action,
                'data' => json_encode($data),
                'user_id' => $user_id
            )
        );

        return $result !== false;
    }

    /**
     * 新增 - 获取备案记录历史
     */
    public function get_record_history($record_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_record_history';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, u.display_name as user_name
             FROM $table_name h
             LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
             WHERE h.record_id = %d
             ORDER BY h.created_at DESC",
            $record_id
        ));

        foreach($results as &$item) {
            $item->data = json_decode($item->data, true);
        }

        return $results;
    }

    /**
     * 验证ICP状态
     */
    public function verify_icp_status() {
        $options = get_option('wpicp_options');

        if(empty($options['icp_number']) || empty($options['api_key']) || $options['verification_method'] != 'api') {
            return false;
        }

        // 获取缓存的验证结果
        $verification_result = get_transient('wpicp_verification_result');

        if($verification_result === false) {
            // 这里应该是实际的API调用，这里使用模拟数据
            // 实际应用中，应该使用工信部API或第三方服务验证
            $icp_number = $options['icp_number'];
            $api_key = $options['api_key'];
            $site_url = get_site_url();

            // 模拟API调用
            $response = array(
                'status' => 'valid',
                'message' => '备案信息有效',
                'verified' => true
            );

            // 保存到缓存，缓存一周
            set_transient('wpicp_verification_result', $response, WEEK_IN_SECONDS);

            return $response;
        }

        return $verification_result;
    }

    /**
     * 检查网站合规性
     */
    public function check_site_compliance() {
        $options = get_option('wpicp_options');

        if(!isset($options['enable_sensitive_check']) || $options['enable_sensitive_check'] != 'yes') {
            return false;
        }

        $issues = array();
        $total_checked = 0;

        // 检查文章内容
        if(isset($_POST['check_posts']) && $_POST['check_posts'] == '1') {
            $posts = get_posts(array(
                'post_type' => 'post',
                'posts_per_page' => 100,
                'post_status' => 'publish'
            ));

            $total_checked += count($posts);

            foreach($posts as $post) {
                $content_issues = $this->check_content_for_sensitive_words($post->post_title . ' ' . $post->post_content);

                if(!empty($content_issues)) {
                    foreach($content_issues as $issue) {
                        $issues[] = array(
                            'type' => __('敏感内容', 'wpicp'),
                            'location' => sprintf(__('文章: %s (ID: %d)', 'wpicp'), $post->post_title, $post->ID),
                            'details' => sprintf(__('发现敏感词: %s (类别: %s)', 'wpicp'), $issue['word'], $issue['category']),
                            'suggestion' => __('请检查并修改相关内容', 'wpicp')
                        );
                    }
                }
            }
        }

        // 检查页面内容
        if(isset($_POST['check_pages']) && $_POST['check_pages'] == '1') {
            $pages = get_posts(array(
                'post_type' => 'page',
                'posts_per_page' => 50,
                'post_status' => 'publish'
            ));

            $total_checked += count($pages);

            foreach($pages as $page) {
                $content_issues = $this->check_content_for_sensitive_words($page->post_title . ' ' . $page->post_content);

                if(!empty($content_issues)) {
                    foreach($content_issues as $issue) {
                        $issues[] = array(
                            'type' => __('敏感内容', 'wpicp'),
                            'location' => sprintf(__('页面: %s (ID: %d)', 'wpicp'), $page->post_title, $page->ID),
                            'details' => sprintf(__('发现敏感词: %s (类别: %s)', 'wpicp'), $issue['word'], $issue['category']),
                            'suggestion' => __('请检查并修改相关内容', 'wpicp')
                        );
                    }
                }
            }
        }

        // 检查评论内容
        if(isset($_POST['check_comments']) && $_POST['check_comments'] == '1') {
            $comments = get_comments(array(
                'status' => 'approve',
                'number' => 100
            ));

            $total_checked += count($comments);

            foreach($comments as $comment) {
                $content_issues = $this->check_content_for_sensitive_words($comment->comment_content);

                if(!empty($content_issues)) {
                    foreach($content_issues as $issue) {
                        $issues[] = array(
                            'type' => __('敏感评论', 'wpicp'),
                            'location' => sprintf(__('评论ID: %d, 作者: %s', 'wpicp'), $comment->comment_ID, $comment->comment_author),
                            'details' => sprintf(__('发现敏感词: %s (类别: %s)', 'wpicp'), $issue['word'], $issue['category']),
                            'suggestion' => __('请检查并修改或删除相关评论', 'wpicp')
                        );
                    }
                }
            }
        }

        // 检查外部链接
        if(isset($_POST['check_links']) && $_POST['check_links'] == '1') {
            $posts = get_posts(array(
                'post_type' => array('post', 'page'),
                'posts_per_page' => 50,
                'post_status' => 'publish'
            ));

            $external_links = array();

            foreach($posts as $post) {
                // 提取所有链接
                preg_match_all('/<a\s[^>]*href=([\'"])(.+?)\1[^>]*>/i', $post->post_content, $matches);

                if(!empty($matches[2])) {
                    foreach($matches[2] as $url) {
                        if(!empty($url) && strpos($url, get_site_url()) === false && strpos($url, 'http') === 0) {
                            $external_links[] = array(
                                'url' => $url,
                                'post_id' => $post->ID,
                                'post_title' => $post->post_title
                            );
                        }
                    }
                }
            }

            $total_checked += count($external_links);

            // 检查外部链接（实际应用中应该调用API检查链接安全性）
            foreach($external_links as $link) {
                // 这里使用简单的模拟检查，实际应该使用安全API
                $unsafe_domains = array('example.com', 'unsafe-site.com', 'malware-site.net');

                $domain = parse_url($link['url'], PHP_URL_HOST);

                if(in_array($domain, $unsafe_domains)) {
                    $issues[] = array(
                        'type' => __('不安全链接', 'wpicp'),
                        'location' => sprintf(__('文章: %s (ID: %d)', 'wpicp'), $link['post_title'], $link['post_id']),
                        'details' => sprintf(__('发现不安全链接: %s', 'wpicp'), $link['url']),
                        'suggestion' => __('请检查并移除或替换此链接', 'wpicp')
                    );
                }
            }
        }

        // 新增 - 检查备案信息
        if(isset($_POST['check_beian_info']) && $_POST['check_beian_info'] == '1') {
            $domain = parse_url(get_site_url(), PHP_URL_HOST);
            $status = $this->check_domain_icp_status($domain);

            $total_checked++;

            if(!$status || $status->icp_status != 'registered') {
                $issues[] = array(
                    'type' => __('备案合规', 'wpicp'),
                    'location' => sprintf(__('域名: %s', 'wpicp'), $domain),
                    'details' => __('网站尚未完成ICP备案', 'wpicp'),
                    'suggestion' => __('请尽快完成ICP备案，以符合中国法律法规要求', 'wpicp')
                );
            }

            if(!$status || $status->police_status != 'registered') {
                $issues[] = array(
                    'type' => __('备案合规', 'wpicp'),
                    'location' => sprintf(__('域名: %s', 'wpicp'), $domain),
                    'details' => __('网站尚未完成公安备案', 'wpicp'),
                    'suggestion' => __('请尽快完成公安备案，以符合中国法律法规要求', 'wpicp')
                );
            }

            // 新增 - 检查实名认证情况
            $real_name_info = $this->get_real_name_info_by_domain($domain);

            if(!$real_name_info) {
                $issues[] = array(
                    'type' => __('实名认证', 'wpicp'),
                    'location' => sprintf(__('域名: %s', 'wpicp'), $domain),
                    'details' => __('尚未提交实名认证信息', 'wpicp'),
                    'suggestion' => __('请提交网站实名认证信息，以便备案使用', 'wpicp')
                );
            } elseif($real_name_info->verification_status != 'verified') {
                $issues[] = array(
                    'type' => __('实名认证', 'wpicp'),
                    'location' => sprintf(__('域名: %s', 'wpicp'), $domain),
                    'details' => __('实名认证尚未完成或验证失败', 'wpicp'),
                    'suggestion' => __('请完成实名认证流程，确保信息准确无误', 'wpicp')
                );
            }
        }

        return array(
            'issues' => $issues,
            'total_checked' => $total_checked
        );
    }

    /**
     * 检查内容是否包含敏感词
     */
    private function check_content_for_sensitive_words($content) {
        $issues = array();

        foreach($this->sensitive_words as $word_data) {
            $word = $word_data['word'];

            if(mb_stripos($content, $word) !== false) {
                $issues[] = array(
                    'word' => $word,
                    'category' => $word_data['category'],
                    'level' => $word_data['level']
                );
            }
        }

        return $issues;
    }

    /**
     * 数据加密
     */
    public function encrypt_data($data) {
        if(empty($data)) {
            return '';
        }

        // 使用WordPress的加密函数
        return wp_hash_password($data);
    }

    /**
     * 数据解密验证
     */
    public function verify_encrypted_data($original_data, $encrypted_data) {
        return wp_check_password($original_data, $encrypted_data);
    }

    /**
     * 字符串数据净化
     */
    public function sanitize_data($data) {
        return sanitize_text_field($data);
    }

    /**
     * 新增 - 导入敏感词
     */
    public function import_sensitive_words($words_array) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_sensitive_words';

        $count = 0;

        foreach($words_array as $word_data) {
            if(empty($word_data['word'])) {
                continue;
            }

            $word = $this->sanitize_data($word_data['word']);
            $category = isset($word_data['category']) ? $this->sanitize_data($word_data['category']) : 'general';
            $level = isset($word_data['level']) ? intval($word_data['level']) : 1;

            // 检查是否已存在
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE word = %s",
                $word
            ));

            if($exists) {
                // 更新
                $wpdb->update(
                    $table_name,
                    array(
                        'category' => $category,
                        'level' => $level
                    ),
                    array('word' => $word)
                );
            } else {
                // 插入
                $wpdb->insert(
                    $table_name,
                    array(
                        'word' => $word,
                        'category' => $category,
                        'level' => $level
                    )
                );

                if($wpdb->insert_id) {
                    $count++;
                }
            }
        }

        // 清除缓存
        delete_transient('wpicp_sensitive_words');

        // 重新加载
        $this->load_sensitive_words();

        return $count;
    }

    /**
     * 新增 - 导出敏感词
     */
    public function export_sensitive_words() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpicp_sensitive_words';

        $words = $wpdb->get_results("SELECT word, category, level FROM $table_name ORDER BY category, word", ARRAY_A);

        if(!$words) {
            wp_die(__('没有敏感词可供导出', 'wpicp'));
        }

        $filename = 'wpicp_sensitive_words_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // 输出CSV标头
        fputcsv($output, array('敏感词', '类别', '级别'));

        // 输出数据行
        foreach($words as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * 新增 - 获取备案指南数据
     */
    public function get_beian_guide_data($province = '') {
        // 这里应该从配置或数据库中加载不同省份的备案指南
        // 这里使用静态数据演示

        $general_guide = array(
            'title' => __('ICP备案和公安备案指南', 'wpicp'),
            'description' => __('根据《中华人民共和国网络安全法》和工信部相关规定，在中国境内提供网站服务需要进行ICP备案和公安备案。', 'wpicp'),
            'steps' => array(
                array(
                    'title' => __('第一步：准备资料', 'wpicp'),
                    'content' => __('1. 域名证书：确保域名在有效期内，且实名认证信息与备案主体一致。<br>2. 主体证件：个人需提供身份证正反面，企业需提供营业执照副本。<br>3. 网站负责人证件：身份证正反面、手持身份证照片。<br>4. 真实有效的联系方式：手机号码和电子邮箱。', 'wpicp')
                ),
                array(
                    'title' => __('第二步：选择服务接入商', 'wpicp'),
                    'content' => __('通过您的云服务提供商（如阿里云、腾讯云等）提交备案申请，他们会提供备案系统和相关支持。', 'wpicp')
                ),
                array(
                    'title' => __('第三步：填写资料', 'wpicp'),
                    'content' => __('1. 登录服务商备案系统，填写备案信息。<br>2. 上传所需证件照片。<br>3. 进行网站负责人视频核验或者提交幕布照片。', 'wpicp')
                ),
                array(
                    'title' => __('第四步：初审和提交管局审核', 'wpicp'),
                    'content' => __('1. 接入商初审：1-3个工作日。<br>2. 管局审核：一般为5-20个工作日，具体时间因各省管局而异。', 'wpicp')
                ),
                array(
                    'title' => __('第五步：获得备案号', 'wpicp'),
                    'content' => __('备案通过后，您将获得ICP备案号，格式如"京ICP备12345678号-1"。', 'wpicp')
                ),
                array(
                    'title' => __('第六步：公安备案', 'wpicp'),
                    'content' => __('1. 在获得ICP备案号后，前往当地公安机关网站进行公安备案。<br>2. 全国公安机关互联网站安全管理平台：<a href="http://www.beian.gov.cn" target="_blank">http://www.beian.gov.cn</a>', 'wpicp')
                ),
                array(
                    'title' => __('第七步：设置备案信息', 'wpicp'),
                    'content' => __('备案完成后，在网站底部添加备案号及链接（使用本插件可轻松完成）。', 'wpicp')
                )
            ),
            'important_notes' => array(
                __('备案信息必须真实、准确，不得提供虚假资料。', 'wpicp'),
                __('网站内容必须符合国家法律法规，不得含有违法违规信息。', 'wpicp'),
                __('备案成功后，若备案信息发生变更，需及时变更备案。', 'wpicp'),
                __('若网站内容与备案信息不符，可能导致网站被关闭。', 'wpicp')
            ),
            'useful_links' => array(
                array(
                    'title' => __('工信部备案管理系统', 'wpicp'),
                    'url' => 'https://beian.miit.gov.cn/'
                ),
                array(
                    'title' => __('全国公安机关互联网站安全管理平台', 'wpicp'),
                    'url' => 'http://www.beian.gov.cn/'
                )
            )
        );

        // 特定省份的指南
        $province_guides = array(
            'beijing' => array(
                'title' => __('北京地区ICP备案指南', 'wpicp'),
                'authority' => __('北京市通信管理局', 'wpicp'),
                'address' => __('北京市西城区闹市口大街1号院5号楼', 'wpicp'),
                'phone' => '010-12345678',
                'website' => 'http://bjca.miit.gov.cn/',
                'special_requirements' => array(
                    __('北京地区要求备案照片必须使用纯白色背景。', 'wpicp'),
                    __('企业备案需要提供最新的企业营业执照（副本）复印件并加盖公章。', 'wpicp')
                ),
                'processing_time' => __('约7-10个工作日', 'wpicp')
            ),
            'shanghai' => array(
                'title' => __('上海地区ICP备案指南', 'wpicp'),
                'authority' => __('上海市通信管理局', 'wpicp'),
                'address' => __('上海市静安区万航渡路2451号', 'wpicp'),
                'phone' => '021-12345678',
                'website' => 'http://shca.miit.gov.cn/',
                'special_requirements' => array(
                    __('上海地区企业备案需要提供上海市企业营业执照并加盖公章。', 'wpicp'),
                    __('外地企业在沪备案需要提供上海分公司证件。', 'wpicp')
                ),
                'processing_time' => __('约5-8个工作日', 'wpicp')
            )
            // 可以添加更多省份的指南
        );

        // 返回通用指南和特定省份指南（如果有）
        if(!empty($province) && isset($province_guides[$province])) {
            return array(
                'general' => $general_guide,
                'province' => $province_guides[$province]
            );
        }

        return array(
            'general' => $general_guide,
            'provinces' => array_keys($province_guides)
        );
    }

    /**
     * 新增 - 获取常见问题
     */
    public function get_faq_data() {
        return array(
            array(
                'question' => __('什么是ICP备案？', 'wpicp'),
                'answer' => __('ICP备案是指在中国大陆境内提供非经营性互联网信息服务，应当办理备案。备案成功后会获得工信部颁发的备案号，例如"京ICP备12345678号"。', 'wpicp')
            ),
            array(
                'question' => __('ICP备案和公安备案有什么区别？', 'wpicp'),
                'answer' => __('ICP备案是向工信部门申请，公安备案是向公安部门申请。两者针对不同法规要求，都是必须完成的。ICP备案完成后才能进行公安备案。', 'wpicp')
            ),
            array(
                'question' => __('个人网站需要备案吗？', 'wpicp'),
                'answer' => __('是的，无论是个人网站还是企业网站，只要服务器在中国大陆并向公众提供服务，都需要进行ICP备案和公安备案。', 'wpicp')
            ),
            array(
                'question' => __('备案大约需要多长时间？', 'wpicp'),
                'answer' => __('从提交资料到获得备案号，一般需要2-4周时间，具体取决于各省管局的审核速度。在此期间，您的网站可能无法访问。', 'wpicp')
            ),
            array(
                'question' => __('如何查询我的网站是否已备案？', 'wpicp'),
                'answer' => __('可以通过工信部备案管理系统（https://beian.miit.gov.cn/）输入域名进行查询。', 'wpicp')
            ),
            array(
                'question' => __('备案信息需要变更怎么办？', 'wpicp'),
                'answer' => __('如果备案主体、网站负责人、联系方式等信息发生变化，需要及时进行备案信息变更。变更流程与新备案类似，但审核时间通常会短一些。', 'wpicp')
            ),
            array(
                'question' => __('一个备案号可以用于多个网站吗？', 'wpicp'),
                'answer' => __('一个备案号下可以备案多个网站，每个网站会有不同的序号，如"京ICP备12345678号-1"、"京ICP备12345678号-2"。', 'wpicp')
            ),
            array(
                'question' => __('不进行备案会有什么后果？', 'wpicp'),
                'answer' => __('根据相关法规，未备案的网站将被关闭，情节严重的还可能面临罚款等处罚。', 'wpicp')
            ),
            array(
                'question' => __('使用香港、国外服务器需要备案吗？', 'wpicp'),
                'answer' => __('如果您的服务器在中国大陆境外（如香港、美国等），原则上不需要进行ICP备案，但网站内容仍需遵守中国法律法规。', 'wpicp')
            ),
            array(
                'question' => __('本插件如何帮助我进行备案？', 'wpicp'),
                'answer' => __('本插件提供备案信息的收集、验证、显示和管理功能，并提供备案指南和合规检查，但实际备案申请需要您通过接入服务商或直接向相关部门提交。', 'wpicp')
            )
        );
    }
}
