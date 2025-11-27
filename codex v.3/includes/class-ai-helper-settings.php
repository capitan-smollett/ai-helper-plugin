<?php
/**
 * Settings handler for AI Helper plugin.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manage plugin settings.
 */
class AI_Helper_Settings {
    const OPTION_GLOBAL_SETTINGS = 'ai_helper_global_settings';
    const OPTION_COST_TOTAL      = 'ai_helper_cost_total';

    /**
     * Get global settings.
     *
     * @return array
     */
    public static function get_global_settings() {
        $defaults = array(
            'global_seo'    => '',
            'style_guide'   => '',
            'temperature'   => 0.7,
            'material_type' => 'news',
            'language'      => 'ru-RU',
        );

        $settings = get_option( self::OPTION_GLOBAL_SETTINGS, array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Save global settings.
     *
     * @param array $settings Settings payload.
     * @return void
     */
    public static function save_global_settings( $settings ) {
        $sanitized = array(
            'global_seo'    => sanitize_textarea_field( $settings['global_seo'] ?? '' ),
            'style_guide'   => sanitize_textarea_field( $settings['style_guide'] ?? '' ),
            'temperature'   => floatval( $settings['temperature'] ?? 0 ),
            'material_type' => sanitize_text_field( $settings['material_type'] ?? 'news' ),
            'language'      => sanitize_text_field( $settings['language'] ?? 'ru-RU' ),
        );

        update_option( self::OPTION_GLOBAL_SETTINGS, $sanitized );
    }

    /**
     * Get scenario settings for user.
     *
     * @param string $scenario Scenario slug.
     * @return array
     */
    public static function get_scenario_settings( $scenario ) {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return array();
        }

        $stored = get_user_meta( $user_id, self::user_meta_key( $scenario ), true );

        return is_array( $stored ) ? $stored : array();
    }

    /**
     * Save scenario settings per user.
     *
     * @param string $scenario Scenario slug.
     * @param array  $settings Scenario settings.
     * @return void
     */
    public static function save_scenario_settings( $scenario, $settings ) {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        $allowed_scenarios = self::get_available_scenarios();

        if ( ! array_key_exists( $scenario, $allowed_scenarios ) ) {
            return;
        }

        update_user_meta( $user_id, self::user_meta_key( $scenario ), self::sanitize_settings( $settings ) );
    }

    /**
     * Allowed scenarios mapping.
     *
     * @return array
     */
    public static function get_available_scenarios() {
        return array(
            'links'        => __( 'Только Линки', 'ai-helper' ),
            'seo_text'     => __( 'Только SEO‑обработка текста', 'ai-helper' ),
            'seo_links'    => __( 'SEO + Линки', 'ai-helper' ),
            'full_guide'   => __( 'Создать Гайд (Full Guide Mode)', 'ai-helper' ),
            'rank_math'    => __( 'Только SEO‑поля Rank Math', 'ai-helper' ),
        );
    }

    /**
     * User meta key.
     *
     * @param string $scenario Scenario slug.
     * @return string
     */
    protected static function user_meta_key( $scenario ) {
        return 'ai_helper_settings_' . $scenario;
    }

    /**
     * Sanitize scenario settings.
     *
     * @param array $settings Raw settings.
     * @return array
     */
    protected static function sanitize_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return array();
        }

        $clean = array();

        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) ) {
                $clean[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', $value );
            } else {
                $clean[ sanitize_key( $key ) ] = is_numeric( $value ) ? floatval( $value ) : sanitize_text_field( $value );
            }
        }

        return $clean;
    }

    /**
     * Increase total cost option.
     *
     * @param float $cost Cost value.
     * @return void
     */
    public static function add_to_total_cost( $cost ) {
        $total = floatval( get_option( self::OPTION_COST_TOTAL, 0 ) );
        $total = $total + floatval( $cost );

        update_option( self::OPTION_COST_TOTAL, $total );
    }
}
