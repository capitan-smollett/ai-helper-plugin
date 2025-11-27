<?php
defined( 'ABSPATH' ) || exit;

class AI_Helper_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_plugin_menu() {
        // Позиция 6 обычно сразу после "Записи" (5)
        add_menu_page(
            'ИИ-Помощник',
            'ИИ-Помощник',
            'edit_others_posts', // Роли Editor и Administrator
            'ai-helper',
            [ $this, 'render_page' ],
            'dashicons-superhero',
            6
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ai-helper' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'ai-helper-css', AI_HELPER_URL . 'assets/admin.css', [], '1.2' );
        wp_enqueue_script( 'ai-helper-js', AI_HELPER_URL . 'assets/admin.js', ['jquery'], '1.2', true );

        wp_localize_script( 'ai-helper-js', 'aiHelperConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ai_helper_nonce' ),
            'totalCost' => get_option( 'ai_helper_cost_total', 0 )
        ]);
    }

    public function render_page() {
        // Получаем сохраненные настройки
        $global = get_option( 'ai_helper_global_settings', [] );
        $user_id = get_current_user_id();
        
        // Получаем мета-настройки сценариев пользователя
        $scenarios = [
            'links' => get_user_meta( $user_id, 'ai_helper_settings_links', true ),
            'seo_text' => get_user_meta( $user_id, 'ai_helper_settings_seo_text', true ),
            'seo_links' => get_user_meta( $user_id, 'ai_helper_settings_seo_links', true ),
            'guide' => get_user_meta( $user_id, 'ai_helper_settings_guide', true ),
            'rank_math' => get_user_meta( $user_id, 'ai_helper_settings_rank_math', true ),
        ];

        include plugin_dir_path( __FILE__ ) . '../templates/admin-ui.php'; 
        // Для компактности ответа HTML вынесен ниже, но лучше хранить его в templates
        $this->render_html_template($global, $scenarios);
    }

    private function render_html_template($global, $scenarios) {
        ?>
        <div class="wrap ai-helper-wrap">
            <h1>ИИ-Помощник</h1>

            <div class="ai-helper-container">
                <div class="ai-box">
                    <h3>Исходный текст / URL</h3>
                    <textarea id="ai-source-input" placeholder="Вставьте текст или URL статьи (с текущего сайта)..."></textarea>
                    
                    <div class="ai-cost-row">
                        <span>Текущий запрос: <strong id="cost-current">$0.00</strong></span>
                        <span>Сессия: <strong id="cost-session">$0.00</strong></span>
                        <span>Всего: <strong id="cost-total">$<?php echo number_format(get_option('ai_helper_cost_total', 0), 4); ?></strong></span>
                    </div>
                </div>

                <div class="ai-grid">
                    <div class="ai-col-left">
                        
                        <div class="ai-box">
                            <h3>Сценарий обработки</h3>
                            <div class="ai-mode-buttons">
                                <button type="button" class="ai-mode-btn" data-scenario="links">Только Линки</button>
                                <button type="button" class="ai-mode-btn" data-scenario="seo_text">Только SEO</button>
                                <button type="button" class="ai-mode-btn active" data-scenario="seo_links">Текст + Линки</button>
                            </div>
                            
                            <label for="ai-scenario-select" class="screen-reader-text">Выбрать сценарий</label>
                            <select id="ai-scenario-select">
                                <option value="links">1. Только Линки</option>
                                <option value="seo_text">2. Только SEO-обработка текста</option>
                                <option value="seo_links" selected>3. SEO + Линки</option>
                                <option value="guide">4. Создать Гайд (Full Guide)</option>
                                <option value="rank_math">5. Только поля Rank Math</option>
                            </select>

                            <div id="settings-panel">
                                <div class="scenario-settings" id="set-links" style="display:none;">
                                    <label>Исключить категории (ID через запятую)</label>
                                    <input type="text" name="exclude_cats" value="<?php echo esc_attr($scenarios['links']['exclude_cats'] ?? ''); ?>">
                                </div>
                                <div class="scenario-settings" id="set-seo_text" style="display:none;">
                                    <label>Целевой объем слов</label>
                                    <input type="number" name="target_words" value="<?php echo esc_attr($scenarios['seo_text']['target_words'] ?? '600'); ?>">
                                </div>
                                <div class="scenario-settings" id="set-seo_links">
                                    <p>Комбинированные настройки (Линки + SEO)</p>
                                    <label>Исключить категории</label>
                                    <input type="text" name="exclude_cats" value="<?php echo esc_attr($scenarios['seo_links']['exclude_cats'] ?? ''); ?>">
                                </div>
                                <div class="scenario-settings" id="set-guide" style="display:none;">
                                    <label>Добавить FAQ?</label>
                                    <input type="checkbox" name="include_faq" <?php checked($scenarios['guide']['include_faq'] ?? 0); ?>>
                                    <br>
                                    <label>Добавить CTA?</label>
                                    <input type="text" name="cta_text" placeholder="Текст призыва" value="<?php echo esc_attr($scenarios['guide']['cta_text'] ?? ''); ?>">
                                </div>
                                <div class="scenario-settings" id="set-rank_math" style="display:none;">
                                    <label>Фокусное ключевое слово (если не задано)</label>
                                    <input type="text" name="fallback_keyword" value="<?php echo esc_attr($scenarios['rank_math']['fallback_keyword'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="button" class="button button-secondary" id="ai-save-scenario">Сохранить настройки сценария</button>
                        </div>

                        <div class="ai-box">
                            <h3>Глобальные настройки</h3>
                            <label>Глобальная SEO-цель</label>
                            <textarea id="global_seo" rows="2"><?php echo esc_textarea($global['global_seo'] ?? ''); ?></textarea>
                            
                            <label>Стайл-гайд</label>
                            <textarea id="style_guide" rows="2"><?php echo esc_textarea($global['style_guide'] ?? ''); ?></textarea>
                            
                            <label>Температура (0-2)</label>
                            <input type="number" id="temperature" min="0" max="2" step="0.1" value="<?php echo esc_attr($global['temperature'] ?? 0.7); ?>">
                            
                            <label>Тип материала</label>
                            <select id="material_type">
                                <option value="news" <?php selected($global['material_type'], 'news'); ?>>Новость</option>
                                <option value="guide" <?php selected($global['material_type'], 'guide'); ?>>Гайд</option>
                                <option value="review" <?php selected($global['material_type'], 'review'); ?>>Обзор</option>
                            </select>

                            <label>Язык</label>
                            <select id="language">
                                <option value="ru-RU" <?php selected($global['language'], 'ru-RU'); ?>>Русский (ru-RU)</option>
                                <option value="en-US" <?php selected($global['language'], 'en-US'); ?>>English (en-US)</option>
                            </select>
                        </div>

                        <button id="ai-process-btn" class="button button-primary button-large">Обработать</button>
                        <span id="ai-spinner" class="spinner"></span>
                    </div>

                    <div class="ai-col-right">
                        <div class="ai-box ai-result-box">
                            <div class="ai-result-header">
                                <h3>Результат</h3>
                                <div class="ai-tabs">
                                    <button class="ai-tab active" data-tab="view">Просмотр</button>
                                    <button class="ai-tab" data-tab="html">HTML</button>
                                </div>
                            </div>
                            
                            <div id="ai-result-view" class="ai-result-content"></div>
                            <textarea id="ai-result-html" class="ai-result-content" style="display:none;" readonly></textarea>
                            
                            <div class="ai-actions">
                                <button type="button" class="button" id="btn-copy-text">Копировать Текст</button>
                                <button type="button" class="button" id="btn-copy-html">Копировать HTML</button>
                                <button type="button" class="button" id="btn-retry">Повторить обработку</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
