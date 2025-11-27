<?php
/**
 * Admin UI for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Helper_Admin_UI {
    /**
     * Singleton instance.
     *
     * @var AI_Helper_Admin_UI
     */
    private static $instance;

    /**
     * Menu position under Posts.
     *
     * @var int
     */
    private $menu_position = 6;

    /**
     * Get instance.
     *
     * @return AI_Helper_Admin_UI
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register hooks.
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register menu item.
     */
    public function register_menu() {
        $position = $this->get_menu_position();
        add_menu_page(
            __( 'ИИ-Помощник', 'ai-helper' ),
            __( 'ИИ-Помощник', 'ai-helper' ),
            'edit_posts',
            'ai-helper',
            array( $this, 'render_page' ),
            'dashicons-admin-site',
            $position
        );
    }

    /**
     * Determine menu position directly under Posts.
     *
     * @return int
     */
    private function get_menu_position() {
        global $menu;

        $position = $this->menu_position;

        if ( empty( $menu ) || ! is_array( $menu ) ) {
            return $position;
        }

        while ( isset( $menu[ $position ] ) ) {
            $position++;
        }

        return $position;
    }

    /**
     * Enqueue assets.
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ai-helper' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'ai-helper-admin', AI_HELPER_URL . 'assets/admin.css', array(), '1.0.0' );
        wp_enqueue_script( 'ai-helper-admin', AI_HELPER_URL . 'assets/admin.js', array( 'jquery' ), '1.0.0', true );

        $scenarios       = AI_Helper_Settings::get_scenarios();
        $scenario_values = array();

        foreach ( $scenarios as $key => $scenario ) {
            $scenario_values[ $key ] = AI_Helper_Settings::get_user_scenario_settings( $key );
        }

        wp_localize_script(
            'ai-helper-admin',
            'AiHelperData',
            array(
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'ai_helper_nonce' ),
                'scenarios'       => $scenarios,
                'scenarioValues'  => $scenario_values,
                'globalSettings'  => AI_Helper_Settings::get_global_settings(),
                'costTotals'      => array(
                    'session' => 0,
                    'total'   => (float) get_option( 'ai_helper_cost_total', 0 ),
                ),
                'strings'         => array(
                    'processing'      => __( 'Обрабатываю…', 'ai-helper' ),
                    'process'         => __( 'Обработать', 'ai-helper' ),
                    'save_settings'   => __( 'Настройки сохранены', 'ai-helper' ),
                    'request_failed'  => __( 'Связь с ИИ временно недоступна', 'ai-helper' ),
                    'invalid_json'    => __( 'Некорректный ответ от API.', 'ai-helper' ),
                ),
            )
        );
    }

    /**
     * Render admin page.
     */
    public function render_page() {
        $global_settings = AI_Helper_Settings::get_global_settings();
        $scenarios       = AI_Helper_Settings::get_scenarios();
        ?>
        <div class="wrap ai-helper">
            <h1><?php esc_html_e( 'ИИ-Помощник', 'ai-helper' ); ?></h1>
            <div class="ai-helper-top">
                <div class="ai-helper-input">
                    <label for="ai-helper-source" class="screen-reader-text"><?php esc_html_e( 'Исходный текст или URL', 'ai-helper' ); ?></label>
                    <textarea id="ai-helper-source" class="widefat" rows="8" placeholder="<?php esc_attr_e( 'Вставьте текст или URL статьи…', 'ai-helper' ); ?>"></textarea>
                </div>
                <div class="ai-helper-controls">
                    <div class="ai-helper-modes">
                        <button class="button mode-toggle" data-scenario="links"><?php esc_html_e( 'Только Линки', 'ai-helper' ); ?></button>
                        <button class="button mode-toggle" data-scenario="seo_text"><?php esc_html_e( 'Только текст (SEO)', 'ai-helper' ); ?></button>
                        <button class="button mode-toggle" data-scenario="seo_links"><?php esc_html_e( 'Текст + Линки', 'ai-helper' ); ?></button>
                    </div>
                    <div class="ai-helper-costs">
                        <span class="cost current"><?php esc_html_e( 'Текущий запрос: $', 'ai-helper' ); ?><strong id="ai-helper-cost-current">0.00</strong></span>
                        <span class="cost session"><?php esc_html_e( 'За текущий сеанс: $', 'ai-helper' ); ?><strong id="ai-helper-cost-session">0.00</strong></span>
                        <span class="cost total"><?php esc_html_e( 'Всего: $', 'ai-helper' ); ?><strong id="ai-helper-cost-total">0.00</strong></span>
                    </div>
                    <div class="ai-helper-scenarios">
                        <label for="ai-helper-scenario" class="screen-reader-text"><?php esc_html_e( 'Выбор сценария', 'ai-helper' ); ?></label>
                        <select id="ai-helper-scenario">
                            <?php foreach ( $scenarios as $key => $scenario ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $scenario['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="ai-helper-scenario-settings" class="ai-helper-settings"></div>
                </div>
            </div>
            <div class="ai-helper-actions">
                <button id="ai-helper-process" class="button button-primary" disabled><?php esc_html_e( 'Обработать', 'ai-helper' ); ?></button>
                <span id="ai-helper-status" class="status"></span>
            </div>
            <div class="ai-helper-result">
                <div class="ai-helper-result-header">
                    <div class="ai-helper-tabs">
                        <button class="tab active" data-tab="view"><?php esc_html_e( 'Просмотр', 'ai-helper' ); ?></button>
                        <button class="tab" data-tab="html"><?php esc_html_e( 'HTML', 'ai-helper' ); ?></button>
                    </div>
                    <div class="ai-helper-result-actions">
                        <button class="button" id="ai-helper-copy-text"><?php esc_html_e( 'Копировать текст', 'ai-helper' ); ?></button>
                        <button class="button" id="ai-helper-copy-html"><?php esc_html_e( 'Копировать HTML', 'ai-helper' ); ?></button>
                        <button class="button" id="ai-helper-repeat"><?php esc_html_e( 'Повторить обработку', 'ai-helper' ); ?></button>
                        <button class="button" id="ai-helper-save-settings"><?php esc_html_e( 'Сохранить настройки сценария', 'ai-helper' ); ?></button>
                    </div>
                </div>
                <div class="ai-helper-result-body">
                    <div class="tab-content active" id="ai-helper-result-view"></div>
                    <pre class="tab-content" id="ai-helper-result-html"></pre>
                </div>
            </div>
            <div class="ai-helper-global">
                <h2><?php esc_html_e( 'Глобальные настройки', 'ai-helper' ); ?></h2>
                <div class="ai-helper-grid">
                    <div>
                        <label for="ai-helper-global-seo"><?php esc_html_e( 'Глобальная SEO-цель', 'ai-helper' ); ?></label>
                        <textarea id="ai-helper-global-seo" rows="3" class="widefat"><?php echo esc_textarea( $global_settings['global_seo'] ); ?></textarea>
                    </div>
                    <div>
                        <label for="ai-helper-style-guide"><?php esc_html_e( 'Стайл-гайд', 'ai-helper' ); ?></label>
                        <textarea id="ai-helper-style-guide" rows="3" class="widefat"><?php echo esc_textarea( $global_settings['style_guide'] ); ?></textarea>
                    </div>
                    <div class="ai-helper-inline">
                        <label for="ai-helper-temperature"><?php esc_html_e( 'Температура', 'ai-helper' ); ?></label>
                        <input type="number" id="ai-helper-temperature" min="0" max="2" step="0.1" value="<?php echo esc_attr( $global_settings['temperature'] ); ?>" />
                    </div>
                    <div class="ai-helper-inline">
                        <label for="ai-helper-material-type"><?php esc_html_e( 'Тип материала', 'ai-helper' ); ?></label>
                        <select id="ai-helper-material-type">
                            <option value="news" <?php selected( $global_settings['material_type'], 'news' ); ?>><?php esc_html_e( 'Новость', 'ai-helper' ); ?></option>
                            <option value="guide" <?php selected( $global_settings['material_type'], 'guide' ); ?>><?php esc_html_e( 'Гайд', 'ai-helper' ); ?></option>
                            <option value="review" <?php selected( $global_settings['material_type'], 'review' ); ?>><?php esc_html_e( 'Обзор', 'ai-helper' ); ?></option>
                        </select>
                    </div>
                    <div class="ai-helper-inline">
                        <label for="ai-helper-language"><?php esc_html_e( 'Язык результата', 'ai-helper' ); ?></label>
                        <select id="ai-helper-language">
                            <option value="ru-RU" <?php selected( $global_settings['language'], 'ru-RU' ); ?>>ru-RU</option>
                            <option value="en-US" <?php selected( $global_settings['language'], 'en-US' ); ?>>en-US</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
