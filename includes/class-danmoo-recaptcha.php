<?php
if (!defined('ABSPATH')) exit;

class Danmoo_Recaptcha {

    public static function verify($token) {
        $settings = get_option('danmoo_settings', []);
        $secret   = $settings['recaptcha_secret_key'] ?? '';

        if (empty($secret) || empty($token)) {
            return empty($secret); // Skip if not configured, fail if configured but no token
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret,
                'response' => $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return !empty($body['success']) && ($body['score'] ?? 0) >= 0.5;
    }
}
