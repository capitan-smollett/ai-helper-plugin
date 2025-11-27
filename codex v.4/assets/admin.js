(function ($) {
    const scenarios = AiHelperData.scenarios || {};
    let scenarioValues = AiHelperData.scenarioValues || {};
    let lastPayload = null;
    let sessionCost = 0;

    function renderScenarioFields(key) {
        const container = $('#ai-helper-scenario-settings');
        container.empty();
        const scenario = scenarios[key];

        if (!scenario || !scenario.fields) {
            return;
        }

        const values = scenarioValues[key] || {};
        scenario.fields.forEach((field) => {
            const wrapper = $('<div/>', { class: 'ai-helper-field' });
            wrapper.append($('<label/>', { for: `ai-helper-${field.id}`, text: field.label }));

            let input;
            switch (field.type) {
                case 'textarea':
                    input = $('<textarea/>', {
                        id: `ai-helper-${field.id}`,
                        rows: 3,
                        text: values[field.id] || '',
                        placeholder: field.placeholder || ''
                    });
                    break;
                case 'select':
                    input = $('<select/>', { id: `ai-helper-${field.id}` });
                    Object.entries(field.options || {}).forEach(([value, label]) => {
                        const option = $('<option/>', { value, text: label });
                        if ((values[field.id] || field.default) === value) {
                            option.attr('selected', 'selected');
                        }
                        input.append(option);
                    });
                    break;
                case 'checkbox':
                    input = $('<input/>', {
                        id: `ai-helper-${field.id}`,
                        type: 'checkbox',
                        checked: values[field.id] ?? field.default ?? false
                    });
                    break;
                default:
                    input = $('<input/>', {
                        id: `ai-helper-${field.id}`,
                        type: field.type || 'text',
                        val: values[field.id] || field.default || '',
                        min: field.min,
                        max: field.max,
                        step: field.step,
                        placeholder: field.placeholder || ''
                    });
                    break;
            }

            wrapper.append(input);
            container.append(wrapper);
        });
    }

    function collectScenarioSettings(key) {
        const scenario = scenarios[key];
        const values = {};
        if (!scenario || !scenario.fields) {
            return values;
        }

        scenario.fields.forEach((field) => {
            const el = $(`#ai-helper-${field.id}`);
            if (!el.length) {
                return;
            }

            if (field.type === 'checkbox') {
                values[field.id] = el.is(':checked');
            } else if (field.type === 'number') {
                values[field.id] = parseFloat(el.val());
            } else {
                values[field.id] = el.val();
            }
        });

        return values;
    }

    function updateCosts(current, total) {
        if (typeof current === 'number') {
            $('#ai-helper-cost-current').text(current.toFixed(4));
            sessionCost += current;
            $('#ai-helper-cost-session').text(sessionCost.toFixed(4));
        }
        if (typeof total === 'number') {
            $('#ai-helper-cost-total').text(total.toFixed(4));
        }
    }

    function toggleProcessButton() {
        const value = $('#ai-helper-source').val();
        const disabled = !value || value.length === 0 || value.length > 20000;
        $('#ai-helper-process').prop('disabled', disabled);
    }

    function switchTab(tab) {
        $('.ai-helper-tabs .tab').removeClass('active');
        $(`.ai-helper-tabs .tab[data-tab="${tab}"]`).addClass('active');
        $('.tab-content').removeClass('active');
        $(`#ai-helper-result-${tab}`).addClass('active');
    }

    function showStatus(text) {
        $('#ai-helper-status').text(text);
    }

    function handleResponse(response) {
        $('#ai-helper-result-view').html(response.result_html || response.result_text || '');
        $('#ai-helper-result-html').text(response.result_html || '');
        updateCosts(response.cost.current_request_usd || 0, response.cost.total || 0);
        switchTab('view');
    }

    function buildPayload() {
        const scenario = $('#ai-helper-scenario').val();
        const global = {
            global_seo: $('#ai-helper-global-seo').val(),
            style_guide: $('#ai-helper-style-guide').val(),
            temperature: parseFloat($('#ai-helper-temperature').val()),
            material_type: $('#ai-helper-material-type').val(),
            language: $('#ai-helper-language').val(),
        };
        const settings = collectScenarioSettings(scenario);
        return {
            action: 'ai_helper_process',
            nonce: AiHelperData.nonce,
            source_text: $('#ai-helper-source').val(),
            scenario,
            settings: JSON.stringify(settings),
            global: JSON.stringify(global),
        };
    }

    function saveScenarioSettings() {
        const scenario = $('#ai-helper-scenario').val();
        const settings = collectScenarioSettings(scenario);
        $.post(AiHelperData.ajax_url, {
            action: 'ai_helper_save_scenario',
            nonce: AiHelperData.nonce,
            scenario,
            settings: JSON.stringify(settings),
        }).done((res) => {
            if (res.success) {
                scenarioValues[scenario] = settings;
                showStatus(AiHelperData.strings.save_settings);
            } else {
                showStatus(res.data?.message || AiHelperData.strings.request_failed);
            }
        });
    }

    function processRequest(payload) {
        const button = $('#ai-helper-process');
        button.prop('disabled', true).text(AiHelperData.strings.processing);
        showStatus('');

        $.post(AiHelperData.ajax_url, payload)
            .done((res) => {
                if (res.success && res.data) {
                    lastPayload = payload;
                    handleResponse(res.data);
                    button.text(AiHelperData.strings.process);
                } else {
                    showStatus(res.data?.message || AiHelperData.strings.request_failed);
                    button.text(AiHelperData.strings.process);
                }
            })
            .fail(() => {
                showStatus(AiHelperData.strings.request_failed);
                button.text(AiHelperData.strings.process);
            })
            .always(() => {
                button.prop('disabled', false);
            });
    }

    $(document).ready(() => {
        renderScenarioFields($('#ai-helper-scenario').val());
        updateCosts(0, AiHelperData.costTotals.total);

        $('#ai-helper-source').on('input', toggleProcessButton);
        toggleProcessButton();

        $('.mode-toggle').on('click', function (e) {
            e.preventDefault();
            const scenario = $(this).data('scenario');
            $('#ai-helper-scenario').val(scenario).trigger('change');
        });

        $('#ai-helper-scenario').on('change', function () {
            const scenario = $(this).val();
            renderScenarioFields(scenario);
        });

        $('.ai-helper-tabs .tab').on('click', function (e) {
            e.preventDefault();
            switchTab($(this).data('tab'));
        });

        $('#ai-helper-process').on('click', function (e) {
            e.preventDefault();
            const payload = buildPayload();
            lastPayload = payload;
            processRequest(payload);
        });

        $('#ai-helper-repeat').on('click', function (e) {
            e.preventDefault();
            if (lastPayload) {
                processRequest(lastPayload);
            }
        });

        $('#ai-helper-save-settings').on('click', function (e) {
            e.preventDefault();
            saveScenarioSettings();
        });

        $('#ai-helper-copy-text').on('click', function () {
            const text = $('#ai-helper-result-view').text();
            navigator.clipboard.writeText(text);
            showStatus('Скопировано');
        });

        $('#ai-helper-copy-html').on('click', function () {
            const text = $('#ai-helper-result-html').text();
            navigator.clipboard.writeText(text);
            showStatus('Скопировано');
        });
    });
})(jQuery);
