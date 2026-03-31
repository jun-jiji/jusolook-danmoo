<?php
if (!defined('ABSPATH')) exit;

class Danmoo_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('the_content', [$this, 'render_single'], 20);
    }

    public function enqueue_assets() {
        if (!$this->should_load()) {
            return;
        }

        $css_file = DANMOO_PLUGIN_DIR . 'assets/css/danmoo.css';
        $js_file  = DANMOO_PLUGIN_DIR . 'assets/js/danmoo.js';

        wp_enqueue_style(
            'danmoo-style',
            DANMOO_PLUGIN_URL . 'assets/css/danmoo.css',
            [],
            file_exists($css_file) ? filemtime($css_file) : DANMOO_VERSION
        );

        wp_enqueue_script(
            'danmoo-script',
            DANMOO_PLUGIN_URL . 'assets/js/danmoo.js',
            [],
            file_exists($js_file) ? filemtime($js_file) : DANMOO_VERSION,
            true
        );

        $settings = get_option('danmoo_settings', []);
        $site_key = $settings['recaptcha_site_key'] ?? '';

        if ($site_key) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . urlencode($site_key), [], null, true);
        }

        wp_localize_script('danmoo-script', 'DANMOO', [
            'rest_url'       => esc_url_raw(rest_url('danmoo/v1/')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'recaptcha_key'  => $site_key,
            'is_admin'       => current_user_can('manage_options'),
            'i18n'           => [
                'loading'        => '로딩 중...',
                'load_more'      => '더 보기',
                'no_posts'       => '아직 글이 없습니다.',
                'submit'         => '등록하기',
                'submitting'     => '등록 중...',
                'comment_submit' => '댓글 등록',
                'like'           => '좋아요',
                'report'         => '신고',
                'delete'         => '삭제',
                'hide'           => '숨기기',
                'ban_ip'         => 'IP 차단',
                'confirm_delete' => '정말 삭제하시겠습니까?',
                'confirm_ban'    => '이 IP를 차단하시겠습니까?',
                'report_reasons' => [
                    '욕설/비방',
                    '스팸/광고',
                    '정치/종교',
                    '개인정보 노출',
                    '기타',
                ],
                'all_categories' => '전체',
                'sort_latest'    => '최신순',
                'sort_likes'     => '좋아요순',
                'sort_views'     => '조회순',
                'sort_comments'  => '댓글순',
                'save_url_msg'   => '이 링크를 저장하면 나중에 글을 찾을 수 있습니다:',
                'copied'         => '복사됨!',
                'views'          => '조회',
                'likes'          => '좋아요',
                'comments'       => '댓글',
            ],
        ]);
    }

    private function should_load() {
        global $post;

        if (is_singular(Danmoo_CPT::CPT)) {
            return true;
        }

        if ($post && (
            has_shortcode($post->post_content, 'danmoo_feed') ||
            has_shortcode($post->post_content, 'danmoo_submit') ||
            has_shortcode($post->post_content, 'danmoo_rules')
        )) {
            return true;
        }

        return false;
    }

    public function render_single($content) {
        if (!is_singular(Danmoo_CPT::CPT) || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        global $post;
        $hash     = $post->post_name;
        $title    = esc_html($post->post_title);
        $body     = wpautop($post->post_content);
        $terms    = wp_get_post_terms($post->ID, Danmoo_CPT::TAX, ['fields' => 'names']);
        $category = !empty($terms) ? esc_html($terms[0]) : '';
        $likes    = (int) get_post_meta($post->ID, '_danmoo_like_count', true);
        $views    = (int) get_post_meta($post->ID, '_danmoo_view_count', true);
        $comments = (int) get_post_meta($post->ID, '_danmoo_comment_count', true);
        $date     = get_the_date('Y.m.d H:i', $post);

        $is_admin = current_user_can('manage_options');
        $author_ip = get_post_meta($post->ID, '_danmoo_author_ip', true);

        ob_start();
        ?>
        <div class="danmoo-detail" data-hash="<?php echo esc_attr($hash); ?>">
            <!-- Main Content Card -->
            <article class="danmoo-detail-card">
                <h1 class="danmoo-detail-title"><?php echo $title; ?></h1>
                <div class="danmoo-detail-meta">
                    <?php if ($category): ?>
                        <span class="danmoo-badge"><?php echo $category; ?></span>
                    <?php endif; ?>
                    <span><?php echo $date; ?></span>
                    <span>조회 <strong class="danmoo-view-count"><?php echo $views; ?></strong></span>
                </div>
                <div class="danmoo-detail-content">
                    <?php echo $body; ?>
                </div>
            </article>

            <!-- Comments Card -->
            <div class="danmoo-comments-card">
                <div class="danmoo-comments-list" data-count="<?php echo $comments; ?>">
                    <!-- Comments loaded via JS -->
                </div>

                <!-- Action Buttons -->
                <div class="danmoo-detail-actions">
                    <button class="danmoo-action-btn danmoo-like-btn" data-target="post">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        <span class="danmoo-like-count"><?php echo $likes; ?></span>
                    </button>
                    <button class="danmoo-action-btn danmoo-report-btn" data-target="post">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                        신고
                    </button>
                    <?php if ($is_admin): ?>
                        <button class="danmoo-action-btn danmoo-action-btn-danger danmoo-admin-hide-btn">숨기기</button>
                        <button class="danmoo-action-btn danmoo-action-btn-danger danmoo-admin-delete-btn">삭제</button>
                        <button class="danmoo-action-btn danmoo-action-btn-danger danmoo-admin-ban-btn" data-ip="<?php echo esc_attr($author_ip); ?>">IP 차단</button>
                    <?php endif; ?>
                </div>

                <!-- Comment Form -->
                <form class="danmoo-comment-form">
                    <textarea class="danmoo-comment-input" placeholder="댓글을 입력해주세요..." rows="3" maxlength="1000"></textarea>
                    <div class="danmoo-comment-form-actions">
                        <span class="danmoo-comment-char-count">0/1000</span>
                        <button type="submit" class="danmoo-btn danmoo-btn-primary">댓글 등록</button>
                    </div>
                </form>
            </div>

            <!-- Report Modal -->
            <div class="danmoo-modal danmoo-report-modal" style="display:none;">
                <div class="danmoo-modal-backdrop"></div>
                <div class="danmoo-modal-content">
                    <h3>신고하기</h3>
                    <div class="danmoo-report-reasons"></div>
                    <div class="danmoo-modal-actions">
                        <button class="danmoo-btn danmoo-modal-cancel">취소</button>
                        <button class="danmoo-btn danmoo-btn-primary danmoo-modal-submit">신고하기</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
