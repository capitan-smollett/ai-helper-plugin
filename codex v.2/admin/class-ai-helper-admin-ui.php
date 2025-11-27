<?php
/**
 * Admin UI.
 *
 * @package AI_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Render admin page.
 */
class AI_Helper_Admin_UI {
/**
 * Slug for admin page.
 *
 * @var string
 */
private $page_slug = 'ai-helper';

/**
 * Constructor.
 */
public function __construct() {
add_action( 'admin_menu', array( $this, 'register_menu' ) );
add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
add_action( 'admin_post_ai_helper_save_global', array( $this, 'handle_global_settings_save' ) );
}

/**
 * Register menu below Posts.
 *
 * @return void
 */
public function register_menu() {
$position = 6; // Directly after Posts which uses position 5.

add_menu_page(
__( 'ИИ‑Помощник', 'ai-helper' ),
__( 'ИИ‑Помощник', 'ai-helper' ),
'edit_posts',
$this->page_slug,
array( $this, 'render_page' ),
'dashicons-format-aside',
$position
);
}

/**
 * Enqueue assets for admin page.
 *
 * @param string $hook Current page.
 */
public function enqueue_assets( $hook ) {
if ( 'toplevel_page_' . $this->page_slug !== $hook ) {
return;
}

wp_enqueue_style(
'ai-helper-admin',
AI_HELPER_PLUGIN_URL . 'assets/admin.css',
array(),
AI_HELPER_VERSION
);

wp_enqueue_script(
'ai-helper-admin',
AI_HELPER_PLUGIN_URL . 'assets/admin.js',
array( 'jquery', 'wp-util' ),
AI_HELPER_VERSION,
true
);

$current_user      = get_current_user_id();
$scenario_settings = array();
foreach ( AI_Helper_Settings::get_scenarios() as $key => $label ) {
$scenario_settings[ $key ] = AI_Helper_Settings::get_scenario_settings( $current_user, $key );
}

wp_localize_script(
'ai-helper-admin',
'aiHelperData',
array(
'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
'nonce'            => wp_create_nonce( 'ai_helper_nonce' ),
'scenarios'        => AI_Helper_Settings::get_scenarios(),
'scenarioSettings' => $scenario_settings,
'globalSettings'   => AI_Helper_Settings::get_global_settings(),
'costTotal'        => floatval( get_option( 'ai_helper_cost_total', 0 ) ),
'i18n'             => array(
'processing'        => __( 'Обрабатываю…', 'ai-helper' ),
'process'           => __( 'Обработать', 'ai-helper' ),
'emptyField'        => __( 'Поле ввода пустое.', 'ai-helper' ),
'overLimit'         => __( 'Текст превышает лимит 20 000 символов.', 'ai-helper' ),
'copyText'          => __( 'Текст скопирован', 'ai-helper' ),
'copyHtml'          => __( 'HTML скопирован', 'ai-helper' ),
'apiUnavailable'    => __( 'Связь с ИИ временно недоступна', 'ai-helper' ),
'scenarioSaved'     => __( 'Настройки сценария сохранены.', 'ai-helper' ),
'urlParsing'        => __( 'Идёт загрузка данных по URL…', 'ai-helper' ),
'settingsSaved'     => __( 'Глобальные настройки сохранены.', 'ai-helper' ),
'parseError'        => __( 'Не удалось извлечь данные статьи.', 'ai-helper' ),
),
)
);
}

/**
 * Render admin page.
 */
public function render_page() {
if ( ! current_user_can( 'edit_posts' ) ) {
return;
}

$global_settings = AI_Helper_Settings::get_global_settings();
$scenarios       = AI_Helper_Settings::get_scenarios();
$current_user    = get_current_user_id();
?>
<div class="wrap ai-helper-wrap">
<h1><?php esc_html_e( 'ИИ‑Помощник для контента и SEO', 'ai-helper' ); ?></h1>

<div class="ai-helper-grid">
<div class="ai-helper-main">
<label for="ai-helper-source" class="ai-helper-label"><?php esc_html_e( 'Исходный текст или URL', 'ai-helper' ); ?></label>
<textarea id="ai-helper-source" class="widefat ai-helper-textarea" rows="8" placeholder="<?php esc_attr_e( 'Вставьте текст или URL статьи…', 'ai-helper' ); ?>"></textarea>
<div id="ai-helper-url-meta" class="ai-helper-url-meta" aria-live="polite"></div>

<div class="ai-helper-controls">
<div class="ai-helper-modes" role="group" aria-label="<?php esc_attr_e( 'Быстрые сценарии', 'ai-helper' ); ?>">
<label><input type="radio" name="ai-helper-mode" value="links_only"> <?php echo esc_html( $scenarios['links_only'] ); ?></label>
<label><input type="radio" name="ai-helper-mode" value="seo_text"> <?php echo esc_html( $scenarios['seo_text'] ); ?></label>
<label><input type="radio" name="ai-helper-mode" value="seo_links"> <?php echo esc_html( $scenarios['seo_links'] ); ?></label>
</div>

<div class="ai-helper-costs">
<span><?php esc_html_e( 'Текущий запрос: $', 'ai-helper' ); ?><span id="ai-helper-cost-current">0.00</span></span>
<span><?php esc_html_e( 'За текущий сеанс: $', 'ai-helper' ); ?><span id="ai-helper-cost-session">0.00</span></span>
<span><?php esc_html_e( 'Всего: $', 'ai-helper' ); ?><span id="ai-helper-cost-total">0.00</span></span>
</div>
</div>

<div class="ai-helper-scenario-select">
<label for="ai-helper-scenario"><?php esc_html_e( 'Сценарий', 'ai-helper' ); ?></label>
<select id="ai-helper-scenario">
<?php foreach ( $scenarios as $key => $label ) : ?>
<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
<?php endforeach; ?>
</select>
</div>

<div id="ai-helper-scenario-panels" class="ai-helper-scenario-panels">
<?php $this->render_scenario_panels( $current_user ); ?>
</div>

<div class="ai-helper-actions">
<button class="button button-primary" id="ai-helper-process"><?php esc_html_e( 'Обработать', 'ai-helper' ); ?></button>
<span id="ai-helper-processing" class="ai-helper-processing" aria-live="polite"></span>
</div>

<div class="ai-helper-result">
<div class="ai-helper-tabs" role="tablist">
<button class="ai-helper-tab active" data-tab="view" role="tab"><?php esc_html_e( 'Просмотр', 'ai-helper' ); ?></button>
<button class="ai-helper-tab" data-tab="html" role="tab"><?php esc_html_e( 'HTML', 'ai-helper' ); ?></button>
</div>
<div class="ai-helper-tab-content" id="ai-helper-tab-view" role="tabpanel"></div>
<div class="ai-helper-tab-content hidden" id="ai-helper-tab-html" role="tabpanel"><pre></pre></div>
</div>

<div class="ai-helper-result-actions">
<button class="button" id="ai-helper-copy-text"><?php esc_html_e( 'Копировать текст', 'ai-helper' ); ?></button>
<button class="button" id="ai-helper-copy-html"><?php esc_html_e( 'Копировать HTML', 'ai-helper' ); ?></button>
<button class="button" id="ai-helper-repeat"><?php esc_html_e( 'Повторить обработку', 'ai-helper' ); ?></button>
<button class="button" id="ai-helper-save-scenario"><?php esc_html_e( 'Сохранить настройки сценария', 'ai-helper' ); ?></button>
</div>
</div>

<div class="ai-helper-sidebar">
<h2><?php esc_html_e( 'Глобальные настройки', 'ai-helper' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<?php wp_nonce_field( 'ai_helper_save_global', 'ai_helper_save_global_nonce' ); ?>
<input type="hidden" name="action" value="ai_helper_save_global" />
<p>
<label for="ai-helper-global-goal"><?php esc_html_e( 'Глобальная SEO-цель', 'ai-helper' ); ?></label>
<textarea id="ai-helper-global-goal" name="global_seo_goal" rows="3" class="widefat"><?php echo esc_textarea( $global_settings['global_seo_goal'] ); ?></textarea>
</p>
<p>
<label for="ai-helper-style-guide"><?php esc_html_e( 'Стайл-гайд', 'ai-helper' ); ?></label>
<textarea id="ai-helper-style-guide" name="style_guide" rows="3" class="widefat"><?php echo esc_textarea( $global_settings['style_guide'] ); ?></textarea>
</p>
<p>
<label for="ai-helper-temperature"><?php esc_html_e( 'Температура', 'ai-helper' ); ?></label>
<input type="number" id="ai-helper-temperature" name="temperature" min="0" max="2" step="0.1" class="widefat" value="<?php echo esc_attr( $global_settings['temperature'] ); ?>" />
</p>
<p>
<label for="ai-helper-material-type"><?php esc_html_e( 'Тип материала', 'ai-helper' ); ?></label>
<select id="ai-helper-material-type" name="material_type" class="widefat">
<option value="news" <?php selected( $global_settings['material_type'], 'news' ); ?>><?php esc_html_e( 'Новость', 'ai-helper' ); ?></option>
<option value="guide" <?php selected( $global_settings['material_type'], 'guide' ); ?>><?php esc_html_e( 'Гайд', 'ai-helper' ); ?></option>
<option value="review" <?php selected( $global_settings['material_type'], 'review' ); ?>><?php esc_html_e( 'Обзор', 'ai-helper' ); ?></option>
</select>
</p>
<p>
<label for="ai-helper-language"><?php esc_html_e( 'Язык результата', 'ai-helper' ); ?></label>
<select id="ai-helper-language" name="language" class="widefat">
<option value="ru-RU" <?php selected( $global_settings['language'], 'ru-RU' ); ?>>ru-RU</option>
<option value="en-US" <?php selected( $global_settings['language'], 'en-US' ); ?>>en-US</option>
<option value="uk-UA" <?php selected( $global_settings['language'], 'uk-UA' ); ?>>uk-UA</option>
</select>
</p>
<p>
<button class="button button-primary" type="submit"><?php esc_html_e( 'Сохранить глобальные настройки', 'ai-helper' ); ?></button>
</p>
</form>
</div>
</div>
</div>
<?php
}

/**
 * Render scenario panels.
 *
 * @param int $user_id Current user.
 */
private function render_scenario_panels( $user_id ) {
$scenarios = AI_Helper_Settings::get_scenarios();
foreach ( $scenarios as $key => $label ) {
$settings = AI_Helper_Settings::get_scenario_settings( $user_id, $key );
?>
<div class="ai-helper-scenario-panel hidden" data-scenario="<?php echo esc_attr( $key ); ?>">
<h3><?php echo esc_html( $label ); ?></h3>
<?php $this->render_fields_for_scenario( $key, $settings ); ?>
</div>
<?php
}
}

/**
 * Render fields for scenario.
 *
 * @param string $scenario Scenario key.
 * @param array  $settings Settings.
 */
private function render_fields_for_scenario( $scenario, $settings ) {
switch ( $scenario ) {
case 'links_only':
?>
<p>
<label for="link_count_links_only"><?php esc_html_e( 'Количество внутренних ссылок', 'ai-helper' ); ?></label>
<input type="number" id="link_count_links_only" data-setting="link_count" min="1" max="10" value="<?php echo esc_attr( $settings['link_count'] ); ?>" />
</p>
<p>
<label for="exclude_categories_links_only"><?php esc_html_e( 'Исключить категории (через запятую)', 'ai-helper' ); ?></label>
<textarea id="exclude_categories_links_only" data-setting="exclude_categories" rows="2" class="widefat"><?php echo esc_textarea( $settings['exclude_categories'] ); ?></textarea>
</p>
<p>
<label for="anchor_strategy_links_only"><?php esc_html_e( 'Пожелания к анкорам', 'ai-helper' ); ?></label>
<input type="text" id="anchor_strategy_links_only" data-setting="anchor_strategy" class="widefat" value="<?php echo esc_attr( $settings['anchor_strategy'] ); ?>" />
</p>
<?php
break;
case 'seo_text':
?>
<p>
<label for="target_length_seo_text"><?php esc_html_e( 'Целевой объём', 'ai-helper' ); ?></label>
<input type="number" id="target_length_seo_text" data-setting="target_length" min="300" max="2000" value="<?php echo esc_attr( $settings['target_length'] ); ?>" />
</p>
<p>
<label><input type="checkbox" data-setting="readability_focus" <?php checked( ! empty( $settings['readability_focus'] ) ); ?> /> <?php esc_html_e( 'Усилить читабельность', 'ai-helper' ); ?></label>
</p>
<p>
<label for="rank_math_keyword_seo_text"><?php esc_html_e( 'Ключ Rank Math', 'ai-helper' ); ?></label>
<input type="text" id="rank_math_keyword_seo_text" data-setting="rank_math_keyword" class="widefat" value="<?php echo esc_attr( $settings['rank_math_keyword'] ); ?>" />
</p>
<p>
<label for="geo_context_seo_text"><?php esc_html_e( 'Геоконтекст', 'ai-helper' ); ?></label>
<input type="text" id="geo_context_seo_text" data-setting="geo_context" class="widefat" value="<?php echo esc_attr( $settings['geo_context'] ); ?>" />
</p>
<?php
break;
case 'seo_links':
?>
<p>
<label for="target_length_seo_links"><?php esc_html_e( 'Целевой объём', 'ai-helper' ); ?></label>
<input type="number" id="target_length_seo_links" data-setting="target_length" min="400" max="2000" value="<?php echo esc_attr( $settings['target_length'] ); ?>" />
</p>
<p>
<label for="link_count_seo_links"><?php esc_html_e( 'Количество ссылок', 'ai-helper' ); ?></label>
<input type="number" id="link_count_seo_links" data-setting="link_count" min="1" max="10" value="<?php echo esc_attr( $settings['link_count'] ); ?>" />
</p>
<p>
<label for="exclude_categories_seo_links"><?php esc_html_e( 'Исключить категории', 'ai-helper' ); ?></label>
<textarea id="exclude_categories_seo_links" data-setting="exclude_categories" rows="2" class="widefat"><?php echo esc_textarea( $settings['exclude_categories'] ); ?></textarea>
</p>
<p>
<label for="geo_context_seo_links"><?php esc_html_e( 'Геоконтекст', 'ai-helper' ); ?></label>
<input type="text" id="geo_context_seo_links" data-setting="geo_context" class="widefat" value="<?php echo esc_attr( $settings['geo_context'] ); ?>" />
</p>
<?php
break;
case 'full_guide':
?>
<p>
<label for="word_range_full_guide"><?php esc_html_e( 'Объём (слов)', 'ai-helper' ); ?></label>
<input type="text" id="word_range_full_guide" data-setting="word_range" class="widefat" value="<?php echo esc_attr( $settings['word_range'] ); ?>" />
</p>
<p>
<label><input type="checkbox" data-setting="include_checklist" <?php checked( ! empty( $settings['include_checklist'] ) ); ?> /> <?php esc_html_e( 'Добавить чеклист и шаги', 'ai-helper' ); ?></label>
</p>
<p>
<label for="faq_items_full_guide"><?php esc_html_e( 'Количество вопросов FAQ', 'ai-helper' ); ?></label>
<input type="number" id="faq_items_full_guide" data-setting="faq_items" min="2" max="10" value="<?php echo esc_attr( $settings['faq_items'] ); ?>" />
</p>
<?php
break;
case 'rank_math':
?>
<p>
<label for="focus_keyword_rank_math"><?php esc_html_e( 'Focus Keyword', 'ai-helper' ); ?></label>
<input type="text" id="focus_keyword_rank_math" data-setting="focus_keyword" class="widefat" value="<?php echo esc_attr( $settings['focus_keyword'] ); ?>" />
</p>
<p>
<label for="audience_rank_math"><?php esc_html_e( 'Целевая аудитория', 'ai-helper' ); ?></label>
<input type="text" id="audience_rank_math" data-setting="audience" class="widefat" value="<?php echo esc_attr( $settings['audience'] ); ?>" />
</p>
<?php
break;
}
}

/**
 * Handle global settings form submit.
 */
public function handle_global_settings_save() {
if ( ! current_user_can( 'edit_posts' ) ) {
wp_die( __( 'Недостаточно прав.', 'ai-helper' ) );
}

check_admin_referer( 'ai_helper_save_global', 'ai_helper_save_global_nonce' );

AI_Helper_Settings::save_global_settings( $_POST );

wp_safe_redirect( add_query_arg( 'ai_helper_saved', '1', wp_get_referer() ) );
exit;
}
}
