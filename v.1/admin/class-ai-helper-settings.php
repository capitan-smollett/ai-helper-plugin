<?php
/**
 * Settings handler for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages plugin settings storage.
 */
class AI_Helper_Settings {
    /**
     * Return stored global settings.
     *
     * @return array
     */
    public static function get_global_settings() {
        $defaults = array(
            'global_seo'    => '',
            'style_guide'   => '',
            'temperature'   => 1.0,
            'material_type' => 'news',
            'language'      => 'ru-RU',
        );

        $stored = get_option( 'ai_helper_global_settings', array() );

        return wp_parse_args( $stored, $defaults );
    }

    /**
     * Persist global settings option.
     *
     * @param array $settings Settings payload.
     *
     * @return void
     */
    public static function save_global_settings( $settings ) {
        $clean = array(
            'global_seo'    => wp_kses_post( $settings['global_seo'] ?? '' ),
            'style_guide'   => wp_kses_post( $settings['style_guide'] ?? '' ),
            'temperature'   => isset( $settings['temperature'] ) ? floatval( $settings['temperature'] ) : 1.0,
            'material_type' => sanitize_text_field( $settings['material_type'] ?? 'news' ),
            'language'      => sanitize_text_field( $settings['language'] ?? 'ru-RU' ),
        );

        update_option( 'ai_helper_global_settings', $clean );
    }

    /**
     * Fetch stored scenario settings for a user.
     *
     * @param int    $user_id  User ID.
     * @param string $scenario Scenario key.
     *
     * @return array
     */
    public static function get_scenario_settings( $user_id, $scenario ) {
        $key      = 'ai_helper_settings_' . sanitize_key( $scenario );
        $settings = get_user_meta( $user_id, $key, true );

        return is_array( $settings ) ? $settings : array();
    }

    /**
     * Save scenario settings for a user.
     *
     * @param int    $user_id  User ID.
     * @param string $scenario Scenario key.
     * @param array  $settings Settings array.
     *
     * @return void
     */
    public static function save_scenario_settings( $user_id, $scenario, $settings ) {
        $key = 'ai_helper_settings_' . sanitize_key( $scenario );

        $clean = array_map( 'wp_kses_post', $settings );

        update_user_meta( $user_id, $key, $clean );
    }

    /**
     * Get total cost value.
     *
     * @return float
     */
    public static function get_total_cost() {
        return (float) get_option( 'ai_helper_cost_total', 0 );
    }

    /**
     * Increment total cost.
     *
     * @param float $amount Additional amount.
     *
     * @return float New total.
     */
    public static function add_total_cost( $amount ) {
        $current = self::get_total_cost();
        $current += (float) $amount;
        update_option( 'ai_helper_cost_total', $current );

        return $current;
    }
}
