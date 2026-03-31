<?php
if (!defined('ABSPATH')) exit;

class Danmoo_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menus']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('manage_' . Danmoo_CPT::CPT . '_posts_columns', [$this, 'custom_columns']);
        add_action('manage_' . Danmoo_CPT::CPT . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('manage_edit-' . Danmoo_CPT::CPT . '_sortable_columns', [$this, 'sortable_columns']);
    }

    // ─── Menus ───

    public function add_menus() {
        add_submenu_page(
            'edit.php?post_type=' . Danmoo_CPT::CPT,
            '대나무숲 설정',
            '설정',
            'manage_options',
            'danmoo-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'edit.php?post_type=' . Danmoo_CPT::CPT,
            'IP 차단 관리',
            'IP 차단 관리',
            'manage_options',
            'danmoo-ip-bans',
            [$this, 'render_ip_bans_page']
        );

        add_submenu_page(
            'edit.php?post_type=' . Danmoo_CPT::CPT,
            '신고 관리',
            '신고 관리',
            'manage_options',
            'danmoo-reports',
            [$this, 'render_reports_page']
        );
    }

    // ─── Admin Columns ───

    public function custom_columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['danmoo_likes']    = '좋아요';
                $new['danmoo_views']    = '조회';
                $new['danmoo_comments'] = '댓글';
                $new['danmoo_reports']  = '신고';
                $new['danmoo_ip']       = 'IP';
            }
        }
        return $new;
    }

    public function column_content($column, $post_id) {
        switch ($column) {
            case 'danmoo_likes':
                echo (int) get_post_meta($post_id, '_danmoo_like_count', true);
                break;
            case 'danmoo_views':
                echo (int) get_post_meta($post_id, '_danmoo_view_count', true);
                break;
            case 'danmoo_comments':
                echo (int) get_post_meta($post_id, '_danmoo_comment_count', true);
                break;
            case 'danmoo_reports':
                $count = (int) get_post_meta($post_id, '_danmoo_report_count', true);
                echo $count > 0 ? '<span style="color:#ef4444;font-weight:600;">' . $count . '</span>' : '0';
                break;
            case 'danmoo_ip':
                echo esc_html(get_post_meta($post_id, '_danmoo_author_ip', true));
                break;
        }
    }

    public function sortable_columns($columns) {
        $columns['danmoo_likes']    = 'danmoo_likes';
        $columns['danmoo_views']    = 'danmoo_views';
        $columns['danmoo_comments'] = 'danmoo_comments';
        $columns['danmoo_reports']  = 'danmoo_reports';
        return $columns;
    }

    // ─── Settings ───

    public function register_settings() {
        register_setting('danmoo_settings_group', 'danmoo_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input) {
        return [
            'recaptcha_site_key'    => sanitize_text_field($input['recaptcha_site_key'] ?? ''),
            'recaptcha_secret_key'  => sanitize_text_field($input['recaptcha_secret_key'] ?? ''),
            'auto_approve_posts'    => !empty($input['auto_approve_posts']),
            'rate_limit_enabled'    => !empty($input['rate_limit_enabled']),
            'post_limit_count'      => max(1, (int) ($input['post_limit_count'] ?? 1)),
            'post_limit_window'     => max(1, (int) ($input['post_limit_window'] ?? 10)),
            'comment_limit_count'   => max(1, (int) ($input['comment_limit_count'] ?? 1)),
            'comment_limit_window'  => max(1, (int) ($input['comment_limit_window'] ?? 2)),
            'notify_new_post'       => !empty($input['notify_new_post']),
            'notify_new_comment'    => !empty($input['notify_new_comment']),
            'notify_new_report'     => !empty($input['notify_new_report']),
            'notify_email'          => sanitize_email($input['notify_email'] ?? get_option('admin_email')),
            'rules_content'         => wp_kses_post($input['rules_content'] ?? ''),
        ];
    }

    public function render_settings_page() {
        $s = get_option('danmoo_settings', []);
        ?>
        <div class="wrap">
            <h1>대나무숲 설정</h1>
            <form method="post" action="options.php">
                <?php settings_fields('danmoo_settings_group'); ?>

                <h2 class="title">reCAPTCHA v3</h2>
                <table class="form-table">
                    <tr>
                        <th>Site Key</th>
                        <td><input type="text" name="danmoo_settings[recaptcha_site_key]" value="<?php echo esc_attr($s['recaptcha_site_key'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Secret Key</th>
                        <td><input type="password" name="danmoo_settings[recaptcha_secret_key]" value="<?php echo esc_attr($s['recaptcha_secret_key'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <h2 class="title">글 설정</h2>
                <table class="form-table">
                    <tr>
                        <th>자동 승인</th>
                        <td><label><input type="checkbox" name="danmoo_settings[auto_approve_posts]" value="1" <?php checked(!empty($s['auto_approve_posts'])); ?>> 새 글 자동 게시</label></td>
                    </tr>
                </table>

                <h2 class="title">속도 제한</h2>
                <table class="form-table">
                    <tr>
                        <th>속도 제한 활성화</th>
                        <td><label><input type="checkbox" name="danmoo_settings[rate_limit_enabled]" value="1" <?php checked(!empty($s['rate_limit_enabled'])); ?>> 활성화</label></td>
                    </tr>
                    <tr>
                        <th>글 작성 제한</th>
                        <td>
                            <input type="number" name="danmoo_settings[post_limit_window]" value="<?php echo (int) ($s['post_limit_window'] ?? 10); ?>" min="1" style="width:60px">분 동안
                            <input type="number" name="danmoo_settings[post_limit_count]" value="<?php echo (int) ($s['post_limit_count'] ?? 1); ?>" min="1" style="width:60px">개까지
                        </td>
                    </tr>
                    <tr>
                        <th>댓글 작성 제한</th>
                        <td>
                            <input type="number" name="danmoo_settings[comment_limit_window]" value="<?php echo (int) ($s['comment_limit_window'] ?? 2); ?>" min="1" style="width:60px">분 동안
                            <input type="number" name="danmoo_settings[comment_limit_count]" value="<?php echo (int) ($s['comment_limit_count'] ?? 1); ?>" min="1" style="width:60px">개까지
                        </td>
                    </tr>
                </table>

                <h2 class="title">알림</h2>
                <table class="form-table">
                    <tr>
                        <th>알림 이메일</th>
                        <td><input type="email" name="danmoo_settings[notify_email]" value="<?php echo esc_attr($s['notify_email'] ?? get_option('admin_email')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>알림 설정</th>
                        <td>
                            <label><input type="checkbox" name="danmoo_settings[notify_new_post]" value="1" <?php checked(!empty($s['notify_new_post'])); ?>> 새 글 알림</label><br>
                            <label><input type="checkbox" name="danmoo_settings[notify_new_comment]" value="1" <?php checked(!empty($s['notify_new_comment'])); ?>> 새 댓글 알림</label><br>
                            <label><input type="checkbox" name="danmoo_settings[notify_new_report]" value="1" <?php checked(!empty($s['notify_new_report'])); ?>> 신고 알림</label>
                        </td>
                    </tr>
                </table>

                <h2 class="title">커뮤니티 규칙</h2>
                <table class="form-table">
                    <tr>
                        <th>규칙 내용</th>
                        <td><textarea name="danmoo_settings[rules_content]" rows="10" class="large-text"><?php echo esc_textarea($s['rules_content'] ?? ''); ?></textarea></td>
                    </tr>
                </table>

                <?php submit_button('설정 저장'); ?>
            </form>
        </div>
        <?php
    }

    // ─── IP Bans Page ───

    public function render_ip_bans_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_ip_bans';
        $bans  = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1>IP 차단 관리</h1>

            <h2>IP 차단 추가</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('danmoo_add_ip_ban'); ?>
                <input type="hidden" name="action" value="danmoo_add_ip_ban">
                <table class="form-table">
                    <tr>
                        <th>IP 주소</th>
                        <td><input type="text" name="ip_address" class="regular-text" required placeholder="예: 123.456.789.0"></td>
                    </tr>
                    <tr>
                        <th>사유</th>
                        <td><input type="text" name="reason" class="regular-text" placeholder="차단 사유 (선택)"></td>
                    </tr>
                </table>
                <?php submit_button('차단 추가'); ?>
            </form>

            <h2>차단 목록</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>IP 주소</th>
                        <th>사유</th>
                        <th>차단일</th>
                        <th>작업</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bans)): ?>
                        <tr><td colspan="4">차단된 IP가 없습니다.</td></tr>
                    <?php else: foreach ($bans as $ban): ?>
                        <tr>
                            <td><?php echo esc_html($ban->ip_address); ?></td>
                            <td><?php echo esc_html($ban->reason); ?></td>
                            <td><?php echo esc_html($ban->created_at); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=danmoo_remove_ip_ban&id=' . $ban->id), 'danmoo_remove_ip_ban_' . $ban->id); ?>" class="button button-small" onclick="return confirm('차단을 해제하시겠습니까?');">해제</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ─── Reports Page ───

    public function render_reports_page() {
        global $wpdb;
        $table   = $wpdb->prefix . 'danmoo_reports';
        $reports = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1>신고 관리</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>유형</th>
                        <th>대상 ID</th>
                        <th>사유</th>
                        <th>신고자 IP</th>
                        <th>상태</th>
                        <th>신고일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="6">신고가 없습니다.</td></tr>
                    <?php else: foreach ($reports as $r): ?>
                        <tr>
                            <td><?php echo $r->target_type === 'post' ? '글' : '댓글'; ?></td>
                            <td>
                                <?php
                                if ($r->target_type === 'post') {
                                    $link = get_permalink($r->target_id);
                                    echo '<a href="' . esc_url($link) . '" target="_blank">#' . $r->target_id . '</a>';
                                } else {
                                    echo '#' . $r->target_id;
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($r->reason); ?></td>
                            <td><?php echo esc_html($r->ip_address); ?></td>
                            <td><?php echo esc_html($r->status); ?></td>
                            <td><?php echo esc_html($r->created_at); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Handle IP ban add/remove via admin-post.php
add_action('admin_post_danmoo_add_ip_ban', function () {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'danmoo_add_ip_ban')) {
        wp_die('Unauthorized');
    }

    $ip     = sanitize_text_field($_POST['ip_address'] ?? '');
    $reason = sanitize_text_field($_POST['reason'] ?? '');

    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        global $wpdb;
        $wpdb->replace($wpdb->prefix . 'danmoo_ip_bans', [
            'ip_address' => $ip,
            'reason'     => $reason,
            'banned_by'  => get_current_user_id(),
        ]);
    }

    wp_redirect(admin_url('edit.php?post_type=' . Danmoo_CPT::CPT . '&page=danmoo-ip-bans&updated=1'));
    exit;
});

add_action('admin_post_danmoo_remove_ip_ban', function () {
    $id = (int) ($_GET['id'] ?? 0);
    if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'danmoo_remove_ip_ban_' . $id)) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'danmoo_ip_bans', ['id' => $id]);

    wp_redirect(admin_url('edit.php?post_type=' . Danmoo_CPT::CPT . '&page=danmoo-ip-bans&updated=1'));
    exit;
});
