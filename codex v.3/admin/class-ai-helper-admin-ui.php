<?php
/**
 * Admin UI rendering for AI Helper plugin.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin UI manager.
 */
class AI_Helper_Admin_UI {
    /**
     * Register admin menu.
     */
    public function register_menu() {
        $position = $this->find_menu_position_below_posts();

        add_menu_page(
            __( 'ИИ‑Помощник', 'ai-helper' ),
            __( 'ИИ‑Помощник', 'ai-helper' ),
            'edit_posts',
            'ai-helper',
            array( $this, 'render_page' ),
            'dashicons-analytics',
            $position
        );
    }

    /**
     * Ensure menu item is directly under Posts.
     *
     * @return int
     */
    protected function find_menu_position_below_posts() {
        global $menu;

        $position = 6; // default just after Posts (5).

        if ( empty( $menu ) ) {
            return $position;
        }

        while ( isset( $menu[ $position ] ) ) {
            $position++;
        }

        return $position;
    }

    /**
     * Enqueue assets for admin page.
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ai-helper' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'ai-helper-admin', AI_HELPER_URL . 'assets/admin.css', array(), AI_HELPER_VERSION );
        wp_enqueue_script( 'ai-helper-admin', AI_HELPER_URL . 'assets/admin.js', array( 'jquery' ), AI_HELPER_VERSION, true );

        $scenarios = AI_Helper_Settings::get_available_scenarios();
        $scenario_settings = array();

        foreach ( $scenarios as $key => $label ) {
            $scenario_settings[ $key ] = AI_Helper_Settings::get_scenario_settings( $key );
        }

        wp_localize_script(
            'ai-helper-admin',
            'AIHelperData',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'ai_helper_nonce' ),
                'scenarios'        => $scenarios,
                'scenarioSettings' => $scenario_settings,
                'globalSettings'   => AI_Helper_Settings::get_global_settings(),
                'costTotal'        => floatval( get_option( AI_Helper_Settings::OPTION_COST_TOTAL, 0 ) ),
                'strings'          => array(
                    'processing'      => __( 'Обрабатываю…', 'ai-helper' ),
                    'retry'           => __( 'Повторить обработку', 'ai-helper' ),
                    'emptyInput'      => __( 'Введите текст или URL для обработки.', 'ai-helper' ),
                    'lengthError'     => __( 'Текст превышает лимит в 20 000 символов.', 'ai-helper' ),
                    'copied'          => __( 'Скопировано', 'ai-helper' ),
                    'copyFailed'      => __( 'Не удалось скопировать', 'ai-helper' ),
                    'errorFallback'   => __( 'Связь с ИИ временно недоступна', 'ai-helper' ),
                    'saved'           => __( 'Сохранено', 'ai-helper' ),
                    'globalSaved'     => __( 'Глобальные настройки сохранены', 'ai-helper' ),
                    'scenarioSaved'   => __( 'Настройки сценария сохранены', 'ai-helper' ),
                ),
            )
        );
    }

    /**
     * Render admin page.
     */
    public function render_page() {
        $scenarios = AI_Helper_Settings::get_available_scenarios();
        ?>
        <div class="wrap ai-helper-wrap">
            <h1><?php esc_html_e( 'ИИ‑Помощник', 'ai-helper' ); ?></h1>

            <div class="ai-helper-layout">
                <div class="ai-helper-main">
                    <?php $this->render_source_block(); ?>
                    <?php $this->render_cost_row(); ?>
                    <?php $this->render_modes( $scenarios ); ?>
                    <?php $this->render_scenario_selector( $scenarios ); ?>
                    <?php $this->render_result_block(); ?>
                    <?php $this->render_result_actions(); ?>
                </div>
                <div class="ai-helper-sidebar">
                    <?php $this->render_global_settings(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render input block.
     */
    protected function render_source_block() {
        ?>
        <div class="ai-helper-card">
            <label for="ai-helper-source" class="ai-helper-block-title"><?php esc_html_e( 'Исходный текст или URL', 'ai-helper' ); ?></label>
            <textarea id="ai-helper-source" rows="8" placeholder="<?php echo esc_attr__( 'Вставьте текст или URL статьи…', 'ai-helper' ); ?>" maxlength="20000"></textarea>
        </div>
        <?php
    }

    /**
     * Render cost row.
     */
    protected function render_cost_row() {
        ?>
        <div class="ai-helper-cost-row">
            <div class="ai-helper-cost-item" id="ai-helper-cost-current"><?php esc_html_e( 'Текущий запрос: $0.00', 'ai-helper' ); ?></div>
            <div class="ai-helper-cost-item" id="ai-helper-cost-session"><?php esc_html_e( 'За текущий сеанс: $0.00', 'ai-helper' ); ?></div>
            <div class="ai-helper-cost-item" id="ai-helper-cost-total"><?php esc_html_e( 'Всего: $0.00', 'ai-helper' ); ?></div>
        </div>
        <?php
    }

    /**
     * Render mode buttons and scenario selector.
     *
     * @param array $scenarios Scenarios list.
     */
    protected function render_modes( $scenarios ) {
        ?>
        <div class="ai-helper-card ai-helper-modes">
            <div class="ai-helper-mode-buttons">
                <label><input type="radio" name="ai-helper-mode" value="links" checked> <?php echo esc_html( $scenarios['links'] ); ?></label>
                <label><input type="radio" name="ai-helper-mode" value="seo_text"> <?php echo esc_html( $scenarios['seo_text'] ); ?></label>
                <label><input type="radio" name="ai-helper-mode" value="seo_links"> <?php echo esc_html( $scenarios['seo_links'] ); ?></label>
            </div>
            <div class="ai-helper-action">
                <button class="button button-primary" id="ai-helper-process" disabled><?php esc_html_e( 'Обработать', 'ai-helper' ); ?></button>
                <span class="ai-helper-status" id="ai-helper-status"></span>
            </div>
        </div>
        <?php
    }

    /**
     * Render scenario selector and panels.
     *
     * @param array $scenarios Scenarios mapping.
     */
    protected function render_scenario_selector( $scenarios ) {
        ?>
        <div class="ai-helper-card">
            <label for="ai-helper-scenario" class="ai-helper-block-title"><?php esc_html_e( 'Выберите сценарий', 'ai-helper' ); ?></label>
            <select id="ai-helper-scenario">
                <?php foreach ( $scenarios as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="ai-helper-scenarios">
                <?php $this->render_scenario_panels(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render scenario panels.
     */
    protected function render_scenario_panels() {
        ?>
        <div class="ai-helper-scenario-panel" data-scenario="links">
            <h3><?php esc_html_e( 'Только Линки', 'ai-helper' ); ?></h3>
            <label>
                <?php esc_html_e( 'Исключить категории (через запятую)', 'ai-helper' ); ?>
                <input type="text" data-field="exclude_categories" placeholder="Новости, Блог">
            </label>
            <label>
                <?php esc_html_e( 'Количество ссылок', 'ai-helper' ); ?>
                <input type="number" min="1" max="10" data-field="links_count" value="4">
            </label>
            <label>
                <?php esc_html_e( 'Приоритетные ключи', 'ai-helper' ); ?>
                <input type="text" data-field="priority_keywords" placeholder="SEO, контент">
            </label>
        </div>

        <div class="ai-helper-scenario-panel" data-scenario="seo_text">
            <h3><?php esc_html_e( 'Только SEO‑обработка текста', 'ai-helper' ); ?></h3>
            <label>
                <?php esc_html_e( 'Целевой объём (слов)', 'ai-helper' ); ?>
                <input type="number" min="300" max="1200" data-field="word_count" value="600">
            </label>
            <label>
                <?php esc_html_e( 'Требования к читабельности', 'ai-helper' ); ?>
                <input type="text" data-field="readability" placeholder="Простые предложения, активный залог">
            </label>
            <label>
                <?php esc_html_e( 'Добавить геоконтекст', 'ai-helper' ); ?>
                <input type="text" data-field="geo_context" placeholder="Москва, Россия">
            </label>
        </div>

        <div class="ai-helper-scenario-panel" data-scenario="seo_links">
            <h3><?php esc_html_e( 'SEO + Линки', 'ai-helper' ); ?></h3>
            <label>
                <?php esc_html_e( 'Максимум ссылок', 'ai-helper' ); ?>
                <input type="number" min="1" max="10" data-field="links_count" value="4">
            </label>
            <label>
                <?php esc_html_e( 'Тон текста', 'ai-helper' ); ?>
                <input type="text" data-field="tone" placeholder="Дружелюбный, экспертный">
            </label>
            <label>
                <?php esc_html_e( 'Усилить ключ Rank Math', 'ai-helper' ); ?>
                <input type="text" data-field="rank_math_focus" placeholder="focus keyword">
            </label>
        </div>

        <div class="ai-helper-scenario-panel" data-scenario="full_guide">
            <h3><?php esc_html_e( 'Создать Гайд (Full Guide Mode)', 'ai-helper' ); ?></h3>
            <label>
                <?php esc_html_e( 'Желаемый объём (слов)', 'ai-helper' ); ?>
                <input type="number" min="800" max="1300" data-field="word_count" value="900">
            </label>
            <label>
                <?php esc_html_e( 'Обязательные блоки', 'ai-helper' ); ?>
                <input type="text" data-field="required_blocks" placeholder="FAQ, CTA, чеклист">
            </label>
            <label>
                <?php esc_html_e( 'CTA сообщение', 'ai-helper' ); ?>
                <input type="text" data-field="cta_text" placeholder="Свяжитесь с нами">
            </label>
        </div>

        <div class="ai-helper-scenario-panel" data-scenario="rank_math">
            <h3><?php esc_html_e( 'Только SEO‑поля Rank Math', 'ai-helper' ); ?></h3>
            <label>
                <?php esc_html_e( 'Title', 'ai-helper' ); ?>
                <input type="text" data-field="title" placeholder="SEO Title">
            </label>
            <label>
                <?php esc_html_e( 'Description', 'ai-helper' ); ?>
                <input type="text" data-field="description" placeholder="Описание">
            </label>
            <label>
                <?php esc_html_e( 'Keywords', 'ai-helper' ); ?>
                <input type="text" data-field="keywords" placeholder="keyword1, keyword2">
            </label>
        </div>
        <?php
    }

    /**
     * Render result block with tabs.
     */
    protected function render_result_block() {
        ?>
        <div class="ai-helper-card ai-helper-result">
            <div class="ai-helper-result-header">
                <div class="ai-helper-tabs">
                    <button class="ai-helper-tab active" data-target="preview"><?php esc_html_e( 'Просмотр', 'ai-helper' ); ?></button>
                    <button class="ai-helper-tab" data-target="html"><?php esc_html_e( 'HTML', 'ai-helper' ); ?></button>
                </div>
            </div>
            <div class="ai-helper-result-body">
                <div id="ai-helper-result-preview" class="ai-helper-result-panel active"></div>
                <textarea id="ai-helper-result-html" class="ai-helper-result-panel" readonly></textarea>
            </div>
        </div>
        <?php
    }

    /**
     * Render result actions.
     */
    protected function render_result_actions() {
        ?>
        <div class="ai-helper-actions">
            <button class="button" id="ai-helper-copy-text"><?php esc_html_e( 'Копировать текст', 'ai-helper' ); ?></button>
            <button class="button" id="ai-helper-copy-html"><?php esc_html_e( 'Копировать HTML', 'ai-helper' ); ?></button>
            <button class="button" id="ai-helper-repeat"><?php esc_html_e( 'Повторить обработку', 'ai-helper' ); ?></button>
            <button class="button" id="ai-helper-save-scenario"><?php esc_html_e( 'Сохранить настройки сценария', 'ai-helper' ); ?></button>
        </div>
        <?php
    }

    /**
     * Render global settings block.
     */
    protected function render_global_settings() {
        ?>
        <div class="ai-helper-card">
            <h2><?php esc_html_e( 'Глобальные настройки', 'ai-helper' ); ?></h2>
            <label>
                <?php esc_html_e( 'Глобальная SEO‑цель', 'ai-helper' ); ?>
                <textarea id="ai-helper-global-seo" rows="3"></textarea>
            </label>
            <label>
                <?php esc_html_e( 'Стайл‑гайд', 'ai-helper' ); ?>
                <textarea id="ai-helper-style-guide" rows="3"></textarea>
            </label>
            <label>
                <?php esc_html_e( 'Температура', 'ai-helper' ); ?>
                <input type="number" min="0" max="2" step="0.1" id="ai-helper-temperature">
            </label>
            <label>
                <?php esc_html_e( 'Тип материала', 'ai-helper' ); ?>
                <select id="ai-helper-material-type">
                    <option value="news"><?php esc_html_e( 'Новость', 'ai-helper' ); ?></option>
                    <option value="guide"><?php esc_html_e( 'Гайд', 'ai-helper' ); ?></option>
                    <option value="review"><?php esc_html_e( 'Обзор', 'ai-helper' ); ?></option>
                </select>
            </label>
            <label>
                <?php esc_html_e( 'Язык результата', 'ai-helper' ); ?>
                <select id="ai-helper-language">
                    <option value="ru-RU">ru-RU</option>
                    <option value="en-US">en-US</option>
                    <option value="uk-UA">uk-UA</option>
                </select>
            </label>
            <button class="button button-secondary" id="ai-helper-save-global"><?php esc_html_e( 'Сохранить глобальные настройки', 'ai-helper' ); ?></button>
        </div>
        <?php
    }
}
