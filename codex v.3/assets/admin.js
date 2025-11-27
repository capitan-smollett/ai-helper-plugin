(function($) {
    'use strict';

    const state = {
        scenario: 'links',
        sessionCost: 0,
        lastPayload: null,
        currentSettings: AIHelperData.scenarioSettings || {},
        globalSettings: AIHelperData.globalSettings || {},
    };

    function init() {
        bindEvents();
        populateGlobals();
        populateScenarioFields(state.scenario);
        updateCostRow(0, state.sessionCost, AIHelperData.costTotal || 0);
    }

    function bindEvents() {
        const sourceInput = $('#ai-helper-source');
        const processBtn = $('#ai-helper-process');

        sourceInput.on('input', function() {
            processBtn.prop('disabled', $(this).val().trim().length === 0);
        });

        $('input[name="ai-helper-mode"]').on('change', function() {
            const mode = $(this).val();
            $('#ai-helper-scenario').val(mode);
            state.scenario = mode;
            populateScenarioFields(mode);
        });

        $('#ai-helper-scenario').on('change', function() {
            const value = $(this).val();
            $('input[name="ai-helper-mode"][value="' + value + '"]').prop('checked', true);
            state.scenario = value;
            populateScenarioFields(value);
        });

        $('#ai-helper-process').on('click', function(e) {
            e.preventDefault();
            submitRequest();
        });

        $('#ai-helper-repeat').on('click', function(e) {
            e.preventDefault();
            if (state.lastPayload) {
                submitRequest(state.lastPayload);
            }
        });

        $('#ai-helper-save-scenario').on('click', function(e) {
            e.preventDefault();
            saveScenarioSettings();
        });

        $('#ai-helper-save-global').on('click', function(e) {
            e.preventDefault();
            saveGlobalSettings();
        });

        $('.ai-helper-tab').on('click', function() {
            $('.ai-helper-tab').removeClass('active');
            $(this).addClass('active');
            const target = $(this).data('target');
            $('.ai-helper-result-panel').removeClass('active');
            if (target === 'preview') {
                $('#ai-helper-result-preview').addClass('active');
            } else {
                $('#ai-helper-result-html').addClass('active');
            }
        });

        $('#ai-helper-copy-text').on('click', function() {
            copyToClipboard($('#ai-helper-result-preview').text());
        });

        $('#ai-helper-copy-html').on('click', function() {
            copyToClipboard($('#ai-helper-result-html').val());
        });
    }

    function populateGlobals() {
        $('#ai-helper-global-seo').val(state.globalSettings.global_seo || '');
        $('#ai-helper-style-guide').val(state.globalSettings.style_guide || '');
        $('#ai-helper-temperature').val(state.globalSettings.temperature || 0.7);
        $('#ai-helper-material-type').val(state.globalSettings.material_type || 'news');
        $('#ai-helper-language').val(state.globalSettings.language || 'ru-RU');
    }

    function populateScenarioFields(scenario) {
        $('.ai-helper-scenario-panel').removeClass('active');
        const panel = $('.ai-helper-scenario-panel[data-scenario="' + scenario + '"]');
        panel.addClass('active');

        const settings = state.currentSettings[scenario] || {};

        panel.find('[data-field]').each(function() {
            const key = $(this).data('field');
            if (settings[key] !== undefined) {
                $(this).val(settings[key]);
            } else {
                if ($(this).attr('type') === 'number' && $(this).data('default')) {
                    $(this).val($(this).data('default'));
                }
            }
        });
    }

    function buildScenarioSettings(scenario) {
        const panel = $('.ai-helper-scenario-panel[data-scenario="' + scenario + '"]');
        const settings = {};
        panel.find('[data-field]').each(function() {
            const key = $(this).data('field');
            settings[key] = $(this).val();
        });
        state.currentSettings[scenario] = settings;
        return settings;
    }

    function submitRequest(presetPayload) {
        const source = $('#ai-helper-source').val().trim();
        if (!source.length) {
            alert(AIHelperData.strings.emptyInput);
            return;
        }

        if (source.length > 20000) {
            alert(AIHelperData.strings.lengthError);
            return;
        }

        const scenario = state.scenario;
        const scenarioSettings = buildScenarioSettings(scenario);
        const payload = presetPayload || {
            action: 'ai_helper_process',
            nonce: AIHelperData.nonce,
            source: source,
            scenario: scenario,
            settings: JSON.stringify(scenarioSettings),
        };

        state.lastPayload = payload;

        toggleProcessing(true);

        $.post(AIHelperData.ajaxUrl, payload)
            .done(function(response) {
                handleResponse(response);
            })
            .fail(function() {
                showStatus(AIHelperData.strings.errorFallback, true);
            })
            .always(function() {
                toggleProcessing(false);
            });
    }

    function handleResponse(response) {
        if (!response || !response.success) {
            const message = response && response.data && response.data.message ? response.data.message : AIHelperData.strings.errorFallback;
            showStatus(message, true);
            return;
        }

        const data = response.data;
        const resultText = data.result_text || '';
        const resultHtml = data.result_html || '';

        $('#ai-helper-result-preview').html(resultHtml || $('<div>').text(resultText).html());
        $('#ai-helper-result-html').val(resultHtml);

        showStatus(AIHelperData.strings.saved, false);

        const currentCost = data.cost && data.cost.current_request_usd ? parseFloat(data.cost.current_request_usd) : 0;
        state.sessionCost += currentCost;
        const totalCost = data.cost && data.cost.total ? parseFloat(data.cost.total) : (AIHelperData.costTotal || 0) + currentCost;
        updateCostRow(currentCost, state.sessionCost, totalCost);
    }

    function updateCostRow(current, session, total) {
        $('#ai-helper-cost-current').text('Текущий запрос: $' + current.toFixed(4));
        $('#ai-helper-cost-session').text('За текущий сеанс: $' + session.toFixed(4));
        $('#ai-helper-cost-total').text('Всего: $' + total.toFixed(4));
    }

    function toggleProcessing(isProcessing) {
        const btn = $('#ai-helper-process');
        const status = $('#ai-helper-status');
        btn.prop('disabled', isProcessing);
        status.text(isProcessing ? AIHelperData.strings.processing : '');
    }

    function saveScenarioSettings() {
        const scenario = state.scenario;
        const scenarioSettings = buildScenarioSettings(scenario);

        $.post(AIHelperData.ajaxUrl, {
            action: 'ai_helper_save_scenario',
            nonce: AIHelperData.nonce,
            scenario: scenario,
            settings: JSON.stringify(scenarioSettings),
        }).done(function(response) {
            if (response && response.success) {
                showStatus(AIHelperData.strings.scenarioSaved, false);
            }
        }).fail(function() {
            showStatus(AIHelperData.strings.errorFallback, true);
        });
    }

    function saveGlobalSettings() {
        const settings = {
            global_seo: $('#ai-helper-global-seo').val(),
            style_guide: $('#ai-helper-style-guide').val(),
            temperature: $('#ai-helper-temperature').val(),
            material_type: $('#ai-helper-material-type').val(),
            language: $('#ai-helper-language').val(),
        };

        $.post(AIHelperData.ajaxUrl, {
            action: 'ai_helper_save_global',
            nonce: AIHelperData.nonce,
            settings: JSON.stringify(settings),
        }).done(function(response) {
            if (response && response.success) {
                state.globalSettings = settings;
                showStatus(AIHelperData.strings.globalSaved, false);
            }
        }).fail(function() {
            showStatus(AIHelperData.strings.errorFallback, true);
        });
    }

    function copyToClipboard(text) {
        if (!text) {
            return;
        }

        navigator.clipboard.writeText(text).then(() => {
            showStatus(AIHelperData.strings.copied, false);
        }).catch(() => {
            showStatus(AIHelperData.strings.copyFailed, true);
        });
    }

    function showStatus(message, isError) {
        const status = $('#ai-helper-status');
        status.text(message);
        status.toggleClass('error', !!isError);
        setTimeout(() => status.text(''), 3000);
    }

    $(document).ready(init);
})(jQuery);
