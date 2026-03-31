<?php
if (!defined('ABSPATH')) exit;

class Danmoo_REST_API {

    const NAMESPACE = 'danmoo/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Posts
        register_rest_route(self::NAMESPACE, '/posts', [
            ['methods' => 'GET',  'callback' => [$this, 'get_posts'],   'permission_callback' => '__return_true'],
            ['methods' => 'POST', 'callback' => [$this, 'create_post'], 'permission_callback' => '__return_true'],
        ]);

        register_rest_route(self::NAMESPACE, '/posts/(?P<hash>[a-z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_post'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/posts/(?P<hash>[a-z0-9]+)/view', [
            'methods'             => 'POST',
            'callback'            => [$this, 'record_view'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/posts/(?P<hash>[a-z0-9]+)/like', [
            'methods'             => 'POST',
            'callback'            => [$this, 'toggle_like'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/posts/(?P<hash>[a-z0-9]+)/report', [
            'methods'             => 'POST',
            'callback'            => [$this, 'report_post'],
            'permission_callback' => '__return_true',
        ]);

        // Comments
        register_rest_route(self::NAMESPACE, '/posts/(?P<hash>[a-z0-9]+)/comments', [
            ['methods' => 'GET',  'callback' => [$this, 'get_comments'],    'permission_callback' => '__return_true'],
            ['methods' => 'POST', 'callback' => [$this, 'create_comment'],  'permission_callback' => '__return_true'],
        ]);

        register_rest_route(self::NAMESPACE, '/comments/(?P<id>\d+)/like', [
            'methods'             => 'POST',
            'callback'            => [$this, 'like_comment'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/comments/(?P<id>\d+)/report', [
            'methods'             => 'POST',
            'callback'            => [$this, 'report_comment'],
            'permission_callback' => '__return_true',
        ]);

        // Categories
        register_rest_route(self::NAMESPACE, '/categories', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_categories'],
            'permission_callback' => '__return_true',
        ]);

        // Admin endpoints
        register_rest_route(self::NAMESPACE, '/admin/posts/(?P<hash>[a-z0-9]+)/hide', [
            'methods'             => 'POST',
            'callback'            => [$this, 'admin_hide_post'],
            'permission_callback' => [$this, 'is_admin'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/posts/(?P<hash>[a-z0-9]+)/delete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'admin_delete_post'],
            'permission_callback' => [$this, 'is_admin'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/comments/(?P<id>\d+)/delete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'admin_delete_comment'],
            'permission_callback' => [$this, 'is_admin'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/ip-ban', [
            ['methods' => 'POST', 'callback' => [$this, 'admin_ban_ip'], 'permission_callback' => [$this, 'is_admin']],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/ip-ban/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'admin_unban_ip'], 'permission_callback' => [$this, 'is_admin']],
        ]);
    }

    public function is_admin() {
        return current_user_can('manage_options');
    }

    // ─── Posts ───

    public function get_posts($request) {
        $page     = max(1, (int) $request->get_param('page'));
        $per_page = min(50, max(1, (int) ($request->get_param('per_page') ?: 12)));
        $sort     = sanitize_text_field($request->get_param('sort') ?: 'latest');
        $category = sanitize_text_field($request->get_param('category') ?: '');

        $args = [
            'post_type'      => Danmoo_CPT::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ];

        switch ($sort) {
            case 'likes':
                $args['meta_key'] = '_danmoo_like_count';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'views':
                $args['meta_key'] = '_danmoo_view_count';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'comments':
                $args['meta_key'] = '_danmoo_comment_count';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
        }

        if ($category) {
            $args['tax_query'] = [[
                'taxonomy' => Danmoo_CPT::TAX,
                'field'    => 'slug',
                'terms'    => $category,
            ]];
        }

        $query = new WP_Query($args);
        $posts = [];

        foreach ($query->posts as $post) {
            $terms = wp_get_post_terms($post->ID, Danmoo_CPT::TAX, ['fields' => 'names']);
            $excerpt = mb_substr(wp_strip_all_tags($post->post_content), 0, 80, 'UTF-8');
            if (mb_strlen(wp_strip_all_tags($post->post_content), 'UTF-8') > 80) {
                $excerpt .= '...';
            }

            $posts[] = [
                'id'            => $post->ID,
                'hash'          => $post->post_name,
                'title'         => $post->post_title,
                'excerpt'       => $excerpt,
                'category'      => !empty($terms) ? $terms[0] : '',
                'category_slug' => '',
                'like_count'    => (int) get_post_meta($post->ID, '_danmoo_like_count', true),
                'view_count'    => (int) get_post_meta($post->ID, '_danmoo_view_count', true),
                'comment_count' => (int) get_post_meta($post->ID, '_danmoo_comment_count', true),
                'date'          => $post->post_date,
                'url'           => get_permalink($post->ID),
            ];

            // Fill category slug
            $term_objs = wp_get_post_terms($post->ID, Danmoo_CPT::TAX);
            if (!empty($term_objs)) {
                $posts[count($posts) - 1]['category_slug'] = $term_objs[0]->slug;
            }
        }

        return rest_ensure_response([
            'posts'       => $posts,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => $page,
        ]);
    }

    public function create_post($request) {
        $ip = Danmoo_DB::get_client_ip();

        // IP ban check
        if (Danmoo_DB::is_ip_banned($ip)) {
            return new WP_Error('ip_banned', 'IP가 차단되었습니다.', ['status' => 403]);
        }

        // Rate limit check
        if (!Danmoo_DB::check_rate_limit($ip, 'post')) {
            return new WP_Error('rate_limited', '잠시 후 다시 시도해주세요.', ['status' => 429]);
        }

        // reCAPTCHA check
        $token = sanitize_text_field($request->get_param('recaptcha_token') ?: '');
        if (!Danmoo_Recaptcha::verify($token)) {
            return new WP_Error('recaptcha_failed', '보안 인증에 실패했습니다.', ['status' => 403]);
        }

        $title    = sanitize_text_field($request->get_param('title'));
        $content  = wp_kses_post($request->get_param('content'));
        $category = sanitize_text_field($request->get_param('category'));

        if (empty($title) || empty($content)) {
            return new WP_Error('missing_fields', '제목과 내용을 입력해주세요.', ['status' => 400]);
        }

        $settings     = get_option('danmoo_settings', []);
        $auto_approve = $settings['auto_approve_posts'] ?? true;
        $hash         = $this->generate_unique_hash();

        $post_id = wp_insert_post([
            'post_type'    => Danmoo_CPT::CPT,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $auto_approve ? 'publish' : 'pending',
            'post_name'    => $hash,
        ]);

        if (is_wp_error($post_id)) {
            return new WP_Error('create_failed', '글 등록에 실패했습니다.', ['status' => 500]);
        }

        // Set category if provided
        if (!empty($category)) {
            wp_set_object_terms($post_id, $category, Danmoo_CPT::TAX);
        }

        // Store author IP and initialize counts
        update_post_meta($post_id, '_danmoo_author_ip', $ip);
        update_post_meta($post_id, '_danmoo_like_count', 0);
        update_post_meta($post_id, '_danmoo_view_count', 0);
        update_post_meta($post_id, '_danmoo_comment_count', 0);
        update_post_meta($post_id, '_danmoo_report_count', 0);

        // Register author in anon map (number 0 = author)
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'danmoo_anon_map', [
            'post_id'     => $post_id,
            'ip_address'  => $ip,
            'anon_number' => 0,
            'is_author'   => 1,
        ]);

        // Record rate limit
        Danmoo_DB::record_rate_limit($ip, 'post');

        // Trigger notification
        do_action('danmoo_new_post', $post_id);

        return rest_ensure_response([
            'success' => true,
            'hash'    => $hash,
            'url'     => get_permalink($post_id),
            'message' => '글이 등록되었습니다. 아래 링크를 저장해주세요!',
        ]);
    }

    public function get_post($request) {
        $hash = sanitize_title($request['hash']);
        $post = $this->get_post_by_hash($hash);

        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $terms  = wp_get_post_terms($post->ID, Danmoo_CPT::TAX, ['fields' => 'names']);
        $ip     = Danmoo_DB::get_client_ip();

        // Check if current visitor liked this post
        global $wpdb;
        $liked = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}danmoo_likes WHERE target_id = %d AND target_type = 'post' AND ip_address = %s",
            $post->ID, $ip
        ));

        return rest_ensure_response([
            'id'            => $post->ID,
            'hash'          => $post->post_name,
            'title'         => $post->post_title,
            'content'       => wpautop($post->post_content),
            'category'      => !empty($terms) ? $terms[0] : '',
            'like_count'    => (int) get_post_meta($post->ID, '_danmoo_like_count', true),
            'view_count'    => (int) get_post_meta($post->ID, '_danmoo_view_count', true),
            'comment_count' => (int) get_post_meta($post->ID, '_danmoo_comment_count', true),
            'liked'         => $liked,
            'date'          => $post->post_date,
            'url'           => get_permalink($post->ID),
        ]);
    }

    public function record_view($request) {
        $post = $this->get_post_by_hash(sanitize_title($request['hash']));
        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $ip = Danmoo_DB::get_client_ip();
        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_views';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE post_id = %d AND ip_address = %s",
            $post->ID, $ip
        ));

        if (!$exists) {
            $wpdb->insert($table, [
                'post_id'    => $post->ID,
                'ip_address' => $ip,
            ]);
            $new_count = (int) get_post_meta($post->ID, '_danmoo_view_count', true) + 1;
            update_post_meta($post->ID, '_danmoo_view_count', $new_count);
        }

        return rest_ensure_response(['success' => true, 'view_count' => (int) get_post_meta($post->ID, '_danmoo_view_count', true)]);
    }

    public function toggle_like($request) {
        $post = $this->get_post_by_hash(sanitize_title($request['hash']));
        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $ip = Danmoo_DB::get_client_ip();
        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_likes';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE target_id = %d AND target_type = 'post' AND ip_address = %s",
            $post->ID, $ip
        ));

        if ($existing) {
            $wpdb->delete($table, ['id' => $existing]);
            $count = max(0, (int) get_post_meta($post->ID, '_danmoo_like_count', true) - 1);
            update_post_meta($post->ID, '_danmoo_like_count', $count);
            $liked = false;
        } else {
            $wpdb->insert($table, [
                'target_id'   => $post->ID,
                'target_type' => 'post',
                'ip_address'  => $ip,
            ]);
            $count = (int) get_post_meta($post->ID, '_danmoo_like_count', true) + 1;
            update_post_meta($post->ID, '_danmoo_like_count', $count);
            $liked = true;
        }

        return rest_ensure_response(['success' => true, 'liked' => $liked, 'like_count' => $count]);
    }

    public function report_post($request) {
        $post = $this->get_post_by_hash(sanitize_title($request['hash']));
        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $ip     = Danmoo_DB::get_client_ip();
        $reason = sanitize_text_field($request->get_param('reason'));

        if (empty($reason)) {
            return new WP_Error('missing_reason', '신고 사유를 선택해주세요.', ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_reports';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE target_id = %d AND target_type = 'post' AND ip_address = %s",
            $post->ID, $ip
        ));

        if ($exists) {
            return new WP_Error('already_reported', '이미 신고한 글입니다.', ['status' => 409]);
        }

        $wpdb->insert($table, [
            'target_id'   => $post->ID,
            'target_type' => 'post',
            'reason'      => $reason,
            'ip_address'  => $ip,
        ]);

        $report_count = (int) get_post_meta($post->ID, '_danmoo_report_count', true) + 1;
        update_post_meta($post->ID, '_danmoo_report_count', $report_count);

        do_action('danmoo_new_report', $post->ID, 'post', $reason);

        return rest_ensure_response(['success' => true, 'message' => '신고가 접수되었습니다.']);
    }

    // ─── Comments ───

    public function get_comments($request) {
        $post = $this->get_post_by_hash(sanitize_title($request['hash']));
        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $ip = Danmoo_DB::get_client_ip();
        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_comments';

        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d AND status = 'published' ORDER BY created_at ASC",
            $post->ID
        ));

        $likes_table = $wpdb->prefix . 'danmoo_likes';
        $result = [];

        foreach ($comments as $c) {
            $liked = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $likes_table WHERE target_id = %d AND target_type = 'comment' AND ip_address = %s",
                $c->id, $ip
            ));

            $result[] = [
                'id'          => (int) $c->id,
                'content'     => esc_html($c->content),
                'anon_number' => (int) $c->anon_number,
                'is_author'   => (bool) $c->is_author,
                'anon_label'  => $c->is_author ? '글쓴이' : '익명' . $c->anon_number,
                'like_count'  => (int) $c->like_count,
                'liked'       => $liked,
                'date'        => $c->created_at,
            ];
        }

        return rest_ensure_response(['comments' => $result]);
    }

    public function create_comment($request) {
        $post = $this->get_post_by_hash(sanitize_title($request['hash']));
        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $ip = Danmoo_DB::get_client_ip();

        if (Danmoo_DB::is_ip_banned($ip)) {
            return new WP_Error('ip_banned', 'IP가 차단되었습니다.', ['status' => 403]);
        }

        if (!Danmoo_DB::check_rate_limit($ip, 'comment')) {
            return new WP_Error('rate_limited', '잠시 후 다시 시도해주세요.', ['status' => 429]);
        }

        $token = sanitize_text_field($request->get_param('recaptcha_token') ?: '');
        if (!Danmoo_Recaptcha::verify($token)) {
            return new WP_Error('recaptcha_failed', '보안 인증에 실패했습니다.', ['status' => 403]);
        }

        $content = sanitize_textarea_field($request->get_param('content'));
        if (empty($content)) {
            return new WP_Error('missing_content', '댓글 내용을 입력해주세요.', ['status' => 400]);
        }

        global $wpdb;
        $anon_table = $wpdb->prefix . 'danmoo_anon_map';

        // Get or assign anon number
        $anon = $wpdb->get_row($wpdb->prepare(
            "SELECT anon_number, is_author FROM $anon_table WHERE post_id = %d AND ip_address = %s",
            $post->ID, $ip
        ));

        if ($anon) {
            $anon_number = (int) $anon->anon_number;
            $is_author   = (bool) $anon->is_author;
        } else {
            $max_num = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(anon_number) FROM $anon_table WHERE post_id = %d AND is_author = 0",
                $post->ID
            ));
            $anon_number = $max_num + 1;
            $is_author   = false;

            $wpdb->insert($anon_table, [
                'post_id'     => $post->ID,
                'ip_address'  => $ip,
                'anon_number' => $anon_number,
                'is_author'   => 0,
            ]);
        }

        // Insert comment
        $comment_table = $wpdb->prefix . 'danmoo_comments';
        $wpdb->insert($comment_table, [
            'post_id'     => $post->ID,
            'content'     => $content,
            'ip_address'  => $ip,
            'anon_number' => $anon_number,
            'is_author'   => $is_author ? 1 : 0,
        ]);

        $comment_id = $wpdb->insert_id;

        // Update comment count
        $new_count = (int) get_post_meta($post->ID, '_danmoo_comment_count', true) + 1;
        update_post_meta($post->ID, '_danmoo_comment_count', $new_count);

        Danmoo_DB::record_rate_limit($ip, 'comment');

        do_action('danmoo_new_comment', $comment_id, $post->ID);

        return rest_ensure_response([
            'success'    => true,
            'comment'    => [
                'id'          => $comment_id,
                'content'     => esc_html($content),
                'anon_number' => $anon_number,
                'is_author'   => $is_author,
                'anon_label'  => $is_author ? '글쓴이' : '익명' . $anon_number,
                'like_count'  => 0,
                'liked'       => false,
                'date'        => current_time('mysql'),
            ],
        ]);
    }

    public function like_comment($request) {
        $comment_id = (int) $request['id'];
        $ip = Danmoo_DB::get_client_ip();

        global $wpdb;
        $likes_table   = $wpdb->prefix . 'danmoo_likes';
        $comment_table = $wpdb->prefix . 'danmoo_comments';

        $comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $comment_table WHERE id = %d AND status = 'published'", $comment_id));
        if (!$comment) {
            return new WP_Error('not_found', '댓글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $likes_table WHERE target_id = %d AND target_type = 'comment' AND ip_address = %s",
            $comment_id, $ip
        ));

        if ($existing) {
            $wpdb->delete($likes_table, ['id' => $existing]);
            $new_count = max(0, (int) $comment->like_count - 1);
            $liked = false;
        } else {
            $wpdb->insert($likes_table, [
                'target_id'   => $comment_id,
                'target_type' => 'comment',
                'ip_address'  => $ip,
            ]);
            $new_count = (int) $comment->like_count + 1;
            $liked = true;
        }

        $wpdb->update($comment_table, ['like_count' => $new_count], ['id' => $comment_id]);

        return rest_ensure_response(['success' => true, 'liked' => $liked, 'like_count' => $new_count]);
    }

    public function report_comment($request) {
        $comment_id = (int) $request['id'];
        $ip         = Danmoo_DB::get_client_ip();
        $reason     = sanitize_text_field($request->get_param('reason'));

        if (empty($reason)) {
            return new WP_Error('missing_reason', '신고 사유를 선택해주세요.', ['status' => 400]);
        }

        global $wpdb;
        $comment_table = $wpdb->prefix . 'danmoo_comments';
        $reports_table = $wpdb->prefix . 'danmoo_reports';

        $comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $comment_table WHERE id = %d", $comment_id));
        if (!$comment) {
            return new WP_Error('not_found', '댓글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $reports_table WHERE target_id = %d AND target_type = 'comment' AND ip_address = %s",
            $comment_id, $ip
        ));

        if ($exists) {
            return new WP_Error('already_reported', '이미 신고한 댓글입니다.', ['status' => 409]);
        }

        $wpdb->insert($reports_table, [
            'target_id'   => $comment_id,
            'target_type' => 'comment',
            'reason'      => $reason,
            'ip_address'  => $ip,
        ]);

        do_action('danmoo_new_report', $comment_id, 'comment', $reason);

        return rest_ensure_response(['success' => true, 'message' => '신고가 접수되었습니다.']);
    }

    // ─── Categories ───

    public function get_categories() {
        $terms = get_terms([
            'taxonomy'   => Danmoo_CPT::TAX,
            'hide_empty' => false,
        ]);

        $cats = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $cats[] = [
                    'id'    => $term->term_id,
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'count' => $term->count,
                ];
            }
        }

        return rest_ensure_response(['categories' => $cats]);
    }

    // ─── Admin ───

    public function admin_hide_post($request) {
        $post = $this->get_post_by_hash(sanitize_title($request['hash']));
        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        wp_update_post(['ID' => $post->ID, 'post_status' => 'draft']);
        return rest_ensure_response(['success' => true, 'message' => '글이 숨겨졌습니다.']);
    }

    public function admin_delete_post($request) {
        $post = $this->get_post_by_hash(sanitize_title($request['hash']));
        if (!$post) {
            return new WP_Error('not_found', '글을 찾을 수 없습니다.', ['status' => 404]);
        }

        wp_trash_post($post->ID);
        return rest_ensure_response(['success' => true, 'message' => '글이 삭제되었습니다.']);
    }

    public function admin_delete_comment($request) {
        $comment_id = (int) $request['id'];
        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_comments';

        $comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $comment_id));
        if (!$comment) {
            return new WP_Error('not_found', '댓글을 찾을 수 없습니다.', ['status' => 404]);
        }

        $wpdb->update($table, ['status' => 'deleted'], ['id' => $comment_id]);

        // Decrement comment count
        $post_id = (int) $comment->post_id;
        $count = max(0, (int) get_post_meta($post_id, '_danmoo_comment_count', true) - 1);
        update_post_meta($post_id, '_danmoo_comment_count', $count);

        return rest_ensure_response(['success' => true, 'message' => '댓글이 삭제되었습니다.']);
    }

    public function admin_ban_ip($request) {
        $ip     = sanitize_text_field($request->get_param('ip'));
        $reason = sanitize_text_field($request->get_param('reason') ?: '');

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return new WP_Error('invalid_ip', '유효하지 않은 IP 주소입니다.', ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'danmoo_ip_bans';

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE ip_address = %s", $ip));
        if ($exists) {
            return new WP_Error('already_banned', '이미 차단된 IP입니다.', ['status' => 409]);
        }

        $wpdb->insert($table, [
            'ip_address' => $ip,
            'reason'     => $reason,
            'banned_by'  => get_current_user_id(),
        ]);

        return rest_ensure_response(['success' => true, 'message' => 'IP가 차단되었습니다.']);
    }

    public function admin_unban_ip($request) {
        $id = (int) $request['id'];
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'danmoo_ip_bans', ['id' => $id]);
        return rest_ensure_response(['success' => true, 'message' => 'IP 차단이 해제되었습니다.']);
    }

    // ─── Helpers ───

    private function get_post_by_hash($hash) {
        $posts = get_posts([
            'post_type'   => Danmoo_CPT::CPT,
            'name'        => $hash,
            'post_status' => ['publish', 'draft'],
            'numberposts' => 1,
        ]);
        return $posts[0] ?? null;
    }

    private function generate_unique_hash() {
        global $wpdb;
        for ($i = 0; $i < 10; $i++) {
            $hash = strtolower(wp_generate_password(10, false, false));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s LIMIT 1",
                $hash, Danmoo_CPT::CPT
            ));
            if (!$exists) {
                return $hash;
            }
        }
        return strtolower(substr(md5(uniqid(mt_rand(), true)), 0, 10));
    }
}
