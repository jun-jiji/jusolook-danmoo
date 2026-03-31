<?php
if (!defined('ABSPATH')) exit;

class Danmoo_Shortcodes {

    public function __construct() {
        add_shortcode('danmoo_feed', [$this, 'render_feed']);
        add_shortcode('danmoo_submit', [$this, 'render_submit']);
        add_shortcode('danmoo_rules', [$this, 'render_rules']);
    }

    public function render_feed($atts) {
        $atts = shortcode_atts(['per_page' => 12], $atts);

        ob_start();
        ?>
        <div class="danmoo-feed" data-per-page="<?php echo (int) $atts['per_page']; ?>">
            <!-- Sort & Filter Bar -->
            <div class="danmoo-feed-toolbar">
                <div class="danmoo-sort-tabs">
                    <button class="danmoo-sort-tab active" data-sort="latest">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        최신순
                    </button>
                    <button class="danmoo-sort-tab" data-sort="likes">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        좋아요순
                    </button>
                    <button class="danmoo-sort-tab" data-sort="views">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        조회순
                    </button>
                    <button class="danmoo-sort-tab" data-sort="comments">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        댓글순
                    </button>
                </div>
                <div class="danmoo-category-filter" style="display:none;">
                    <button class="danmoo-category-chip active" data-category="">전체</button>
                    <!-- Categories loaded via JS -->
                </div>
            </div>

            <!-- Posts Grid -->
            <div class="danmoo-feed-grid"></div>

            <!-- Load More -->
            <div class="danmoo-feed-footer" style="display:none;">
                <button class="danmoo-btn danmoo-btn-outline danmoo-load-more">더 보기</button>
            </div>

            <!-- Empty State -->
            <div class="danmoo-feed-empty" style="display:none;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <p>아직 글이 없습니다.</p>
                <p class="danmoo-text-muted">첫 번째 글을 작성해보세요!</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_submit($atts) {
        $settings = get_option('danmoo_settings', []);
        $rules    = esc_html($settings['rules_content'] ?? '');

        ob_start();
        ?>
        <div class="danmoo-submit-wrapper">
            <form class="danmoo-submit-form">
                <h2 class="danmoo-submit-title">글쓰기</h2>
                <p class="danmoo-text-muted">익명으로 자유롭게 이야기해보세요.</p>

                <div class="danmoo-form-group">
                    <label class="danmoo-label" for="danmoo-title">제목 <span class="danmoo-required">*</span></label>
                    <input type="text" id="danmoo-title" class="danmoo-input" name="title" placeholder="제목을 입력해주세요" maxlength="100" required>
                </div>

                <div class="danmoo-form-group danmoo-category-group" style="display:none;">
                    <label class="danmoo-label" for="danmoo-category">카테고리</label>
                    <select id="danmoo-category" class="danmoo-select" name="category">
                        <option value="">카테고리 없음</option>
                        <!-- Loaded via JS -->
                    </select>
                </div>

                <div class="danmoo-form-group">
                    <label class="danmoo-label" for="danmoo-content">내용 <span class="danmoo-required">*</span></label>
                    <textarea id="danmoo-content" class="danmoo-textarea" name="content" placeholder="내용을 입력해주세요" rows="8" maxlength="5000" required></textarea>
                    <span class="danmoo-char-count">0/5000</span>
                </div>

                <div class="danmoo-form-group danmoo-rules-agree">
                    <label class="danmoo-checkbox-label">
                        <input type="checkbox" name="agree_rules" required>
                        <span>커뮤니티 이용 규칙에 동의합니다</span>
                    </label>
                    <button type="button" class="danmoo-rules-toggle">규칙 보기</button>
                    <div class="danmoo-rules-preview" style="display:none;">
                        <pre><?php echo $rules; ?></pre>
                    </div>
                </div>

                <div class="danmoo-form-actions">
                    <button type="submit" class="danmoo-btn danmoo-btn-primary danmoo-btn-lg">등록하기</button>
                </div>

                <!-- Success message (hidden by default) -->
                <div class="danmoo-submit-success" style="display:none;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--juso-success, #22c55e)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <h3>글이 등록되었습니다!</h3>
                    <p>아래 링크를 저장하면 나중에 글을 찾을 수 있습니다:</p>
                    <div class="danmoo-url-copy">
                        <input type="text" class="danmoo-url-input" readonly>
                        <button type="button" class="danmoo-btn danmoo-copy-btn">복사</button>
                    </div>
                    <a href="#" class="danmoo-btn danmoo-btn-outline danmoo-view-post-btn">글 보러가기</a>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_rules($atts) {
        $settings = get_option('danmoo_settings', []);
        $rules    = nl2br(esc_html($settings['rules_content'] ?? ''));

        ob_start();
        ?>
        <div class="danmoo-rules">
            <h2 class="danmoo-rules-title">커뮤니티 이용 규칙</h2>
            <div class="danmoo-rules-content">
                <?php echo $rules; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
