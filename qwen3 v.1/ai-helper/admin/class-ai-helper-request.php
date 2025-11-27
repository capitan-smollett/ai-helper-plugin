<?php
/**
 * AJAX request handler
 */

class AI_Helper_Request {

	public function init() {
		add_action( 'admin_init', [ $this, 'start_session' ] );
		add_action( 'wp_ajax_ai_helper_process', [ $this, 'process' ] );
	}

	public function start_session() {
		if ( ! session_id() ) {
			session_start();
		}
		if ( ! isset( $_SESSION['ai_helper_session_cost'] ) ) {
			$_SESSION['ai_helper_session_cost'] = 0.0;
		}
	}

	public function process() {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_helper_process' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token' ] );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Access denied' ] );
		}

		$input = sanitize_textarea_field( wp_unslash( $_POST['input'] ?? '' ) );
		if ( empty( $input ) ) {
			wp_send_json_error( [ 'message' => 'Input is empty' ] );
		}

		if ( strlen( $input ) > 20000 ) {
			wp_send_json_error( [ 'message' => 'Input exceeds 20,000 characters' ] );
		}

		// Определяем тип
		$source_type = 'text';
		$url         = '';
		$parsed_data = [];

		if ( filter_var( $input, FILTER_VALIDATE_URL ) && strpos( $input, home_url() ) === 0 ) {
			$source_type = 'url';
			$url         = $input;
			$post_id     = url_to_postid( $input );
			if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
				wp_send_json_error( [ 'message' => 'Post not found or not published' ] );
			}
			$parsed_data = AI_Helper_Utils::parse_post_data( $post_id );
		} else {
			$parsed_data = [
				'text' => $input,
			];
		}

		// Собираем данные запроса
		$global = get_option( 'ai_helper_global_settings', [
			'seo_goal' => '',
			'style_guide' => '',
			'temperature' => 0.7,
			'material_type' => 'guide',
			'language' => 'ru-RU',
		] );

		$scenario_key = sanitize_key( $_POST['scenario'] ?? 'seo_links' );
		$scenario_map = [
			'links'      => 'only_links',
			'seo'        => 'only_seo',
			'seo_links'  => 'seo_plus_links',
			'guide'      => 'full_guide',
			'rank_math'  => 'rank_math_fields',
		];
		$api_scenario = $scenario_map[ $scenario_key ] ?? 'seo_plus_links';

		// Загружаем настройки сценария
		$scenario_settings = get_user_meta( get_current_user_id(), 'ai_helper_scenario_' . $scenario_key, true );
		if ( ! is_array( $scenario_settings ) ) {
			$scenario_settings = [];
		}

		// Формируем запрос
		$request_data = [
			'scenario'      => $api_scenario,
			'source_type'   => $source_type,
			'text'          => $parsed_data['text'] ?? '',
			'url'           => $url,
			'settings'      => $scenario_settings,
			'global_seo'    => $global['seo_goal'],
			'style_guide'   => $global['style_guide'],
			'material_type' => $global['material_type'],
			'language'      => $global['language'],
			'temperature'   => $global['temperature'],
		];

		// Добавляем данные поста при URL
		if ( $source_type === 'url' ) {
			$request_data = array_merge( $request_data, [
				'post_id'     => $parsed_data['post_id'] ?? 0,
				'title'       => $parsed_data['title'] ?? '',
				'content'     => $parsed_data['content'] ?? '',
				'excerpt'     => $parsed_data['excerpt'] ?? '',
				'categories'  => $parsed_data['categories'] ?? [],
				'tags'        => $parsed_data['tags'] ?? [],
				'rank_math'   => $parsed_data['rank_math'] ?? [],
			] );
		}

		// Отправка
		$response = AI_Helper_API_Client::call( $request_data );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => $response->get_error_message(),
			] );
		}

		// Обновление стоимости
		$cost = floatval( $response['cost']['current_request_usd'] ?? 0.0 );
		$_SESSION['ai_helper_session_cost'] = floatval( $_SESSION['ai_helper_session_cost'] ) + $cost;
		$total = floatval( get_option( 'ai_helper_cost_total', 0 ) ) + $cost;
		update_option( 'ai_helper_cost_total', $total );

		// Возвращаем результат + стоимость
		wp_send_json_success( [
			'response' => $response,
			'cost'     => [
				'current' => $cost,
				'session' => $_SESSION['ai_helper_session_cost'],
				'total'   => $total,
			],
		] );
	}
}
