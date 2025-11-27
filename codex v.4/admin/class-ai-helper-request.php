<?php
/**
 * AJAX requests for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Helper_Request {
    /**
     * Singleton instance.
     *
     * @var AI_Helper_Request
     */
    private static $instance;

    /**
     * Get instance.
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
     * Register hooks.
     */
    private function __construct() {
        add_action( 'wp_ajax_ai_helper_process', array( $this, 'process_request' ) );
        add_action( 'wp_ajax_ai_helper_save_scenario', array( $this, 'save_scenario_settings' ) );
    }

    /**
     * Handle processing request.
     */
    public function process_request() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $source   = isset( $_POST['source_text'] ) ? wp_unslash( $_POST['source_text'] ) : '';
        $scenario = isset( $_POST['scenario'] ) ? sanitize_text_field( wp_unslash( $_POST['scenario'] ) ) : '';
        $settings = isset( $_POST['settings'] ) ? (array) json_decode( wp_unslash( $_POST['settings'] ), true ) : array();
        $global   = isset( $_POST['global'] ) ? (array) json_decode( wp_unslash( $_POST['global'] ), true ) : array();

        if ( empty( $source ) || empty( $scenario ) ) {
            wp_send_json_error( array( 'message' => __( 'Заполните входные данные.', 'ai-helper' ) ) );
        }

        if ( strlen( $source ) > 20000 ) {
            wp_send_json_error( array( 'message' => __( 'Текст превышает 20 000 символов.', 'ai-helper' ) ) );
        }

        $source_type = filter_var( $source, FILTER_VALIDATE_URL ) ? 'url' : 'text';

        if ( 'url' === $source_type && ! AI_Helper_Utils::is_internal_url( $source ) ) {
            wp_send_json_error( array( 'message' => __( 'Укажите ссылку на внутренний пост сайта.', 'ai-helper' ) ) );
        }

        $payload     = array(
            'scenario'     => $scenario,
            'source_type'  => $source_type,
            'text'         => 'url' === $source_type ? '' : $source,
            'url'          => 'url' === $source_type ? esc_url_raw( $source ) : '',
            'settings'     => $settings,
            'global_seo'   => isset( $global['global_seo'] ) ? sanitize_textarea_field( $global['global_seo'] ) : '',
            'style_guide'  => isset( $global['style_guide'] ) ? sanitize_textarea_field( $global['style_guide'] ) : '',
            'material_type'=> isset( $global['material_type'] ) ? sanitize_text_field( $global['material_type'] ) : 'news',
            'temperature'  => isset( $global['temperature'] ) ? floatval( $global['temperature'] ) : 0,
            'language'     => isset( $global['language'] ) ? sanitize_text_field( $global['language'] ) : 'ru-RU',
        );

        AI_Helper_Settings::save_global_settings(
            array(
                'global_seo'    => $payload['global_seo'],
                'style_guide'   => $payload['style_guide'],
                'temperature'   => $payload['temperature'],
                'material_type' => $payload['material_type'],
                'language'      => $payload['language'],
            )
        );

        if ( 'url' === $source_type ) {
            $post_data = AI_Helper_Utils::extract_post_data( $source );
            if ( $post_data ) {
                $payload['post'] = $post_data;
            }
        }

        $client  = new AI_Helper_API_Client();
        $result  = $client->send_request( $payload );

        if ( isset( $result['error'] ) && $result['error'] ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        $cost_total = get_option( 'ai_helper_cost_total', 0 );
        $current    = isset( $result['cost']['current_request_usd'] ) ? floatval( $result['cost']['current_request_usd'] ) : 0;
        $new_total  = $cost_total + $current;
        update_option( 'ai_helper_cost_total', $new_total );

        $response = array(
            'result_text' => isset( $result['result_text'] ) ? wp_kses_post( $result['result_text'] ) : '',
            'result_html' => isset( $result['result_html'] ) ? AI_Helper_Utils::sanitize_html( $result['result_html'] ) : '',
            'links'       => isset( $result['links'] ) ? $result['links'] : array(),
            'blocks'      => isset( $result['blocks'] ) ? $result['blocks'] : array(),
            'token_usage' => isset( $result['token_usage'] ) ? $result['token_usage'] : array(),
            'cost'        => array(
                'current_request_usd' => $current,
                'total'               => $new_total,
            ),
        );

        wp_send_json_success( $response );
    }

    /**
     * Save scenario settings for current user.
     */
    public function save_scenario_settings() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        $scenario = isset( $_POST['scenario'] ) ? sanitize_text_field( wp_unslash( $_POST['scenario'] ) ) : '';
        $settings = isset( $_POST['settings'] ) ? (array) json_decode( wp_unslash( $_POST['settings'] ), true ) : array();

        if ( empty( $scenario ) ) {
            wp_send_json_error( array( 'message' => __( 'Не указан сценарий.', 'ai-helper' ) ) );
        }

        AI_Helper_Settings::save_user_scenario_settings( $scenario, $settings );

        wp_send_json_success( array( 'message' => __( 'Настройки сохранены.', 'ai-helper' ) ) );
    }
}
