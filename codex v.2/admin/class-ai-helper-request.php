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
 * Handle AJAX processing and saving.
 */
class AI_Helper_Request {
/**
 * Constructor.
 */
public function __construct() {
add_action( 'wp_ajax_ai_helper_process', array( $this, 'process_request' ) );
add_action( 'wp_ajax_ai_helper_save_scenario', array( $this, 'save_scenario_settings' ) );
add_action( 'wp_ajax_ai_helper_parse_url', array( $this, 'parse_url' ) );
}

/**
 * Build and send API request.
 */
public function process_request() {
if ( ! current_user_can( 'edit_posts' ) ) {
wp_send_json_error( array( 'message' => __( 'Недостаточно прав для выполнения запроса.', 'ai-helper' ) ), 403 );
}

check_ajax_referer( 'ai_helper_nonce', 'nonce' );

$scenario = sanitize_text_field( wp_unslash( $_POST['scenario'] ?? '' ) );
$text     = AI_Helper_Utils::clean_textarea( $_POST['text'] ?? '' );
$url      = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

$source_type = AI_Helper_Utils::is_url( $text ) ? 'url' : 'text';
if ( $url && AI_Helper_Utils::is_url( $url ) ) {
$source_type = 'url';
}

if ( empty( $text ) && empty( $url ) ) {
wp_send_json_error( array( 'message' => __( 'Поле ввода пустое.', 'ai-helper' ) ) );
}

$payload_text = 'url' === $source_type ? '' : $text;

if ( 'text' === $source_type && ! AI_Helper_Utils::is_text_within_limit( $payload_text ) ) {
wp_send_json_error( array( 'message' => __( 'Текст превышает лимит 20 000 символов.', 'ai-helper' ) ) );
}

$post_data = array();
if ( 'url' === $source_type ) {
$post_data = AI_Helper_Utils::collect_post_data_from_url( $url ? $url : $text );
if ( is_wp_error( $post_data ) ) {
wp_send_json_error( array( 'message' => $post_data->get_error_message() ) );
}
$payload_text = $post_data['post_content'] ?? '';
}

$settings        = AI_Helper_Utils::sanitize_array( $_POST['settings'] ?? array() );
$global_settings = AI_Helper_Settings::get_global_settings();
if ( isset( $_POST['global_settings'] ) && is_array( $_POST['global_settings'] ) ) {
$global_settings = AI_Helper_Settings::sanitize_global_settings( $_POST['global_settings'] );
}

$request_body = array(
'scenario'     => $scenario,
'source_type'  => $source_type,
'text'         => $payload_text,
'url'          => 'url' === $source_type ? ( $url ?: $text ) : '',
'settings'     => $settings,
'post'         => $post_data,
'global_seo'   => $global_settings['global_seo_goal'],
'style_guide'  => $global_settings['style_guide'],
'material_type'=> $global_settings['material_type'],
'language'     => $global_settings['language'],
'temperature'  => $global_settings['temperature'],
);

$client   = new AI_Helper_API_Client();
$response = $client->send_request( $request_body );

if ( is_wp_error( $response ) ) {
$error_message = __( 'Связь с ИИ временно недоступна', 'ai-helper' );
if ( 'ai_helper_bad_json' === $response->get_error_code() || 'ai_helper_missing_endpoint' === $response->get_error_code() ) {
$error_message = $response->get_error_message();
}

wp_send_json_error( array( 'message' => $error_message ) );
}

$current_cost = 0;
if ( isset( $response['cost']['current_request_usd'] ) ) {
$current_cost = floatval( $response['cost']['current_request_usd'] );
}

$total_cost = floatval( get_option( 'ai_helper_cost_total', 0 ) );
$total_cost += $current_cost;
update_option( 'ai_helper_cost_total', $total_cost );

wp_send_json_success(
array(
'payload'         => $request_body,
'cost'            => array(
'current' => $current_cost,
'total'   => $total_cost,
),
'links'           => $response['links'] ?? array(),
'blocks'          => $response['blocks'] ?? array(),
'token_usage'     => $response['token_usage'] ?? array(),
'result_text'     => $response['result_text'] ?? '',
'result_html'     => isset( $response['result_html'] ) ? wp_kses_post( $response['result_html'] ) : '',
'raw_response'    => $response,
)
);
}

/**
 * Save scenario settings to user meta.
 */
public function save_scenario_settings() {
if ( ! current_user_can( 'edit_posts' ) ) {
wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'ai-helper' ) ), 403 );
}

check_ajax_referer( 'ai_helper_nonce', 'nonce' );

$scenario = sanitize_text_field( wp_unslash( $_POST['scenario'] ?? '' ) );
$settings = AI_Helper_Utils::sanitize_array( $_POST['settings'] ?? array() );

AI_Helper_Settings::save_scenario_settings( get_current_user_id(), $scenario, $settings );

wp_send_json_success( array( 'message' => __( 'Настройки сценария сохранены.', 'ai-helper' ) ) );
}

/**
 * Parse URL and return post data.
 */
public function parse_url() {
if ( ! current_user_can( 'edit_posts' ) ) {
wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'ai-helper' ) ), 403 );
}

check_ajax_referer( 'ai_helper_nonce', 'nonce' );

$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

if ( empty( $url ) || ! AI_Helper_Utils::is_url( $url ) ) {
wp_send_json_error( array( 'message' => __( 'Укажите корректный URL.', 'ai-helper' ) ) );
}

$post_data = AI_Helper_Utils::collect_post_data_from_url( $url );

if ( is_wp_error( $post_data ) ) {
wp_send_json_error( array( 'message' => $post_data->get_error_message() ) );
}

wp_send_json_success( $post_data );
}
}
