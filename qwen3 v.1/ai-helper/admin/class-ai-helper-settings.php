<?php
/**
 * Settings handler (global + per-scenario)
 */

class AI_Helper_Settings {

	public function init() {
		add_action( 'wp_ajax_ai_helper_save_global', [ $this, 'save_global' ] );
		add_action( 'wp_ajax_ai_helper_save_scenario', [ $this, 'save_scenario' ] );
		add_action( 'wp_ajax_ai_helper_get_costs', [ $this, 'get_costs' ] );
	}

	public function save_global() {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_helper_process' ) ) {
			wp_die( 'Invalid nonce', 403 );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Access denied', 403 );
		}

		$settings = [
			'seo_goal'     => sanitize_text_field( wp_unslash( $_POST['seo_goal'] ?? '' ) ),
			'style_guide'  => sanitize_textarea_field( wp_unslash( $_POST['style_guide'] ?? '' ) ),
			'temperature'  => floatval( $_POST['temperature'] ?? 0.7 ),
			'material_type' => sanitize_key( $_POST['material_type'] ?? 'guide' ),
			'language'     => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'ru-RU' ) ),
		];

		update_option( 'ai_helper_global_settings', $settings );
		wp_send_json_success();
	}

	public function save_scenario() {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_helper_process' ) ) {
			wp_die( 'Invalid nonce', 403 );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Access denied', 403 );
		}

		$scenario = sanitize_key( $_POST['scenario'] ?? '' );
		$allowed  = [ 'links', 'seo', 'seo_links', 'guide', 'rank_math' ];
		if ( ! in_array( $scenario, $allowed, true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid scenario' ] );
		}

		// Фильтрация полей по сценарию (защита от инъекций)
		$raw_settings = $_POST['settings'] ?? [];
		$meta_key     = 'ai_helper_scenario_' . $scenario;

		// Простая санитизация — можно расширить
		$clean = array_map( 'sanitize_text_field', $raw_settings );

		update_user_meta( get_current_user_id(), $meta_key, $clean );
		wp_send_json_success();
	}

	public function get_costs() {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ai_helper_process' ) ) {
			wp_die( 'Invalid nonce', 403 );
		}

		$total = floatval( get_option( 'ai_helper_cost_total', 0 ) );
		$session = isset( $_SESSION['ai_helper_session_cost'] ) ? floatval( $_SESSION['ai_helper_session_cost'] ) : 0.0;

		wp_send_json_success( [
			'total'   => $total,
			'session' => $session,
		] );
	}
}
