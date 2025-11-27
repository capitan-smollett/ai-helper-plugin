<?php
/**
 * Settings handling for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Helper_Settings {
    const GLOBAL_OPTION = 'ai_helper_global_settings';

    /**
     * Get scenario field definitions.
     *
     * @return array
     */
    public static function get_scenarios() {
        return array(
            'links'      => array(
                'label'  => __( 'Только Линки', 'ai-helper' ),
                'fields' => array(
                    array(
                        'id'    => 'link_count',
                        'label' => __( 'Количество ссылок', 'ai-helper' ),
                        'type'  => 'number',
                        'min'   => 1,
                        'max'   => 10,
                        'step'  => 1,
                        'default' => 4,
                    ),
                    array(
                        'id'    => 'exclude_categories',
                        'label' => __( 'Исключить категории', 'ai-helper' ),
                        'type'  => 'text',
                        'placeholder' => __( 'slug1, slug2', 'ai-helper' ),
                    ),
                    array(
                        'id'    => 'anchor_style',
                        'label' => __( 'Правила анкоров', 'ai-helper' ),
                        'type'  => 'textarea',
                    ),
                ),
            ),
            'seo_text'   => array(
                'label'  => __( 'Только SEO-обработка текста', 'ai-helper' ),
                'fields' => array(
                    array(
                        'id'    => 'target_words',
                        'label' => __( 'Целевой объём (слов)', 'ai-helper' ),
                        'type'  => 'number',
                        'min'   => 300,
                        'max'   => 2000,
                        'step'  => 50,
                        'default' => 600,
                    ),
                    array(
                        'id'    => 'readability',
                        'label' => __( 'Читабельность', 'ai-helper' ),
                        'type'  => 'select',
                        'options' => array(
                            'basic'   => __( 'Базовая', 'ai-helper' ),
                            'neutral' => __( 'Нейтральная', 'ai-helper' ),
                            'expert'  => __( 'Экспертная', 'ai-helper' ),
                        ),
                        'default' => 'neutral',
                    ),
                    array(
                        'id'    => 'geo_context',
                        'label' => __( 'Добавить геоконтекст', 'ai-helper' ),
                        'type'  => 'checkbox',
                        'default' => true,
                    ),
                ),
            ),
            'seo_links'  => array(
                'label'  => __( 'SEO + Линки', 'ai-helper' ),
                'fields' => array(
                    array(
                        'id'    => 'link_count',
                        'label' => __( 'Количество ссылок', 'ai-helper' ),
                        'type'  => 'number',
                        'min'   => 1,
                        'max'   => 10,
                        'step'  => 1,
                        'default' => 4,
                    ),
                    array(
                        'id'    => 'target_words',
                        'label' => __( 'Целевой объём (слов)', 'ai-helper' ),
                        'type'  => 'number',
                        'min'   => 400,
                        'max'   => 2000,
                        'step'  => 50,
                        'default' => 800,
                    ),
                    array(
                        'id'    => 'structure',
                        'label' => __( 'Структура блока', 'ai-helper' ),
                        'type'  => 'textarea',
                    ),
                ),
            ),
            'guide'      => array(
                'label'  => __( 'Создать Гайд (Full Guide Mode)', 'ai-helper' ),
                'fields' => array(
                    array(
                        'id'    => 'include_faq',
                        'label' => __( 'Добавить FAQ', 'ai-helper' ),
                        'type'  => 'checkbox',
                        'default' => true,
                    ),
                    array(
                        'id'    => 'include_checklist',
                        'label' => __( 'Добавить чек-лист', 'ai-helper' ),
                        'type'  => 'checkbox',
                        'default' => true,
                    ),
                    array(
                        'id'    => 'desired_length',
                        'label' => __( 'Желаемый объём (слов)', 'ai-helper' ),
                        'type'  => 'select',
                        'options' => array(
                            'short'  => __( '800-950', 'ai-helper' ),
                            'medium' => __( '950-1150', 'ai-helper' ),
                            'long'   => __( '1150-1300', 'ai-helper' ),
                        ),
                        'default' => 'medium',
                    ),
                ),
            ),
            'rank_math'  => array(
                'label'  => __( 'Только SEO-поля Rank Math', 'ai-helper' ),
                'fields' => array(
                    array(
                        'id'    => 'focus_keyword',
                        'label' => __( 'Фокусный ключ', 'ai-helper' ),
                        'type'  => 'text',
                    ),
                    array(
                        'id'    => 'secondary_keywords',
                        'label' => __( 'Второстепенные ключи', 'ai-helper' ),
                        'type'  => 'textarea',
                    ),
                    array(
                        'id'    => 'tone',
                        'label' => __( 'Тон заголовка', 'ai-helper' ),
                        'type'  => 'select',
                        'options' => array(
                            'neutral' => __( 'Нейтральный', 'ai-helper' ),
                            'bold'    => __( 'Смелый', 'ai-helper' ),
                            'friendly'=> __( 'Дружелюбный', 'ai-helper' ),
                        ),
                        'default' => 'neutral',
                    ),
                ),
            ),
        );
    }

    /**
     * Get saved scenario settings for current user.
     *
     * @param string $scenario Scenario key.
     * @return array
     */
    public static function get_user_scenario_settings( $scenario ) {
        $user_id = get_current_user_id();
        $stored  = get_user_meta( $user_id, 'ai_helper_settings_' . $scenario, true );

        return is_array( $stored ) ? $stored : array();
    }

    /**
     * Save scenario settings for current user.
     *
     * @param string $scenario Scenario key.
     * @param array  $settings Settings.
     * @return void
     */
    public static function save_user_scenario_settings( $scenario, $settings ) {
        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'ai_helper_settings_' . $scenario, $settings );
    }

    /**
     * Get global settings.
     *
     * @return array
     */
    public static function get_global_settings() {
        $defaults = array(
            'global_seo'    => '',
            'style_guide'   => '',
            'temperature'   => 0.8,
            'material_type' => 'news',
            'language'      => 'ru-RU',
        );

        $stored = get_option( self::GLOBAL_OPTION );

        if ( ! is_array( $stored ) ) {
            return $defaults;
        }

        return wp_parse_args( $stored, $defaults );
    }

    /**
     * Save global settings.
     *
     * @param array $settings Settings.
     * @return void
     */
    public static function save_global_settings( $settings ) {
        update_option( self::GLOBAL_OPTION, $settings );
    }
}
