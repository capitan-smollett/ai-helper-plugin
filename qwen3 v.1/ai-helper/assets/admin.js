(function($) {
	'use strict';

	$(document).ready(function() {
		let lastRequestData = null;

		// Инициализация
		renderScenarioSettings(aiHelperData.scenarios.links);
		$('#ai-helper-scenario').val('seo_links');

		// Обновление стоимости при загрузке
		updateCostDisplay();

		// === Обработчики событий ===

		// Кнопки режимов
		$('.ai-helper-mode-btn').on('click', function() {
			const mode = $(this).data('mode');
			$('#ai-helper-scenario').val(mode).trigger('change');
		});

		// Смена сценария
		$('#ai-helper-scenario').on('change', function() {
			const scenario = $(this).val();
			const config = aiHelperData.scenarios[scenario] || [];
			renderScenarioSettings(config);
			loadScenarioSettings(scenario);
		});

		// Сохранение глобальных настроек
		$('#ai-helper-save-global').on('click', function() {
			const settings = {};
			$('.ai-helper-global-field').each(function() {
				const field = $(this).data('field');
				if ($(this).is('input[type="checkbox"]')) {
					settings[field] = $(this).prop('checked') ? '1' : '0';
				} else {
					settings[field] = $(this).val();
				}
			});

			$.post(ajaxurl, {
				action: 'ai_helper_save_global',
				nonce: aiHelperData.nonce,
				...settings
			}).done(function() {
				showNotice('Глобальные настройки сохранены');
			});
		});

		// Ввод текста/URL
		$('#ai-helper-input').on('input', debounce(checkInput, 300));

		// Кнопка "Обработать"
		$('#ai-helper-process').on('click', processRequest);

		// Сохранение настроек сценария
		$('#ai-helper-save-settings').on('click', function() {
			const scenario = $('#ai-helper-scenario').val();
			const settings = collectScenarioSettings();
			$.post(ajaxurl, {
				action: 'ai_helper_save_scenario',
				nonce: aiHelperData.nonce,
				scenario: scenario,
				settings: settings
			}).done(function() {
				showNotice('Настройки сценария сохранены');
			});
		});

		// Вкладки результата
		$('.ai-helper-tab').on('click', function() {
			const tab = $(this).data('tab');
			$('.ai-helper-tab').removeClass('active');
			$(this).addClass('active');
			$('.ai-helper-result-tab-content').removeClass('active');
			$('#ai-helper-result-' + tab).addClass('active');
		});

		// Копирование
		$('#ai-helper-copy-text').on('click', function() {
			const text = $('#ai-helper-result-preview').text();
			navigator.clipboard.writeText(text).then(() => {
				showNotice(aiHelperData.i18n.copied);
			});
		});

		$('#ai-helper-copy-html').on('click', function() {
			const html = $('#ai-helper-result-html-code').text();
			navigator.clipboard.writeText(html).then(() => {
				showNotice(aiHelperData.i18n.copied);
			});
		});

		// Повтор обработки
		$('#ai-helper-repeat').on('click', function() {
			if (lastRequestData) {
				processRequestWith(lastRequestData);
			}
		});

		// === Функции ===

		function checkInput() {
			const val = $('#ai-helper-input').val().trim();
			const btn = $('#ai-helper-process');
			if (!val) {
				btn.prop('disabled', true);
				return;
			}

			if (val.length > 20000) {
				showError(aiHelperData.i18n.too_long);
				btn.prop('disabled', true);
				return;
			}

			// Проверка URL
			if (isUrl(val)) {
				if (!val.startsWith(aiHelperData.homeUrl)) {
					showError(aiHelperData.i18n.url_not_local);
					btn.prop('disabled', true);
					return;
				}
			}

			btn.prop('disabled', false);
		}

		function isUrl(str) {
			try { new URL(str); return true; } catch(_) { return false; }
		}

		function renderScenarioSettings(config) {
			const $container = $('#ai-helper-scenario-settings');
			if (config.length === 0) {
				$container.html('<div class="ai-helper-settings-placeholder">' + aiHelperData.i18n.empty_input + '</div>');
				return;
			}

			let html = '<h3>' + aiHelperData.i18n.scenario_settings + '</h3><table class="form-table">';
			config.forEach(field => {
				let input;
				switch (field.type) {
					case 'text':
						input = `<input type="text" name="${field.name}" value="${field.default || ''}" class="regular-text">`;
						break;
					case 'number':
						input = `<input type="number" name="${field.name}" value="${field.default || ''}" min="${field.min || 0}" max="${field.max || 100}" class="small-text">`;
						break;
					case 'checkbox':
						input = `<input type="checkbox" name="${field.name}" ${field.default ? 'checked' : ''}>`;
						break;
					default:
						input = '';
				}
				html += `
					<tr>
						<th scope="row">${field.label}</th>
						<td>${input}</td>
					</tr>
				`;
			});
			html += '</table>';
			$container.html(html);
		}

		function collectScenarioSettings() {
			const settings = {};
			$('#ai-helper-scenario-settings input, #ai-helper-scenario-settings select').each(function() {
				const name = $(this).attr('name');
				if (!name) return;
				if ($(this).is('input[type="checkbox"]')) {
					settings[name] = $(this).prop('checked') ? '1' : '0';
				} else {
					settings[name] = $(this).val();
				}
			});
			return settings;
		}

		function loadScenarioSettings(scenario) {
			const key = 'ai_helper_scenario_' + scenario;
			const saved = aiHelperData.currentUserMeta[key] || [];
			if (!saved || typeof saved !== 'object') return;

			Object.keys(saved).forEach(key => {
				const $el = $(`#ai-helper-scenario-settings [name="${key}"]`);
				if ($el.is('input[type="checkbox"]')) {
					$el.prop('checked', saved[key] === '1');
				} else {
					$el.val(saved[key]);
				}
			});
		}

		function processRequest() {
			const input = $('#ai-helper-input').val().trim();
			if (!input) {
				showError(aiHelperData.i18n.empty_input);
				return;
			}
			processRequestWith({
				input: input,
				scenario: $('#ai-helper-scenario').val(),
				scenario_settings: collectScenarioSettings()
			});
		}

		function processRequestWith(data) {
			const $btn = $('#ai-helper-process');
			const $text = $btn.find('.ai-helper-btn-text');
			const $spinner = $btn.find('.ai-helper-spinner');

			$text.text(aiHelperData.i18n.processing);
			$spinner.show();
			$btn.prop('disabled', true);

			$.post(ajaxurl, {
				action: 'ai_helper_process',
				nonce: aiHelperData.nonce,
				input: data.input,
				scenario: data.scenario,
				settings: data.scenario_settings
			})
			.done(function(response) {
				if (response.success) {
					const res = response.data.response;
					displayResult(res.result_text, res.result_html);
					updateCostDisplay(response.data.cost);
					lastRequestData = data;
					$('#ai-helper-repeat').prop('disabled', false);
				} else {
					showError(response.data.message || 'Unknown error');
				}
			})
			.fail(function(xhr, status, error) {
				if (status === 'timeout') {
					showError(aiHelperData.i18n.error_timeout);
				} else {
					showError(aiHelperData.i18n.error_json);
				}
			})
			.always(function() {
				$text.text(aiHelperData.i18n.process);
				$spinner.hide();
				$btn.prop('disabled', false);
			});
		}

		function displayResult(text, html) {
			$('#ai-helper-result-preview').html(text || '<em>Нет текста</em>');
			$('#ai-helper-result-html-code').text(html || '');
			// Переключиться на "Просмотр"
			$('.ai-helper-tab').removeClass('active');
			$('.ai-helper-tab[data-tab="preview"]').addClass('active');
			$('.ai-helper-result-tab-content').removeClass('active');
			$('#ai-helper-result-preview').addClass('active');
		}

		function updateCostDisplay(costs) {
			let current = 0, session = 0, total = 0;

			if (costs) {
				current = costs.current || 0;
				session = costs.session || 0;
				total = costs.total || 0;
			} else {
				// Получить текущие значения
				$.post(ajaxurl, {
					action: 'ai_helper_get_costs',
					nonce: aiHelperData.nonce
				}).done(function(r) {
					if (r.success) {
						$('#ai-helper-cost-current').text('$0.00');
						$('#ai-helper-cost-session').text('$' + r.data.session.toFixed(4));
						$('#ai-helper-cost-total').text('$' + r.data.total.toFixed(4));
					}
				});
				return;
			}

			$('#ai-helper-cost-current').text('$' + current.toFixed(4));
			$('#ai-helper-cost-session').text('$' + session.toFixed(4));
			$('#ai-helper-cost-total').text('$' + total.toFixed(4));
		}

		function showError(msg) {
			alert('⚠️ ' + msg);
		}

		function showNotice(msg) {
			const $notice = $('<div class="notice notice-success is-dismissible"><p>' + msg + '</p></div>');
			$('.wrap').first().before($notice);
			setTimeout(() => $notice.fadeOut(500), 3000);
		}

		function debounce(func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		}
	});
})(jQuery);
