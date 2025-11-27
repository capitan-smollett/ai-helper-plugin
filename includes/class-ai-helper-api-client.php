<?php
/**
 * API client stub.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Simple API client to communicate with external bridge.
 */
class AI_Helper_API_Client {
/**
 * Send payload to configured endpoint.
 *
 * @param array $payload Request payload.
 *
 * @return array|WP_Error
 */
public function send_request( $payload ) {
$endpoint = apply_filters( 'ai_helper_api_endpoint', get_option( 'ai_helper_api_endpoint', '' ) );

if ( empty( $endpoint ) ) {
return new WP_Error( 'ai_helper_missing_endpoint', __( 'API endpoint не настроен.', 'ai-helper' ) );
}

$args     = array(
'headers' => array(
'Content-Type' => 'application/json',
),
'body'    => wp_json_encode( $payload ),
'timeout' => 30,
);
$response = wp_remote_post( esc_url_raw( $endpoint ), $args );

if ( is_wp_error( $response ) ) {
return $response;
}

$body = wp_remote_retrieve_body( $response );
$data = json_decode( $body, true );

if ( null === $data ) {
return new WP_Error( 'ai_helper_bad_json', __( 'Получен некорректный JSON от API.', 'ai-helper' ) );
}

return $data;
}
}
