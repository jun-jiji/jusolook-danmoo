<?php
if (!defined('ABSPATH')) exit;

class Danmoo_CPT {

    const CPT = 'danmoo_post';
    const TAX = 'danmoo_category';

    public function __construct() {
        add_action('init', [__CLASS__, 'register']);
    }

    public static function register() {
        register_post_type(self::CPT, [
            'labels' => [
                'name'               => '대나무숲',
                'singular_name'      => '대나무숲 글',
                'add_new'            => '새 글 추가',
                'add_new_item'       => '새 글 추가',
                'edit_item'          => '글 수정',
                'view_item'          => '글 보기',
                'all_items'          => '전체 글',
                'search_items'       => '글 검색',
                'not_found'          => '글이 없습니다',
                'not_found_in_trash' => '휴지통에 글이 없습니다',
                'menu_name'          => '대나무숲',
            ],
            'public'             => true,
            'show_in_rest'       => true,
            'has_archive'        => false,
            'supports'           => ['title', 'editor'],
            'menu_icon'          => 'dashicons-admin-comments',
            'rewrite'            => ['slug' => 'danmoo', 'with_front' => false],
            'exclude_from_search' => true,
            'publicly_queryable' => true,
        ]);

        register_taxonomy(self::TAX, self::CPT, [
            'labels' => [
                'name'          => '카테고리',
                'singular_name' => '카테고리',
                'add_new_item'  => '새 카테고리 추가',
                'edit_item'     => '카테고리 수정',
                'all_items'     => '전체 카테고리',
                'search_items'  => '카테고리 검색',
                'menu_name'     => '카테고리',
            ],
            'public'            => true,
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'danmoo-category'],
        ]);
    }
}
