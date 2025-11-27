<?php
/**
 * AJAX request handler.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Request handling logic.
 */
class AI_Helper_Request {
    /**
     * Register AJAX hooks.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_ai_helper_process', array( __CLASS__, 'process' ) );
        add_action( 'wp_ajax_ai_helper_save_scenario', array( __CLASS__, 'save_scenario' ) );
        add_action( 'wp_ajax_ai_helper_save_global', array( __CLASS__, 'save_global' ) );
    }

    /**
     * Process content request.
     */
    public static function process() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Недостаточно прав для выполнения запроса.', 'ai-helper' ) ) );
        }

        $source_input = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '';
        $scenario     = isset( $_POST['scenario'] ) ? sanitize_text_field( wp_unslash( $_POST['scenario'] ) ) : 'seo_links';
        $settings     = isset( $_POST['settings'] ) ? (array) json_decode( wp_unslash( $_POST['settings'] ), true ) : array();

        if ( empty( $source_input ) ) {
            wp_send_json_error( array( 'message' => __( 'Введите текст или URL для обработки.', 'ai-helper' ) ) );
        }

        $source_type = 'text';
        $text        = sanitize_textarea_field( $source_input );
        $url         = '';
        $post_data   = array();

        if ( AI_Helper_Utils::is_internal_url( $source_input ) ) {
            $source_type = 'url';
            $url         = esc_url_raw( $source_input );
            $post_data   = AI_Helper_Utils::extract_post_data( $url );

            if ( ! empty( $post_data['content'] ) ) {
                $text = wp_strip_all_tags( $post_data['content'] );
            }
        }

        if ( strlen( $text ) > 20000 ) {
            wp_send_json_error( array( 'message' => __( 'Текст превышает лимит в 20 000 символов.', 'ai-helper' ) ) );
        }

        $global_settings = AI_Helper_Settings::get_global_settings();

        $payload = array(
            'scenario'      => $scenario,
            'source_type'   => $source_type,
            'text'          => $text,
            'url'           => $url,
            'settings'      => $settings,
            'global_seo'    => $global_settings['global_seo'],
            'style_guide'   => $global_settings['style_guide'],
            'material_type' => $global_settings['material_type'],
            'language'      => $global_settings['language'],
            'temperature'   => $global_settings['temperature'],
            'post_data'     => $post_data,
        );

        $client   = new AI_Helper_API_Client();
        $response = $client->send_request( $payload );

        if ( isset( $response['result_html'] ) ) {
            $response['result_html'] = AI_Helper_Utils::sanitize_html( $response['result_html'] );
        }

        if ( ! empty( $response['error'] ) ) {
            wp_send_json_error( array( 'message' => $response['error'] ) );
        }

        if ( isset( $response['cost']['current_request_usd'] ) ) {
            AI_Helper_Settings::add_to_total_cost( floatval( $response['cost']['current_request_usd'] ) );
            $response['cost']['total'] = floatval( get_option( AI_Helper_Settings::OPTION_COST_TOTAL, 0 ) );
        }

        wp_send_json_success( $response );
    }

    /**
     * Save scenario settings per user.
     */
    public static function save_scenario() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Недостаточно прав для сохранения настроек.', 'ai-helper' ) ) );
        }

        $scenario = isset( $_POST['scenario'] ) ? sanitize_text_field( wp_unslash( $_POST['scenario'] ) ) : '';
        $settings = isset( $_POST['settings'] ) ? (array) json_decode( wp_unslash( $_POST['settings'] ), true ) : array();

        AI_Helper_Settings::save_scenario_settings( $scenario, $settings );

        wp_send_json_success( array( 'message' => __( 'Настройки сценария сохранены.', 'ai-helper' ) ) );
    }

    /**
     * Save global settings.
     */
    public static function save_global() {
        check_ajax_referer( 'ai_helper_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Недостаточно прав для сохранения.', 'ai-helper' ) ) );
        }

        $settings = isset( $_POST['settings'] ) ? (array) json_decode( wp_unslash( $_POST['settings'] ), true ) : array();

        AI_Helper_Settings::save_global_settings( $settings );

        wp_send_json_success( array( 'message' => __( 'Глобальные настройки сохранены.', 'ai-helper' ) ) );
    }
}
