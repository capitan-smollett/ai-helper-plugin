<?php
/**
 * Settings handler.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Manage plugin settings and defaults.
 */
class AI_Helper_Settings {
/**
 * Get global settings.
 *
 * @return array
 */
public static function get_global_settings() {
$defaults = array(
'global_seo_goal' => '',
'style_guide'    => '',
'temperature'    => '0.7',
'material_type'  => 'news',
'language'       => 'ru-RU',
);

$saved = get_option( 'ai_helper_global_settings', array() );

return wp_parse_args( $saved, $defaults );
}

/**
 * Save global settings.
 *
 * @param array $data Data from request.
 *
 * @return void
 */
public static function save_global_settings( $data ) {
$settings = self::sanitize_global_settings( $data );

update_option( 'ai_helper_global_settings', $settings );
}

/**
 * Sanitize global settings from array.
 *
 * @param array $data Raw data.
 *
 * @return array
 */
public static function sanitize_global_settings( $data ) {
return array(
'global_seo_goal' => AI_Helper_Utils::clean_textarea( $data['global_seo_goal'] ?? '' ),
'style_guide'    => AI_Helper_Utils::clean_textarea( $data['style_guide'] ?? '' ),
'temperature'    => isset( $data['temperature'] ) ? floatval( $data['temperature'] ) : 0.7,
'material_type'  => sanitize_text_field( $data['material_type'] ?? 'news' ),
'language'       => sanitize_text_field( $data['language'] ?? 'ru-RU' ),
);
}

/**
 * Default scenario settings.
 *
 * @param string $scenario Scenario key.
 *
 * @return array
 */
public static function get_default_scenario_settings( $scenario ) {
$defaults = array(
'links_only'   => array(
'link_count'        => 4,
'exclude_categories' => '',
'anchor_strategy'    => '',
),
'seo_text'     => array(
'target_length'     => 600,
'readability_focus' => 'on',
'rank_math_keyword' => '',
'geo_context'       => '',
),
'seo_links'    => array(
'target_length'     => 750,
'link_count'        => 4,
'exclude_categories' => '',
'geo_context'       => '',
),
'full_guide'   => array(
'word_range'        => '800-1300',
'include_checklist' => 'on',
'faq_items'         => 4,
),
'rank_math'    => array(
'focus_keyword' => '',
'audience'      => '',
),
);

return $defaults[ $scenario ] ?? array();
}

/**
 * Get settings for user and scenario.
 *
 * @param int    $user_id  User ID.
 * @param string $scenario Scenario.
 *
 * @return array
 */
public static function get_scenario_settings( $user_id, $scenario ) {
$saved    = get_user_meta( $user_id, 'ai_helper_settings_' . $scenario, true );
$defaults = self::get_default_scenario_settings( $scenario );

return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * Save scenario settings.
 *
 * @param int    $user_id  User ID.
 * @param string $scenario Scenario key.
 * @param array  $settings Settings array.
 *
 * @return void
 */
public static function save_scenario_settings( $user_id, $scenario, $settings ) {
$clean_settings = AI_Helper_Utils::sanitize_array( $settings );
update_user_meta( $user_id, 'ai_helper_settings_' . $scenario, $clean_settings );
}

/**
 * Scenario labels.
 *
 * @return array
 */
public static function get_scenarios() {
return array(
'links_only' => __( 'Только Линки', 'ai-helper' ),
'seo_text'   => __( 'Только SEO‑обработка текста', 'ai-helper' ),
'seo_links'  => __( 'SEO + Линки', 'ai-helper' ),
'full_guide' => __( 'Создать Гайд', 'ai-helper' ),
'rank_math'  => __( 'Только SEO‑поля Rank Math', 'ai-helper' ),
);
}
}
