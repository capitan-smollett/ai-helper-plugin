<?php
/**
 * Admin UI class
 */

class AI_Helper_Admin_UI {

	private $page_hook;

	public function init() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_admin_menu() {
		$position = $this->get_menu_position_after( 'edit.php' );
		$this->page_hook = add_menu_page(
			__( 'ИИ‑Помощник', 'ai-helper' ),
			__( 'ИИ‑Помощник', 'ai-helper' ),
			'edit_posts',
			'ai-helper',
			[ $this, 'render_page' ],
			'dashicons-ai', // WP 6.5+; fallback ниже
			$position
		);
	}

	private function get_menu_position_after( $target_slug ) {
		global $menu;
		$default = 26; // после Записи (5), Медиа (10), Страницы (20)
		foreach ( $menu as $index => $item ) {
			if ( is_array( $item ) && isset( $item[2] ) && $item[2] === $target_slug ) {
				$position = $index + 1;
				while ( isset( $menu[ $position ] ) && ! empty( $menu[ $position ][0] ) ) {
					$position++;
				}
				return $position;
			}
		}
		return $default;
	}

	public function enqueue_assets( $hook ) {
		if ( $this->page_hook !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ai-helper-admin',
			AI_HELPER_URL . 'assets/admin.css',
			[],
			AI_HELPER_VERSION
		);

		wp_enqueue_script(
			'ai-helper-admin',
			AI_HELPER_URL . 'assets/admin.js',
			[ 'jquery' ],
			AI_HELPER_VERSION,
			true
		);

		// Передача данных в JS
		wp_localize_script(
			'ai-helper-admin',
			'aiHelperData',
			[
				'nonce'           => wp_create_nonce( 'ai_helper_process' ),
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'scenarios'       => $this->get_scenario_configs(),
				'globalSettings'  => get_option( 'ai_helper_global_settings', $this->get_default_global_settings() ),
				'currentUserMeta' => get_user_meta( get_current_user_id(), '', true ),
				'homeUrl'         => home_url(),
				'i18n'            => [
					'processing'    => __( 'Обрабатываю…', 'ai-helper' ),
					'error_timeout' => __( 'Связь с ИИ временно недоступна', 'ai-helper' ),
					'error_json'    => __( 'Ошибка: некорректный ответ от сервера', 'ai-helper' ),
					'empty_input'   => __( 'Введите текст или URL', 'ai-helper' ),
					'too_long'      => __( 'Текст превышает 20 000 символов', 'ai-helper' ),
					'url_not_local' => __( 'URL должен быть с этого сайта', 'ai-helper' ),
					'copied'        => __( 'Скопировано!', 'ai-helper' ),
				],
			]
		);
	}

	private function get_default_global_settings() {
		return [
			'seo_goal'     => '',
			'style_guide'  => '',
			'temperature'  => 0.7,
			'material_type' => 'guide',
			'language'     => 'ru-RU',
		];
	}

	private function get_scenario_configs() {
		return [
			'links' => [
				[
					'type'  => 'text',
					'name'  => 'exclude_cats',
					'label' => __( 'Исключить категории (slug, через запятую)', 'ai-helper' ),
				],
				[
					'type'    => 'number',
					'name'    => 'link_count',
					'label'   => __( 'Количество ссылок', 'ai-helper' ),
					'default' => 4,
					'min'     => 1,
					'max'     => 10,
				],
			],
			'seo' => [
				[
					'type'  => 'text',
					'name'  => 'target_word_count',
					'label' => __( 'Целевое количество слов', 'ai-helper' ),
					'default' => 600,
				],
				[
					'type'  => 'checkbox',
					'name'  => 'add_geo_context',
					'label' => __( 'Добавить геоконтекст', 'ai-helper' ),
				],
			],
			'seo_links' => [
				[
					'type'  => 'text',
					'name'  => 'exclude_cats',
					'label' => __( 'Исключить категории (slug, через запятую)', 'ai-helper' ),
				],
				[
					'type'    => 'number',
					'name'    => 'link_count',
					'label'   => __( 'Количество ссылок', 'ai-helper' ),
					'default' => 4,
					'min'     => 1,
					'max'     => 10,
				],
				[
					'type'  => 'text',
					'name'  => 'target_word_count',
					'label' => __( 'Целевое количество слов', 'ai-helper' ),
					'default' => 800,
				],
			],
			'guide' => [
				[
					'type'  => 'checkbox',
					'name'  => 'include_faq',
					'label' => __( 'Включить блок FAQ', 'ai-helper' ),
					'default' => true,
				],
				[
					'type'  => 'checkbox',
					'name'  => 'include_cta',
					'label' => __( 'Включить блок CTA', 'ai-helper' ),
					'default' => true,
				],
				[
					'type'  => 'checkbox',
					'name'  => 'include_steps',
					'label' => __( 'Включить пошаговый гайд', 'ai-helper' ),
					'default' => true,
				],
				[
					'type'  => 'text',
					'name'  => 'target_word_count_min',
					'label' => __( 'Мин. объём (слов)', 'ai-helper' ),
					'default' => 800,
				],
				[
					'type'  => 'text',
					'name'  => 'target_word_count_max',
					'label' => __( 'Макс. объём (слов)', 'ai-helper' ),
					'default' => 1300,
				],
			],
			'rank_math' => [
				[
					'type'  => 'text',
					'name'  => 'focus_keyword',
					'label' => __( 'Основной ключ', 'ai-helper' ),
				],
				[
					'type'  => 'checkbox',
					'name'  => 'optimize_for_voice',
					'label' => __( 'Оптимизировать под голосовой поиск', 'ai-helper' ),
				],
			],
		];
	}

	public function render_page() {
		?>
		<div class="wrap ai-helper-wrap">
			<h1><?php esc_html_e( 'ИИ‑Помощник для контента и SEO', 'ai-helper' ); ?></h1>

			<div class="ai-helper-main">
				<!-- Блок 1: Ввод -->
				<div class="ai-helper-section">
					<h2><?php esc_html_e( 'Исходный текст или URL', 'ai-helper' ); ?></h2>
					<textarea id="ai-helper-input" rows="6" placeholder="<?php esc_attr_e( 'Вставьте текст или URL статьи…', 'ai-helper' ); ?>"></textarea>
				</div>

				<!-- Блок 2: Режимы (3,4,5 + select) -->
				<div class="ai-helper-section ai-helper-modes">
					<div class="ai-helper-mode-buttons">
						<button type="button" class="button ai-helper-mode-btn" data-mode="links"><?php esc_html_e( 'Только Линки', 'ai-helper' ); ?></button>
						<button type="button" class="button ai-helper-mode-btn" data-mode="seo"><?php esc_html_e( 'Только текст (SEO)', 'ai-helper' ); ?></button>
						<button type="button" class="button ai-helper-mode-btn" data-mode="seo_links"><?php esc_html_e( 'Текст + Линки', 'ai-helper' ); ?></button>
					</div>

					<label for="ai-helper-scenario"><?php esc_html_e( 'Полный сценарий:', 'ai-helper' ); ?></label>
					<select id="ai-helper-scenario">
						<option value="links"><?php esc_html_e( '1. Только Линки', 'ai-helper' ); ?></option>
						<option value="seo"><?php esc_html_e( '2. Только SEO-обработка текста', 'ai-helper' ); ?></option>
						<option value="seo_links"><?php esc_html_e( '3. SEO + Линки', 'ai-helper' ); ?></option>
						<option value="guide"><?php esc_html_e( '4. Создать Гайд (Full Guide Mode)', 'ai-helper' ); ?></option>
						<option value="rank_math"><?php esc_html_e( '5. Только SEO-поля Rank Math', 'ai-helper' ); ?></option>
					</select>
				</div>

				<!-- Панель настроек сценария -->
				<div class="ai-helper-section" id="ai-helper-scenario-settings">
					<!-- Динамически заполняется JS -->
					<div class="ai-helper-settings-placeholder">
						<?php esc_html_e( 'Выберите сценарий для настройки', 'ai-helper' ); ?>
					</div>
				</div>

				<!-- Глобальные настройки -->
				<div class="ai-helper-section ai-helper-global">
					<h2><?php esc_html_e( 'Глобальные настройки', 'ai-helper' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'SEO‑цель', 'ai-helper' ); ?></th>
							<td>
								<textarea name="global_seo_goal" rows="2" class="large-text ai-helper-global-field" data-field="seo_goal"><?php echo esc_textarea( get_option( 'ai_helper_global_settings', [] )['seo_goal'] ?? '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Общая цель SEO-оптимизации (например: «повысить конверсию продаж увлажнителей»)', 'ai-helper' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Стайл‑гайд', 'ai-helper' ); ?></th>
							<td>
								<textarea name="global_style_guide" rows="3" class="large-text ai-helper-global-field" data-field="style_guide"><?php echo esc_textarea( get_option( 'ai_helper_global_settings', [] )['style_guide'] ?? '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Тон, стиль, ограничения (например: «дружелюбный, без жаргона, для мам 25–40 лет»)', 'ai-helper' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Температура', 'ai-helper' ); ?></th>
							<td>
								<input type="number" step="0.1" min="0" max="2" class="small-text ai-helper-global-field" data-field="temperature"
									value="<?php echo esc_attr( get_option( 'ai_helper_global_settings', [] )['temperature'] ?? '0.7' ); ?>">
								<p class="description"><?php esc_html_e( '0.0 — строго по шаблону; 1.0+ — креативно', 'ai-helper' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Тип материала', 'ai-helper' ); ?></th>
							<td>
								<select class="ai-helper-global-field" data-field="material_type">
									<option value="news" <?php selected( ( get_option( 'ai_helper_global_settings', [] )['material_type'] ?? 'guide' ), 'news' ); ?>><?php esc_html_e( 'Новость', 'ai-helper' ); ?></option>
									<option value="guide" <?php selected( ( get_option( 'ai_helper_global_settings', [] )['material_type'] ?? 'guide' ), 'guide' ); ?>><?php esc_html_e( 'Гайд', 'ai-helper' ); ?></option>
									<option value="review" <?php selected( ( get_option( 'ai_helper_global_settings', [] )['material_type'] ?? 'guide' ), 'review' ); ?>><?php esc_html_e( 'Обзор', 'ai-helper' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Язык результата', 'ai-helper' ); ?></th>
							<td>
								<select class="ai-helper-global-field" data-field="language">
									<option value="ru-RU" <?php selected( ( get_option( 'ai_helper_global_settings', [] )['language'] ?? 'ru-RU' ), 'ru-RU' ); ?>>ru-RU</option>
									<option value="en-US" <?php selected( ( get_option( 'ai_helper_global_settings', [] )['language'] ?? 'ru-RU' ), 'en-US' ); ?>>en-US</option>
									<option value="uk-UA" <?php selected( ( get_option( 'ai_helper_global_settings', [] )['language'] ?? 'ru-RU' ), 'uk-UA' ); ?>>uk-UA</option>
								</select>
							</td>
						</tr>
					</table>
					<button type="button" id="ai-helper-save-global" class="button button-primary"><?php esc_html_e( 'Сохранить глобальные настройки', 'ai-helper' ); ?></button>
				</div>

				<!-- Строка стоимости -->
				<div class="ai-helper-cost-bar">
					<span class="ai-helper-cost-item">
						<strong><?php esc_html_e( 'Текущий запрос:', 'ai-helper' ); ?></strong>
						<span id="ai-helper-cost-current">$0.00</span>
					</span>
					<span class="ai-helper-cost-item">
						<strong><?php esc_html_e( 'За сеанс:', 'ai-helper' ); ?></strong>
						<span id="ai-helper-cost-session">$0.00</span>
					</span>
					<span class="ai-helper-cost-item">
						<strong><?php esc_html_e( 'Всего:', 'ai-helper' ); ?></strong>
						<span id="ai-helper-cost-total">$0.00</span>
					</span>
				</div>

				<!-- Кнопка обработки -->
				<div class="ai-helper-actions">
					<button id="ai-helper-process" class="button button-primary" disabled>
						<span class="ai-helper-btn-text"><?php esc_html_e( 'Обработать', 'ai-helper' ); ?></span>
						<span class="ai-helper-spinner" style="display:none;">↻</span>
					</button>
					<button id="ai-helper-save-settings" class="button"><?php esc_html_e( 'Сохранить настройки сценария', 'ai-helper' ); ?></button>
				</div>

				<!-- Блок 2: Результат -->
				<div class="ai-helper-section">
					<h2><?php esc_html_e( 'Результат обработки', 'ai-helper' ); ?></h2>
					<div class="ai-helper-result-tabs">
						<button type="button" class="ai-helper-tab active" data-tab="preview"><?php esc_html_e( 'Просмотр', 'ai-helper' ); ?></button>
						<button type="button" class="ai-helper-tab" data-tab="html"><?php esc_html_e( 'HTML', 'ai-helper' ); ?></button>
					</div>
					<div class="ai-helper-result-content">
						<div id="ai-helper-result-preview" class="ai-helper-result-tab-content active">
							<p class="ai-helper-placeholder"><?php esc_html_e( 'Результат появится здесь после обработки', 'ai-helper' ); ?></p>
						</div>
						<div id="ai-helper-result-html" class="ai-helper-result-tab-content" style="display:none;">
							<pre><code id="ai-helper-result-html-code"></code></pre>
						</div>
					</div>

					<!-- Действия с результатом -->
					<div class="ai-helper-result-actions">
						<button type="button" id="ai-helper-copy-text" class="button"><?php esc_html_e( 'Копировать текст', 'ai-helper' ); ?></button>
						<button type="button" id="ai-helper-copy-html" class="button"><?php esc_html_e( 'Копировать HTML', 'ai-helper' ); ?></button>
						<button type="button" id="ai-helper-repeat" class="button" disabled><?php esc_html_e( 'Повторить обработку', 'ai-helper' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
