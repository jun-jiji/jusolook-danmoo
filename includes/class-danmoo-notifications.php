<?php
if (!defined('ABSPATH')) exit;

class Danmoo_Notifications {

    public function __construct() {
        add_action('danmoo_new_post', [$this, 'notify_new_post']);
        add_action('danmoo_new_comment', [$this, 'notify_new_comment'], 10, 2);
        add_action('danmoo_new_report', [$this, 'notify_new_report'], 10, 3);
    }

    private function get_settings() {
        return get_option('danmoo_settings', []);
    }

    private function get_email() {
        $s = $this->get_settings();
        return $s['notify_email'] ?? get_option('admin_email');
    }

    public function notify_new_post($post_id) {
        $s = $this->get_settings();
        if (empty($s['notify_new_post'])) return;

        $post  = get_post($post_id);
        $title = $post->post_title;
        $url   = get_permalink($post_id);

        wp_mail(
            $this->get_email(),
            "[대나무숲] 새 글: {$title}",
            "새 글이 등록되었습니다.\n\n제목: {$title}\n링크: {$url}\n\n관리자 페이지에서 확인해주세요.",
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }

    public function notify_new_comment($comment_id, $post_id) {
        $s = $this->get_settings();
        if (empty($s['notify_new_comment'])) return;

        $post  = get_post($post_id);
        $title = $post->post_title;
        $url   = get_permalink($post_id);

        global $wpdb;
        $comment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}danmoo_comments WHERE id = %d",
            $comment_id
        ));

        $label = $comment->is_author ? '글쓴이' : '익명' . $comment->anon_number;

        wp_mail(
            $this->get_email(),
            "[대나무숲] 새 댓글 - {$title}",
            "새 댓글이 등록되었습니다.\n\n글: {$title}\n작성자: {$label}\n내용: {$comment->content}\n링크: {$url}\n",
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }

    public function notify_new_report($target_id, $type, $reason) {
        $s = $this->get_settings();
        if (empty($s['notify_new_report'])) return;

        $type_label = $type === 'post' ? '글' : '댓글';

        $body = "새 신고가 접수되었습니다.\n\n대상: {$type_label} (ID: {$target_id})\n사유: {$reason}\n";

        if ($type === 'post') {
            $posts = get_posts([
                'post_type'   => Danmoo_CPT::CPT,
                'p'           => $target_id,
                'post_status' => 'any',
                'numberposts' => 1,
            ]);
            if ($posts) {
                $body .= "글 제목: {$posts[0]->post_title}\n링크: " . get_permalink($target_id) . "\n";
            }
        }

        wp_mail(
            $this->get_email(),
            "[대나무숲] 신고 접수 - {$type_label}",
            $body,
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }
}
