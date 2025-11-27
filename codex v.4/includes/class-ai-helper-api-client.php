<?php
/**
 * API client for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Helper_API_Client {
    /**
     * Send payload to API bridge.
     *
     * @param array $payload Request payload.
     * @return array
     */
    public function send_request( $payload ) {
        $endpoint = apply_filters( 'ai_helper_api_endpoint', get_option( 'ai_helper_api_endpoint' ) );

        if ( empty( $endpoint ) ) {
            return array(
                'error' => __( 'API endpoint is not configured.', 'ai-helper' ),
            );
        }

        $args     = array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 45,
        );
        $response = wp_remote_post( esc_url_raw( $endpoint ), $args );

        if ( is_wp_error( $response ) ) {
            return array(
                'error' => __( 'Связь с ИИ временно недоступна', 'ai-helper' ),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== $code || empty( $body ) ) {
            return array(
                'error' => __( 'Связь с ИИ временно недоступна', 'ai-helper' ),
            );
        }

        $decoded = json_decode( $body, true );

        if ( null === $decoded ) {
            return array(
                'error' => __( 'Некорректный ответ от API. Проверьте формат JSON.', 'ai-helper' ),
            );
        }

        return $decoded;
    }
}
