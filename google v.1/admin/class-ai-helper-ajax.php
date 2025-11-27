<?php
defined( 'ABSPATH' ) || exit;

class AI_Helper_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_ai_helper_fetch_url', [ $this, 'fetch_url_data' ] );
        add_action( 'wp_ajax_ai_helper_process', [ $this, 'process_request' ] );
        add_action( 'wp_ajax_ai_helper_save_settings', [ $this, 'save_settings' ] );
    }

    /**
     * Извлечение данных из локального URL без внешних запросов
     */
    public function fetch_url_data() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $url = esc_url_raw( $_POST['url'] );
        $post_id = url_to_postid( $url );

        if ( ! $post_id ) {
            wp_send_json_error( 'URL не принадлежит этому сайту или пост не найден.' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( 'Ошибка получения данных поста.' );
        }

        // Сбор таксономий
        $categories = wp_get_post_categories( $post_id, ['fields' => 'names'] );
        $tags = wp_get_post_tags( $post_id, ['fields' => 'names'] );

        // Сбор полей Rank Math
        $rm_title = get_post_meta( $post_id, 'rank_math_title', true );
        $rm_desc  = get_post_meta( $post_id, 'rank_math_description', true );
        $rm_kw    = get_post_meta( $post_id, 'rank_math_focus_keyword', true );

        $data = [
            'post_id'      => $post_id,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'categories'   => $categories,
            'tags'         => $tags,
            'seo_data'     => [
                'title'       => $rm_title,
                'description' => $rm_desc,
                'keywords'    => $rm_kw
            ]
        ];

        wp_send_json_success( $data );
    }

    /**
     * Сохранение настроек сценария
     */
    public function save_settings() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();

        $scenario = sanitize_text_field( $_POST['scenario'] );
        $settings = isset($_POST['settings']) ? $_POST['settings'] : []; // Санитизация массива зависит от полей

        // Простая санитизация
        $clean_settings = array_map( 'sanitize_text_field', $settings );

        update_user_meta( get_current_user_id(), 'ai_helper_settings_' . $scenario, $clean_settings );
        
        // Глобальные тоже можно обновить тут, если пришли
        if ( isset( $_POST['global'] ) ) {
            $global_clean = array_map( 'sanitize_text_field', $_POST['global'] );
            // text area нуждаются в сохранении переносов, используем sanitize_textarea_field для них
            if(isset($_POST['global']['global_seo'])) $global_clean['global_seo'] = sanitize_textarea_field($_POST['global']['global_seo']);
            if(isset($_POST['global']['style_guide'])) $global_clean['style_guide'] = sanitize_textarea_field($_POST['global']['style_guide']);
            
            update_option( 'ai_helper_global_settings', $global_clean );
        }

        wp_send_json_success( 'Настройки сохранены' );
    }

    /**
     * Отправка запроса на API-мост
     */
    public function process_request() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );
        
        // 1. Сбор данных
        $payload = [
            'scenario'    => sanitize_text_field( $_POST['scenario'] ),
            'source_type' => sanitize_text_field( $_POST['source_type'] ), // 'text' or 'url'
            'text'        => wp_kses_post( $_POST['text'] ), // Чистим HTML, но оставляем структуру
            'url'         => esc_url_raw( $_POST['url'] ),
            'settings'    => isset($_POST['settings']) ? $_POST['settings'] : [],
            'global_seo'  => sanitize_textarea_field( $_POST['global_seo'] ),
            'style_guide' => sanitize_textarea_field( $_POST['style_guide'] ),
            'material_type' => sanitize_text_field( $_POST['material_type'] ),
            'language'    => sanitize_text_field( $_POST['language'] ),
            'temperature' => floatval( $_POST['temperature'] ),
        ];

        // Валидация
        if ( mb_strlen( $payload['text'] ) > 20000 ) {
            wp_send_json_error( 'Текст превышает 20 000 символов.' );
        }

        // 2. Отправка на API Мост
        $response = wp_remote_post( AI_HELPER_API_ENDPOINT, [
            'body'    => json_encode( $payload ),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . AI_HELPER_API_KEY
            ],
            'timeout' => 45
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Ошибка соединения с API: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            wp_send_json_error( 'API вернул ошибку (' . $code . '): ' . ($data['error'] ?? 'Unknown') );
        }

        if ( ! $data ) {
            wp_send_json_error( 'Некорректный JSON от API.' );
        }

        // 3. Обновление стоимости
        if ( isset( $data['cost']['current_request_usd'] ) ) {
            $cost = floatval( $data['cost']['current_request_usd'] );
            $total = get_option( 'ai_helper_cost_total', 0 );
            update_option( 'ai_helper_cost_total', $total + $cost );
        }

        // 4. Возврат результата
        wp_send_json_success( $data );
    }
}
