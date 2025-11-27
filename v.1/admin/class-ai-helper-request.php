<?php
/**
 * AJAX and request handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AJAX interactions.
 */
class AI_Helper_Request {
    /**
     * Singleton instance.
     *
     * @var AI_Helper_Request
     */
    private static $instance;

    /**
     * Last request cache key.
     */
    const LAST_REQUEST_META = 'ai_helper_last_request';

    /**
     * Retrieve instance.
     *
     * @return AI_Helper_Request
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Hook AJAX actions.
     */
    private function __construct() {
        add_action( 'wp_ajax_ai_helper_process', array( $this, 'process_request' ) );
        add_action( 'wp_ajax_ai_helper_fetch_post', array( $this, 'fetch_post_from_url' ) );
        add_action( 'wp_ajax_ai_helper_save_scenario', array( $this, 'save_scenario_settings' ) );
        add_action( 'wp_ajax_ai_helper_save_global', array( $this, 'save_global_settings' ) );
        add_action( 'wp_ajax_ai_helper_repeat', array( $this, 'repeat_last_request' ) );
    }

    /**
     * Process AI request.
     */
    public function process_request() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $scenario    = isset( $_POST['scenario'] ) ? sanitize_text_field( wp_unslash( $_POST['scenario'] ) ) : '';
        $raw_text    = isset( $_POST['text'] ) ? AI_Helper_Utils::sanitize_text_input( $_POST['text'] ) : '';
        $url         = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        $settings    = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
        $global      = isset( $_POST['global'] ) ? (array) wp_unslash( $_POST['global'] ) : array();
        $source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : '';

        if ( empty( $scenario ) ) {
            wp_send_json_error( array( 'message' => __( 'Не выбран сценарий.', 'ai-helper' ) ) );
        }

        $payload_text = '';
        $post_data    = array();

        if ( 'url' === $source_type ) {
            if ( empty( $url ) || ! AI_Helper_Utils::is_internal_url( $url ) ) {
                wp_send_json_error( array( 'message' => __( 'URL должен относиться к текущему сайту.', 'ai-helper' ) ) );
            }

            $post_data = AI_Helper_Utils::extract_post_data_from_url( $url );

            if ( empty( $raw_text ) && ! empty( $post_data['content'] ) ) {
                $payload_text = AI_Helper_Utils::sanitize_text_input( $post_data['content'] );
            } else {
                $payload_text = $raw_text;
            }

            if ( empty( $payload_text ) ) {
                wp_send_json_error( array( 'message' => __( 'Текст для обработки пуст.', 'ai-helper' ) ) );
            }
        } else {
            if ( empty( $raw_text ) ) {
                wp_send_json_error( array( 'message' => __( 'Поле текста пустое.', 'ai-helper' ) ) );
            }
            if ( mb_strlen( $raw_text ) > 20000 ) {
                wp_send_json_error( array( 'message' => __( 'Превышен лимит 20 000 символов.', 'ai-helper' ) ) );
            }
            $payload_text = $raw_text;
        }

        $payload = array(
            'scenario'      => $scenario,
            'source_type'   => $source_type,
            'text'          => $payload_text,
            'url'           => $url,
            'settings'      => $settings,
            'global_seo'    => wp_kses_post( $global['global_seo'] ?? '' ),
            'style_guide'   => wp_kses_post( $global['style_guide'] ?? '' ),
            'material_type' => sanitize_text_field( $global['material_type'] ?? 'news' ),
            'language'      => sanitize_text_field( $global['language'] ?? 'ru-RU' ),
        );

        if ( isset( $global['temperature'] ) ) {
            $payload['temperature'] = floatval( $global['temperature'] );
        }

        if ( ! empty( $post_data ) ) {
            $payload['post'] = $post_data;
        }

        $client   = new AI_Helper_API_Client();
        $response = $client->send_request( $payload );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $clean_html  = isset( $response['result_html'] ) ? AI_Helper_Utils::clean_result_html( $response['result_html'] ) : '';
        $result_text = isset( $response['result_text'] ) ? wp_strip_all_tags( $response['result_text'], true ) : '';

        $current_cost = isset( $response['cost']['current_request_usd'] ) ? (float) $response['cost']['current_request_usd'] : 0;
        $total_cost   = $current_cost > 0 ? AI_Helper_Settings::add_total_cost( $current_cost ) : AI_Helper_Settings::get_total_cost();

        $this->store_last_request( get_current_user_id(), $payload );

        wp_send_json_success(
            array(
                'result_text'  => $result_text,
                'result_html'  => $clean_html,
                'links'        => $response['links'] ?? array(),
                'blocks'       => $response['blocks'] ?? array(),
                'token_usage'  => $response['token_usage'] ?? array(),
                'cost'         => array(
                    'current_request_usd' => $current_cost,
                    'total_usd'           => $total_cost,
                ),
                'raw_response' => $response,
                'payload_echo' => $payload,
            )
        );
    }

    /**
     * Repeat last request for the user.
     */
    public function repeat_last_request() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $last    = get_user_meta( $user_id, self::LAST_REQUEST_META, true );

        if ( empty( $last ) || ! is_array( $last ) ) {
            wp_send_json_error( array( 'message' => __( 'Нет предыдущего запроса.', 'ai-helper' ) ) );
        }

        $_POST['scenario']    = $last['scenario'] ?? '';
        $_POST['text']        = $last['text'] ?? '';
        $_POST['url']         = $last['url'] ?? '';
        $_POST['settings']    = $last['settings'] ?? array();
        $_POST['global']      = array(
            'global_seo'    => $last['global_seo'] ?? '',
            'style_guide'   => $last['style_guide'] ?? '',
            'material_type' => $last['material_type'] ?? 'news',
            'language'      => $last['language'] ?? 'ru-RU',
            'temperature'   => $last['temperature'] ?? 1,
        );
        $_POST['source_type'] = $last['source_type'] ?? 'text';

        $this->process_request();
    }

    /**
     * Store last request for a user.
     *
     * @param int   $user_id User ID.
     * @param array $payload Payload.
     */
    private function store_last_request( $user_id, $payload ) {
        update_user_meta( $user_id, self::LAST_REQUEST_META, $payload );
    }

    /**
     * Save scenario settings.
     */
    public function save_scenario_settings() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $scenario = isset( $_POST['scenario'] ) ? sanitize_text_field( wp_unslash( $_POST['scenario'] ) ) : '';
        $settings = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();

        if ( empty( $scenario ) ) {
            wp_send_json_error( array( 'message' => __( 'Сценарий не выбран.', 'ai-helper' ) ) );
        }

        AI_Helper_Settings::save_scenario_settings( get_current_user_id(), $scenario, $settings );

        wp_send_json_success( array( 'message' => __( 'Настройки сохранены.', 'ai-helper' ) ) );
    }

    /**
     * Save global settings.
     */
    public function save_global_settings() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $global = isset( $_POST['global'] ) ? (array) wp_unslash( $_POST['global'] ) : array();

        AI_Helper_Settings::save_global_settings( $global );

        wp_send_json_success( array( 'message' => __( 'Глобальные настройки сохранены.', 'ai-helper' ) ) );
    }

    /**
     * Fetch post data from URL.
     */
    public function fetch_post_from_url() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        if ( empty( $url ) || ! AI_Helper_Utils::is_internal_url( $url ) ) {
            wp_send_json_error( array( 'message' => __( 'URL должен относиться к текущему сайту.', 'ai-helper' ) ) );
        }

        $post_data = AI_Helper_Utils::extract_post_data_from_url( $url );

        if ( empty( $post_data ) ) {
            wp_send_json_error( array( 'message' => __( 'Пост не найден.', 'ai-helper' ) ) );
        }

        wp_send_json_success( array( 'post' => $post_data ) );
    }
}
