<?php
/**
 * API client for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles outbound API calls.
 */
class AI_Helper_API_Client {
    /**
     * Send payload to API bridge.
     *
     * @param array $payload Request payload.
     *
     * @return array|WP_Error
     */
    public function send_request( $payload ) {
        $endpoint = apply_filters( 'ai_helper_api_endpoint', get_option( 'ai_helper_api_endpoint', '' ) );
        $api_key  = apply_filters( 'ai_helper_api_key', get_option( 'ai_helper_api_key', '' ) );

        if ( empty( $endpoint ) ) {
            return new WP_Error( 'ai_helper_missing_endpoint', __( 'API endpoint is not configured yet.', 'ai-helper' ) );
        }

        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => $api_key ? 'Bearer ' . $api_key : '',
            ),
            'body'    => wp_json_encode( $payload ),
            'method'  => 'POST',
            'timeout' => 30,
        );

        $response = wp_remote_request( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code >= 400 ) {
            return new WP_Error( 'ai_helper_api_error', __( 'API bridge returned an error.', 'ai-helper' ), array( 'status_code' => $code ) );
        }

        $decoded = json_decode( $body, true );

        if ( null === $decoded ) {
            return new WP_Error( 'ai_helper_bad_json', __( 'Неверный формат ответа от API.', 'ai-helper' ) );
        }

        return $decoded;
    }
}
