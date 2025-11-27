jQuery(document).ready(function($) {
    const config = aiHelperConfig;
    let sessionCost = 0.0;

    // --- 1. Управление Табами и Сценариями ---
    
    // Переключение сценария через Select
    $('#ai-scenario-select').on('change', function() {
        const scenario = $(this).val();
        
        // Переключаем кнопки быстрого выбора (если совпадают)
        $('.ai-mode-btn').removeClass('active');
        $(`.ai-mode-btn[data-scenario="${scenario}"]`).addClass('active');

        // Показываем нужную панель настроек
        $('.scenario-settings').hide();
        $(`#set-${scenario}`).fadeIn(200);
    });

    // Переключение через Кнопки
    $('.ai-mode-btn').on('click', function() {
        const scenario = $(this).data('scenario');
        $('#ai-scenario-select').val(scenario).trigger('change');
    });

    // Табы Результата (Просмотр / HTML)
    $('.ai-tab').on('click', function() {
        $('.ai-tab').removeClass('active');
        $(this).addClass('active');
        
        const mode = $(this).data('tab'); // view or html
        if(mode === 'view') {
            $('#ai-result-html').hide();
            $('#ai-result-view').show();
        } else {
            $('#ai-result-view').hide();
            $('#ai-result-html').show();
        }
    });

    // --- 2. Обработка URL (Fetch Data) ---
    
    let fetchTimeout;
    $('#ai-source-input').on('input paste', function() {
        const val = $(this).val().trim();
        // Простая проверка на URL
        if(val.startsWith('http')) {
            clearTimeout(fetchTimeout);
            fetchTimeout = setTimeout(() => {
                fetchUrlData(val);
            }, 800);
        }
    });

    function fetchUrlData(url) {
        // Блокируем поле, показываем индикатор (опционально)
        $.post(config.ajaxUrl, {
            action: 'ai_helper_fetch_url',
            nonce: config.nonce,
            url: url
        }, function(res) {
            if(res.success) {
                const data = res.data;
                // Заполняем поле контента данными поста для наглядности (или храним в переменной)
                // В ТЗ сказано "автоматически извлекает". 
                // Мы можем подменить значение textarea на контент поста или оставить URL и передать данные скрыто.
                // Для простоты интерфейса, если это URL, мы просто уведомляем, что данные подтянулись, 
                // но в textarea оставляем URL (как источник).
                // Но лучше подставить контент, чтобы пользователь видел, ЧТО отправляет.
                
                let content = `Title: ${data.post_title}\n\n${data.post_content}`;
                $('#ai-source-input').val(content);
                alert(`Данные поста "${data.post_title}" успешно загружены!`);
            } else {
                // Если не URL текущего сайта, ничего страшного, считаем это просто текстом/URL для обработки
                console.log(res.data); 
            }
        });
    }

    // --- 3. Основная обработка (Process) ---

    $('#ai-process-btn').on('click', function() {
        const $btn = $(this);
        const $spinner = $('#ai-spinner');
        const textVal = $('#ai-source-input').val().trim();
        
        if(!textVal) {
            alert('Введите текст или URL!');
            return;
        }

        // Блокировка UI
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        // Сбор данных формы
        const scenario = $('#ai-scenario-select').val();
        // Собираем settings только видимого блока
        let settings = {};
        $(`#set-${scenario} input, #set-${scenario} select, #set-${scenario} textarea`).each(function() {
            const name = $(this).attr('name');
            const type = $(this).attr('type');
            if(type === 'checkbox') {
                settings[name] = $(this).is(':checked') ? 1 : 0;
            } else {
                settings[name] = $(this).val();
            }
        });

        // Определяем source_type
        const sourceType = textVal.startsWith('http') ? 'url' : 'text';

        const payload = {
            action: 'ai_helper_process',
            nonce: config.nonce,
            scenario: scenario,
            source_type: sourceType,
            text: sourceType === 'text' ? textVal : '', // Если URL, текст пустой (или наоборот, зависит от логики моста)
            url: sourceType === 'url' ? textVal : '',
            settings: settings,
            global_seo: $('#global_seo').val(),
            style_guide: $('#style_guide').val(),
            temperature: $('#temperature').val(),
            material_type: $('#material_type').val(),
            language: $('#language').val()
        };

        // Отправка
        $.post(config.ajaxUrl, payload, function(res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if(res.success) {
                const data = res.data;
                
                // Вывод результата
                $('#ai-result-view').html(data.result_text); // Предполагаем, что result_text может содержать HTML форматирование для View
                // Если result_html пришел отдельным полем:
                $('#ai-result-html').val(data.result_html || data.result_text);
                
                // Обновление цен
                if(data.cost && data.cost.current_request_usd) {
                    const cost = parseFloat(data.cost.current_request_usd);
                    sessionCost += cost;
                    
                    $('#cost-current').text('$' + cost.toFixed(4));
                    $('#cost-session').text('$' + sessionCost.toFixed(4));
                    
                    // Total обновляем сами (так как PHP обновил его в БД, но нам надо показать сразу)
                    let currentTotal = parseFloat(config.totalCost) + sessionCost; // Упрощенно, в идеале брать из ответа
                    // Лучше если API вернет новый total, но пока просто прибавим
                     // config.totalCost статичен при загрузке. Нужно прибавлять сессию к изначальному.
                     const initialTotal = parseFloat(config.totalCost);
                     $('#cost-total').text('$' + (initialTotal + sessionCost).toFixed(4));
                }

            } else {
                alert('Ошибка: ' + res.data);
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            alert('Ошибка сервера или сети.');
        });
    });

    // --- 4. Действия с результатом ---

    $('#btn-copy-text').on('click', function() {
        const text = $('#ai-result-html').val().replace(/<[^>]*>?/gm, ''); // Strip tags for text copy
        navigator.clipboard.writeText(text).then(() => alert('Текст скопирован'));
    });

    $('#btn-copy-html').on('click', function() {
        const html = $('#ai-result-html').val();
        navigator.clipboard.writeText(html).then(() => alert('HTML скопирован'));
    });

    $('#btn-retry').on('click', function() {
        $('#ai-process-btn').trigger('click');
    });

    // --- 5. Сохранение настроек ---
    $('#ai-save-scenario').on('click', function() {
        const scenario = $('#ai-scenario-select').val();
        let settings = {};
        $(`#set-${scenario} input, #set-${scenario} select, #set-${scenario} textarea`).each(function() {
             const name = $(this).attr('name');
             if($(this).attr('type') === 'checkbox') settings[name] = $(this).is(':checked') ? 1 : 0;
             else settings[name] = $(this).val();
        });

        const global = {
            global_seo: $('#global_seo').val(),
            style_guide: $('#style_guide').val(),
            temperature: $('#temperature').val(),
            material_type: $('#material_type').val(),
            language: $('#language').val()
        };

        $.post(config.ajaxUrl, {
            action: 'ai_helper_save_settings',
            nonce: config.nonce,
            scenario: scenario,
            settings: settings,
            global: global
        }, function(res) {
            alert(res.data);
        });
    });
});
