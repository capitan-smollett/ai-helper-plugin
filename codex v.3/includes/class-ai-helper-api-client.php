<?php
/**
 * API client for AI Helper plugin.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * API client encapsulation.
 */
class AI_Helper_API_Client {
    const OPTION_ENDPOINT = 'ai_helper_api_endpoint';

    /**
     * Send request payload to API bridge.
     *
     * @param array $payload Request body.
     * @return array
     */
    public function send_request( $payload ) {
        $endpoint = trim( get_option( self::OPTION_ENDPOINT, '' ) );

        if ( empty( $endpoint ) ) {
            return array(
                'error' => __( 'API endpoint is not configured. Please set it in plugin settings.', 'ai-helper' ),
            );
        }

        $response = wp_remote_post(
            esc_url_raw( $endpoint ),
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'error' => __( 'Связь с ИИ временно недоступна', 'ai-helper' ),
            );
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            return array(
                'error' => __( 'Пустой ответ от API', 'ai-helper' ),
            );
        }

        $decoded = json_decode( $body, true );

        if ( null === $decoded ) {
            return array(
                'error' => __( 'Некорректный JSON ответ от API', 'ai-helper' ),
            );
        }

        return $decoded;
    }
}
