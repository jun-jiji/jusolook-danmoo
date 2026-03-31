<?php
/**
 * Plugin Name: Juso-Danmoo (대나무숲)
 * Description: Anonymous community board for Juso-Look
 * Version: 1.0.0
 * Author: JusoLook
 * Text Domain: juso-danmoo
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DANMOO_VERSION', '1.0.0');
define('DANMOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DANMOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DANMOO_PLUGIN_FILE', __FILE__);

final class Juso_Danmoo {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init();
    }

    private function includes() {
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-db.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-cpt.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-recaptcha.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-rest-api.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-frontend.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-shortcodes.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-admin.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-notifications.php';
    }

    private function init() {
        new Danmoo_CPT();
        new Danmoo_REST_API();
        new Danmoo_Frontend();
        new Danmoo_Shortcodes();
        new Danmoo_Notifications();

        if (is_admin()) {
            new Danmoo_Admin();
        }
    }

    public static function activate() {
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-db.php';
        require_once DANMOO_PLUGIN_DIR . 'includes/class-danmoo-cpt.php';

        Danmoo_DB::create_tables();
        Danmoo_CPT::register();
        flush_rewrite_rules();

        // Set default settings
        if (!get_option('danmoo_settings')) {
            update_option('danmoo_settings', [
                'recaptcha_site_key'    => '',
                'recaptcha_secret_key'  => '',
                'auto_approve_posts'    => true,
                'rate_limit_enabled'    => true,
                'post_limit_count'      => 1,
                'post_limit_window'     => 10,
                'comment_limit_count'   => 1,
                'comment_limit_window'  => 2,
                'notify_new_post'       => true,
                'notify_new_comment'    => false,
                'notify_new_report'     => true,
                'notify_email'          => get_option('admin_email'),
                'rules_content'         => "커뮤니티 이용 규칙\n\n1. 정치, 종교 관련 글 금지\n2. 개인 공격 및 비방 금지\n3. 스팸/광고 금지\n4. 혐오 발언 금지\n5. 위반 시 게시글 삭제 및 IP 차단될 수 있습니다.",
            ]);
        }
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}

register_activation_hook(__FILE__, ['Juso_Danmoo', 'activate']);
register_deactivation_hook(__FILE__, ['Juso_Danmoo', 'deactivate']);

add_action('plugins_loaded', function () {
    Juso_Danmoo::instance();
});
