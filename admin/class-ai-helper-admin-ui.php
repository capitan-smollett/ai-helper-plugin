<?php
/**
 * Admin UI for AI Helper plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sets up admin page and assets.
 */
class AI_Helper_Admin_UI {
    /**
     * Singleton instance.
     *
     * @var AI_Helper_Admin_UI
     */
    private static $instance;

    /**
     * Retrieve instance.
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
     * Hook actions.
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register admin menu item.
     */
    public function register_menu() {
        $position = AI_Helper_Utils::get_menu_position_after_posts();
        add_menu_page(
            __( 'ИИ‑Помощник', 'ai-helper' ),
            __( 'ИИ‑Помощник', 'ai-helper' ),
            'edit_posts',
            'ai-helper',
            array( $this, 'render_page' ),
            'dashicons-admin-site-alt3',
            $position
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ai-helper' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'ai-helper-admin', AI_HELPER_PLUGIN_URL . 'assets/admin.css', array(), AI_HELPER_VERSION );
        wp_enqueue_script( 'ai-helper-admin', AI_HELPER_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), AI_HELPER_VERSION, true );

        $user_id = get_current_user_id();
        $scenarios = array( 'links_only', 'text_seo', 'seo_links', 'full_guide', 'rank_math' );
        $scenario_settings = array();
        foreach ( $scenarios as $scenario ) {
            $scenario_settings[ $scenario ] = AI_Helper_Settings::get_scenario_settings( $user_id, $scenario );
        }

        wp_localize_script(
            'ai-helper-admin',
            'AIHelperData',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'ai_helper_nonce' ),
                'globalSettings'   => AI_Helper_Settings::get_global_settings(),
                'scenarioSettings' => $scenario_settings,
                'totalCost'        => AI_Helper_Settings::get_total_cost(),
                'i18n'             => array(
                    'processing' => __( 'Обрабатываю…', 'ai-helper' ),
                    'copyText'   => __( 'Текст скопирован', 'ai-helper' ),
                    'copyHtml'   => __( 'HTML скопирован', 'ai-helper' ),
                    'noRequest'  => __( 'Нет последнего запроса', 'ai-helper' ),
                ),
            )
        );
    }

    /**
     * Render main admin page.
     */
    public function render_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Недостаточно прав.', 'ai-helper' ) );
        }

        $global = AI_Helper_Settings::get_global_settings();
        ?>
        <div class="wrap ai-helper-wrap">
            <h1><?php echo esc_html__( 'ИИ‑Помощник', 'ai-helper' ); ?></h1>

            <div class="ai-helper-top">
                <div class="ai-helper-input-block">
                    <label for="ai-helper-source" class="screen-reader-text"><?php esc_html_e( 'Вставьте текст или URL статьи…', 'ai-helper' ); ?></label>
                    <textarea id="ai-helper-source" class="widefat" rows="6" placeholder="<?php echo esc_attr__( 'Вставьте текст или URL статьи…', 'ai-helper' ); ?>"></textarea>
                    <div class="ai-helper-cost-line">
                        <span><?php esc_html_e( 'Текущий запрос: $', 'ai-helper' ); ?><span id="ai-helper-cost-current">0.00</span></span>
                        <span><?php esc_html_e( 'За текущий сеанс: $', 'ai-helper' ); ?><span id="ai-helper-cost-session">0.00</span></span>
                        <span><?php esc_html_e( 'Всего: $', 'ai-helper' ); ?><span id="ai-helper-cost-total"><?php echo esc_html( number_format_i18n( AI_Helper_Settings::get_total_cost(), 2 ) ); ?></span></span>
                    </div>
                </div>
                <div class="ai-helper-modes">
                    <div class="ai-helper-mode-buttons" role="group" aria-label="<?php esc_attr_e( 'Режимы', 'ai-helper' ); ?>">
                        <button class="button ai-helper-mode" data-scenario="links_only"><?php esc_html_e( 'Только Линки', 'ai-helper' ); ?></button>
                        <button class="button ai-helper-mode" data-scenario="text_seo"><?php esc_html_e( 'Только текст (SEO)', 'ai-helper' ); ?></button>
                        <button class="button ai-helper-mode" data-scenario="seo_links"><?php esc_html_e( 'Текст + Линки', 'ai-helper' ); ?></button>
                    </div>
                    <div class="ai-helper-scenario-select">
                        <label for="ai-helper-scenario"><?php esc_html_e( 'Сценарий', 'ai-helper' ); ?></label>
                        <select id="ai-helper-scenario" class="ai-helper-select">
                            <option value="links_only"><?php esc_html_e( 'Только Линки', 'ai-helper' ); ?></option>
                            <option value="text_seo"><?php esc_html_e( 'Только SEO-обработка текста', 'ai-helper' ); ?></option>
                            <option value="seo_links"><?php esc_html_e( 'SEO + Линки', 'ai-helper' ); ?></option>
                            <option value="full_guide"><?php esc_html_e( 'Создать Гайд (Full Guide Mode)', 'ai-helper' ); ?></option>
                            <option value="rank_math"><?php esc_html_e( 'Только SEO-поля Rank Math', 'ai-helper' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="ai-helper-scenario-settings" class="ai-helper-panel">
                <div class="ai-helper-scenario-panel" data-scenario="links_only">
                    <h2><?php esc_html_e( 'Настройки: Только Линки', 'ai-helper' ); ?></h2>
                    <label>
                        <?php esc_html_e( 'Исключить категории (через запятую)', 'ai-helper' ); ?>
                        <input type="text" name="exclude_categories" class="widefat" />
                    </label>
                    <label>
                        <?php esc_html_e( 'Предпочтительные темы', 'ai-helper' ); ?>
                        <textarea name="preferred_topics" class="widefat" rows="3"></textarea>
                    </label>
                </div>
                <div class="ai-helper-scenario-panel" data-scenario="text_seo">
                    <h2><?php esc_html_e( 'Настройки: Только SEO‑обработка текста', 'ai-helper' ); ?></h2>
                    <label>
                        <?php esc_html_e( 'Требуемая длина (слов)', 'ai-helper' ); ?>
                        <input type="number" name="target_length" class="small-text" min="100" max="2000" />
                    </label>
                    <label>
                        <?php esc_html_e( 'Дополнительные требования', 'ai-helper' ); ?>
                        <textarea name="extra_requirements" class="widefat" rows="3"></textarea>
                    </label>
                </div>
                <div class="ai-helper-scenario-panel" data-scenario="seo_links">
                    <h2><?php esc_html_e( 'Настройки: SEO + Линки', 'ai-helper' ); ?></h2>
                    <label>
                        <?php esc_html_e( 'Исключить категории', 'ai-helper' ); ?>
                        <input type="text" name="exclude_categories" class="widefat" />
                    </label>
                    <label>
                        <?php esc_html_e( 'Ключевые слова', 'ai-helper' ); ?>
                        <input type="text" name="keywords" class="widefat" />
                    </label>
                </div>
                <div class="ai-helper-scenario-panel" data-scenario="full_guide">
                    <h2><?php esc_html_e( 'Настройки: Создать Гайд', 'ai-helper' ); ?></h2>
                    <label>
                        <?php esc_html_e( 'Основные разделы гайда', 'ai-helper' ); ?>
                        <textarea name="guide_sections" class="widefat" rows="3"></textarea>
                    </label>
                    <label>
                        <?php esc_html_e( 'CTA сообщение', 'ai-helper' ); ?>
                        <input type="text" name="cta" class="widefat" />
                    </label>
                </div>
                <div class="ai-helper-scenario-panel" data-scenario="rank_math">
                    <h2><?php esc_html_e( 'Настройки: Только SEO‑поля Rank Math', 'ai-helper' ); ?></h2>
                    <label>
                        <?php esc_html_e( 'Основной ключ', 'ai-helper' ); ?>
                        <input type="text" name="focus_keyword" class="widefat" />
                    </label>
                    <label>
                        <?php esc_html_e( 'Дополнительные пожелания', 'ai-helper' ); ?>
                        <textarea name="notes" class="widefat" rows="3"></textarea>
                    </label>
                </div>
            </div>

            <div class="ai-helper-global-settings ai-helper-panel">
                <h2><?php esc_html_e( 'Глобальные настройки', 'ai-helper' ); ?></h2>
                <label>
                    <?php esc_html_e( 'Глобальная SEO‑цель', 'ai-helper' ); ?>
                    <textarea id="ai-helper-global-seo" class="widefat" rows="3"><?php echo esc_textarea( $global['global_seo'] ); ?></textarea>
                </label>
                <label>
                    <?php esc_html_e( 'Стайл‑гайд', 'ai-helper' ); ?>
                    <textarea id="ai-helper-style-guide" class="widefat" rows="3"><?php echo esc_textarea( $global['style_guide'] ); ?></textarea>
                </label>
                <label>
                    <?php esc_html_e( 'Температура', 'ai-helper' ); ?>
                    <input type="number" id="ai-helper-temperature" min="0" max="2" step="0.1" value="<?php echo esc_attr( $global['temperature'] ); ?>" />
                </label>
                <label>
                    <?php esc_html_e( 'Тип материала', 'ai-helper' ); ?>
                    <select id="ai-helper-material-type">
                        <option value="news" <?php selected( $global['material_type'], 'news' ); ?>><?php esc_html_e( 'Новость', 'ai-helper' ); ?></option>
                        <option value="guide" <?php selected( $global['material_type'], 'guide' ); ?>><?php esc_html_e( 'Гайд', 'ai-helper' ); ?></option>
                        <option value="review" <?php selected( $global['material_type'], 'review' ); ?>><?php esc_html_e( 'Обзор', 'ai-helper' ); ?></option>
                    </select>
                </label>
                <label>
                    <?php esc_html_e( 'Язык результата', 'ai-helper' ); ?>
                    <select id="ai-helper-language">
                        <option value="ru-RU" <?php selected( $global['language'], 'ru-RU' ); ?>>ru-RU</option>
                        <option value="en-US" <?php selected( $global['language'], 'en-US' ); ?>>en-US</option>
                    </select>
                </label>
                <button class="button button-secondary" id="ai-helper-save-global"><?php esc_html_e( 'Сохранить глобальные настройки', 'ai-helper' ); ?></button>
            </div>

            <div class="ai-helper-actions">
                <button class="button button-primary" id="ai-helper-process" disabled><?php esc_html_e( 'Обработать', 'ai-helper' ); ?></button>
                <span class="ai-helper-status" aria-live="polite"></span>
                <button class="button" id="ai-helper-repeat"><?php esc_html_e( 'Повторить обработку', 'ai-helper' ); ?></button>
                <button class="button" id="ai-helper-save-scenario"><?php esc_html_e( 'Сохранить настройки сценария', 'ai-helper' ); ?></button>
            </div>

            <div class="ai-helper-result ai-helper-panel">
                <div class="ai-helper-result-tabs">
                    <button class="button ai-helper-tab active" data-tab="preview"><?php esc_html_e( 'Просмотр', 'ai-helper' ); ?></button>
                    <button class="button ai-helper-tab" data-tab="html"><?php esc_html_e( 'HTML', 'ai-helper' ); ?></button>
                </div>
                <div class="ai-helper-result-body" id="ai-helper-result-preview"></div>
                <pre class="ai-helper-result-body hidden" id="ai-helper-result-html"></pre>
                <div class="ai-helper-result-actions">
                    <button class="button" id="ai-helper-copy-text"><?php esc_html_e( 'Копировать текст', 'ai-helper' ); ?></button>
                    <button class="button" id="ai-helper-copy-html"><?php esc_html_e( 'Копировать HTML', 'ai-helper' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}
