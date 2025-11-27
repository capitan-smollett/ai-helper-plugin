<?php
/**
 * Mock API Client (реальный — будет заменён на endpoint + auth)
 * Для ТЗ: возвращает фиксированный ответ с cost=0.0123
 */

class AI_Helper_API_Client {

	// Замените на реальный URL
	const API_ENDPOINT = 'https://your-api-mock.local/process';
	const API_KEY      = 'mock-key-for-dev';

	public static function call( $data ) {
		// === ВРЕМЕННЫЙ MOCK ===
		// Удалите этот блок и замените на настоящий HTTP-запрос для продакшена
		return [
			'result_text' => "Это обработанный текст. Он был улучшен ИИ согласно вашим настройкам.",
			'result_html' => "<p>Это <strong>обработанный текст</strong>. Он был <em>улучшен ИИ</em> согласно вашим настройкам.</p>",
			'links'       => [
				'<a href="/post-1/">Как выбрать увлажнитель</a>',
				'<a href="/post-2/">ТОП-5 моделей 2025</a>',
				'<a href="/post-3/">Уход за прибором</a>',
				'<a href="/post-4/">Отзывы врачей</a>',
			],
			'blocks'      => [
				'faq'  => '<h3>Частые вопросы</h3><ul><li><strong>Вреден ли увлажнитель?</strong> Нет, при правильном использовании.</li></ul>',
				'cta'  => '<div class="cta-box"><h4>Готовы к покупке?</h4><a href="/catalog/" class="button">Перейти в каталог</a></div>',
			],
			'token_usage' => [
				'prompt'     => 1234,
				'completion' => 912,
			],
			'cost'        => [
				'current_request_usd' => 0.0123,
			],
			'error'       => null,
		];
		// === КОНЕЦ MOCK ===


		/*
		// Реальная реализация (раскомментировать в проде):
		$response = wp_remote_post( self::API_ENDPOINT, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . self::API_KEY,
			],
			'body'    => wp_json_encode( $data ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Invalid JSON response' );
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$msg = $decoded['error'] ?? 'API returned error';
			return new WP_Error( 'api_http_error', $msg );
		}

		return $decoded;
		*/
	}
}
