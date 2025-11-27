(function($){
    const state = {
        currentScenario: 'links_only',
        sessionCost: 0,
        sourceUrl: '',
        postData: null,
    };

    function init(){
        bindScenarioSwitching();
        bindTabs();
        bindProcess();
        bindCopyButtons();
        bindSaveButtons();
        bindSourceWatcher();
        bindRepeat();
        populateScenarioSettings(state.currentScenario);
        updateProcessAvailability();
    }

    function bindScenarioSwitching(){
        $('.ai-helper-mode').on('click', function(e){
            e.preventDefault();
            const scenario = $(this).data('scenario');
            $('#ai-helper-scenario').val(scenario).trigger('change');
        });

        $('#ai-helper-scenario').on('change', function(){
            state.currentScenario = $(this).val();
            $('.ai-helper-scenario-panel').addClass('hidden');
            $('.ai-helper-scenario-panel[data-scenario="'+state.currentScenario+'"]').removeClass('hidden');
            $('.ai-helper-mode').removeClass('active');
            $('.ai-helper-mode[data-scenario="'+state.currentScenario+'"]').addClass('active');
            populateScenarioSettings(state.currentScenario);
        }).trigger('change');
    }

    function populateScenarioSettings(scenario){
        const settings = (AIHelperData.scenarioSettings && AIHelperData.scenarioSettings[scenario]) || {};
        const panel = $('.ai-helper-scenario-panel[data-scenario="'+scenario+'"]').get(0);
        if(!panel){
            return;
        }
        $(panel).find('input[type="text"], input[type="number"], textarea').each(function(){
            const name = $(this).attr('name');
            if(settings[name] !== undefined){
                $(this).val(settings[name]);
            }
        });
    }

    function bindTabs(){
        $('.ai-helper-tab').on('click', function(e){
            e.preventDefault();
            const tab = $(this).data('tab');
            $('.ai-helper-tab').removeClass('active');
            $(this).addClass('active');
            if(tab === 'preview'){
                $('#ai-helper-result-preview').removeClass('hidden');
                $('#ai-helper-result-html').addClass('hidden');
            } else {
                $('#ai-helper-result-preview').addClass('hidden');
                $('#ai-helper-result-html').removeClass('hidden');
            }
        });
    }

    function collectScenarioSettings(){
        const panel = $('.ai-helper-scenario-panel[data-scenario="'+state.currentScenario+'"]').first();
        const data = {};
        panel.find('input[type="text"], input[type="number"], textarea').each(function(){
            const name = $(this).attr('name');
            data[name] = $(this).val();
        });
        return data;
    }

    function collectGlobalSettings(){
        return {
            global_seo: $('#ai-helper-global-seo').val(),
            style_guide: $('#ai-helper-style-guide').val(),
            temperature: $('#ai-helper-temperature').val(),
            material_type: $('#ai-helper-material-type').val(),
            language: $('#ai-helper-language').val(),
        };
    }

    function bindProcess(){
        $('#ai-helper-process').on('click', function(e){
            e.preventDefault();
            sendProcessRequest();
        });
    }

    function detectSourceType(value){
        if(state.sourceUrl){
            return 'url';
        }
        const urlPattern = /^https?:\/\//i;
        return urlPattern.test(value.trim()) ? 'url' : 'text';
    }

    function bindSourceWatcher(){
        $('#ai-helper-source').on('input', function(){
            const value = $(this).val().trim();
            if(value === ''){
                state.sourceUrl = '';
                state.postData = null;
            } else if(/^https?:\/\//i.test(value)){
                state.sourceUrl = value;
            }
            updateProcessAvailability();
        }).on('blur', function(){
            const value = $(this).val();
            if(detectSourceType(value) === 'url'){
                fetchPostFromUrl(value);
            }
        });
    }

    function updateProcessAvailability(){
        const value = $('#ai-helper-source').val().trim();
        const disabled = value.length === 0 || value.length > 20000;
        $('#ai-helper-process').prop('disabled', disabled);
        if(value.length > 20000){
            $('.ai-helper-status').text('Превышен лимит 20 000 символов');
        } else {
            $('.ai-helper-status').text('');
        }
    }

    function fetchPostFromUrl(url){
        state.sourceUrl = url;
        $.post(AIHelperData.ajaxUrl, {
            action: 'ai_helper_fetch_post',
            nonce: AIHelperData.nonce,
            url: url
        }).done(function(response){
            if(response.success && response.data.post){
                state.postData = response.data.post;
                const content = response.data.post.content || '';
                if(content){
                    $('#ai-helper-source').val(content);
                    updateProcessAvailability();
                }
            } else if(response.data && response.data.message){
                $('.ai-helper-status').text(response.data.message);
            }
        });
    }

    function sendProcessRequest(){
        const sourceValue = $('#ai-helper-source').val();
        const sourceType = detectSourceType(sourceValue);
        const payload = {
            action: 'ai_helper_process',
            nonce: AIHelperData.nonce,
            scenario: state.currentScenario,
            text: sourceValue,
            url: state.sourceUrl || (sourceType === 'url' ? sourceValue : ''),
            source_type: sourceType,
            settings: collectScenarioSettings(),
            global: collectGlobalSettings()
        };

        toggleProcessing(true);

        $.post(AIHelperData.ajaxUrl, payload).done(function(response){
            if(response.success){
                renderResult(response.data);
                updateCosts(response.data.cost || {});
            } else if(response.data && response.data.message){
                $('.ai-helper-status').text(response.data.message);
            } else {
                $('.ai-helper-status').text('Связь с ИИ временно недоступна');
            }
        }).fail(function(){
            $('.ai-helper-status').text('Связь с ИИ временно недоступна');
        }).always(function(){
            toggleProcessing(false);
        });
    }

    function toggleProcessing(isProcessing){
        $('#ai-helper-process').prop('disabled', isProcessing);
        const text = isProcessing ? AIHelperData.i18n.processing : '';
        $('.ai-helper-status').text(text);
    }

    function renderResult(data){
        $('#ai-helper-result-preview').html(data.result_html || '');
        $('#ai-helper-result-html').text(data.result_html || '');
        if(data.result_text){
            $('#ai-helper-result-preview').attr('data-text', data.result_text);
        }
    }

    function updateCosts(cost){
        const current = parseFloat(cost.current_request_usd || 0) || 0;
        state.sessionCost += current;
        $('#ai-helper-cost-current').text(current.toFixed(4));
        $('#ai-helper-cost-session').text(state.sessionCost.toFixed(4));
        if(cost.total_usd !== undefined){
            $('#ai-helper-cost-total').text(parseFloat(cost.total_usd).toFixed(4));
        }
    }

    function bindCopyButtons(){
        $('#ai-helper-copy-text').on('click', function(e){
            e.preventDefault();
            const text = $('#ai-helper-result-preview').attr('data-text') || '';
            navigator.clipboard.writeText(text).then(function(){
                $('.ai-helper-status').text(AIHelperData.i18n.copyText);
            });
        });

        $('#ai-helper-copy-html').on('click', function(e){
            e.preventDefault();
            const html = $('#ai-helper-result-html').text();
            navigator.clipboard.writeText(html).then(function(){
                $('.ai-helper-status').text(AIHelperData.i18n.copyHtml);
            });
        });
    }

    function bindSaveButtons(){
        $('#ai-helper-save-scenario').on('click', function(e){
            e.preventDefault();
            $.post(AIHelperData.ajaxUrl, {
                action: 'ai_helper_save_scenario',
                nonce: AIHelperData.nonce,
                scenario: state.currentScenario,
                settings: collectScenarioSettings()
            }).done(function(response){
                if(response.success){
                    $('.ai-helper-status').text(response.data.message);
                }
            });
        });

        $('#ai-helper-save-global').on('click', function(e){
            e.preventDefault();
            $.post(AIHelperData.ajaxUrl, {
                action: 'ai_helper_save_global',
                nonce: AIHelperData.nonce,
                global: collectGlobalSettings()
            }).done(function(response){
                if(response.success){
                    $('.ai-helper-status').text(response.data.message);
                }
            });
        });
    }

    function bindRepeat(){
        $('#ai-helper-repeat').on('click', function(e){
            e.preventDefault();
            $.post(AIHelperData.ajaxUrl, {
                action: 'ai_helper_repeat',
                nonce: AIHelperData.nonce
            }).done(function(response){
                if(response.success){
                    renderResult(response.data);
                    updateCosts(response.data.cost || {});
                } else if(response.data && response.data.message){
                    $('.ai-helper-status').text(response.data.message || AIHelperData.i18n.noRequest);
                }
            }).fail(function(){
                $('.ai-helper-status').text('Связь с ИИ временно недоступна');
            });
        });
    }

    $(document).ready(init);
})(jQuery);
