<?php
if (!defined('ABSPATH')) exit;

class Danmoo_DB {

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Likes (post + comment)
        dbDelta("CREATE TABLE {$prefix}danmoo_likes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            target_id bigint(20) unsigned NOT NULL,
            target_type varchar(10) NOT NULL DEFAULT 'post',
            ip_address varchar(45) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_like (target_id, target_type, ip_address),
            KEY target_id (target_id),
            KEY ip_address (ip_address)
        ) $charset;");

        // Views
        dbDelta("CREATE TABLE {$prefix}danmoo_views (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            ip_address varchar(45) NOT NULL,
            viewed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_view (post_id, ip_address),
            KEY post_id (post_id)
        ) $charset;");

        // Reports
        dbDelta("CREATE TABLE {$prefix}danmoo_reports (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            target_id bigint(20) unsigned NOT NULL,
            target_type varchar(10) NOT NULL DEFAULT 'post',
            reason varchar(100) NOT NULL,
            ip_address varchar(45) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY (id),
            UNIQUE KEY unique_report (target_id, target_type, ip_address),
            KEY target_id (target_id),
            KEY status (status)
        ) $charset;");

        // Comments (custom, not WP comments)
        dbDelta("CREATE TABLE {$prefix}danmoo_comments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            content text NOT NULL,
            ip_address varchar(45) NOT NULL,
            anon_number int unsigned NOT NULL,
            is_author tinyint(1) NOT NULL DEFAULT 0,
            like_count int unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'published',
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY ip_address (ip_address),
            KEY status (status)
        ) $charset;");

        // Anonymous number mapping
        dbDelta("CREATE TABLE {$prefix}danmoo_anon_map (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            ip_address varchar(45) NOT NULL,
            anon_number int unsigned NOT NULL,
            is_author tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_anon (post_id, ip_address),
            KEY post_id (post_id)
        ) $charset;");

        // IP bans
        dbDelta("CREATE TABLE {$prefix}danmoo_ip_bans (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            reason varchar(255) DEFAULT '',
            banned_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address)
        ) $charset;");

        // Rate limits
        dbDelta("CREATE TABLE {$prefix}danmoo_rate_limits (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            action_type varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_rate (ip_address, action_type, created_at)
        ) $charset;");
    }

    public static function is_ip_banned($ip) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}danmoo_ip_bans WHERE ip_address = %s LIMIT 1",
            $ip
        ));
    }

    public static function check_rate_limit($ip, $action_type) {
        $settings = get_option('danmoo_settings', []);
        if (empty($settings['rate_limit_enabled'])) {
            return true;
        }

        $count_key  = $action_type . '_limit_count';
        $window_key = $action_type . '_limit_window';
        $max_count  = (int) ($settings[$count_key] ?? 1);
        $window_min = (int) ($settings[$window_key] ?? 5);

        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_rate_limits';

        // Clean old entries
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE ip_address = %s AND action_type = %s AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $ip, $action_type, $window_min
        ));

        $recent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE ip_address = %s AND action_type = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $ip, $action_type, $window_min
        ));

        return $recent < $max_count;
    }

    public static function record_rate_limit($ip, $action_type) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'danmoo_rate_limits', [
            'ip_address'  => $ip,
            'action_type' => $action_type,
        ]);
    }

    public static function get_client_ip() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
