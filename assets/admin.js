(function ($) {
'use strict';

const state = {
currentScenario: 'links_only',
lastPayload: null,
costSession: 0,
costTotal: parseFloat(aiHelperData.costTotal || 0),
lastParsedUrl: '',
isParsing: false,
lastPostData: null,
};

function setScenario(scenario) {
state.currentScenario = scenario;
$('#ai-helper-scenario').val(scenario);
$('input[name="ai-helper-mode"]').prop('checked', false);
$(`input[name="ai-helper-mode"][value="${scenario}"]`).prop('checked', true);

$('.ai-helper-scenario-panel').addClass('hidden');
$(`.ai-helper-scenario-panel[data-scenario="${scenario}"]`).removeClass('hidden');
}

function collectSettings(scenario) {
const settings = {};
const panel = $(`.ai-helper-scenario-panel[data-scenario="${scenario}"]`);
panel.find('[data-setting]').each(function () {
const key = $(this).data('setting');
if ($(this).attr('type') === 'checkbox') {
settings[key] = $(this).is(':checked') ? 'on' : '';
} else {
settings[key] = $(this).val();
}
});
return settings;
}

function updateCosts(current, total) {
state.costSession += current;
state.costTotal = total;
$('#ai-helper-cost-current').text(current.toFixed(4));
$('#ai-helper-cost-session').text(state.costSession.toFixed(4));
$('#ai-helper-cost-total').text(state.costTotal.toFixed(4));
}

function showProcessing(isProcessing, message) {
$('#ai-helper-process').prop('disabled', isProcessing);
$('#ai-helper-processing').text(isProcessing ? (message || aiHelperData.i18n.processing) : '');
}

function renderResult(response) {
const viewContainer = $('#ai-helper-tab-view');
const htmlContainer = $('#ai-helper-tab-html pre');
const html = response.result_html || '';
const text = response.result_text || '';
viewContainer.html(html || $('<div>').text(text).html());
htmlContainer.text(html || text);
updateCosts(response.cost.current || 0, response.cost.total || state.costTotal);
state.lastPayload = response.payload;
}

function displayError(message) {
window.alert(message || aiHelperData.i18n.apiUnavailable);
}

function isUrl(value) {
return /^https?:\/\//i.test(value || '');
}

function parseUrl(url) {
if (state.isParsing || !isUrl(url) || url === state.lastParsedUrl) {
return;
}
state.isParsing = true;
state.lastParsedUrl = url;
$('#ai-helper-url-meta').text(aiHelperData.i18n.urlParsing);

$.post(
aiHelperData.ajaxUrl,
{
action: 'ai_helper_parse_url',
nonce: aiHelperData.nonce,
url,
},
function (resp) {
state.isParsing = false;
if (!resp || !resp.success) {
$('#ai-helper-url-meta').text(resp && resp.data && resp.data.message ? resp.data.message : aiHelperData.i18n.parseError);
return;
}
state.lastPostData = resp.data;
$('#ai-helper-source').val(resp.data.post_content || '');
const meta = [];
if (resp.data.post_title) {
meta.push('<strong>Заголовок:</strong> ' + resp.data.post_title);
}
if (resp.data.excerpt) {
meta.push('<strong>Выдержка:</strong> ' + resp.data.excerpt);
}
if (resp.data.categories && resp.data.categories.length) {
meta.push('<strong>Категории:</strong> ' + resp.data.categories.join(', '));
}
if (resp.data.tags && resp.data.tags.length) {
meta.push('<strong>Теги:</strong> ' + resp.data.tags.join(', '));
}
if (resp.data.rank_math && resp.data.rank_math.focus_keyword) {
meta.push('<strong>Rank Math:</strong> ' + resp.data.rank_math.focus_keyword);
}
$('#ai-helper-url-meta').html(meta.join('<br>'));
}
).fail(function () {
state.isParsing = false;
$('#ai-helper-url-meta').text(aiHelperData.i18n.parseError);
});
}

function processRequest(payload) {
showProcessing(true);
$.post(
aiHelperData.ajaxUrl,
{
action: 'ai_helper_process',
nonce: aiHelperData.nonce,
...payload,
},
function (resp) {
showProcessing(false);
if (!resp || !resp.success) {
displayError(resp && resp.data && resp.data.message ? resp.data.message : aiHelperData.i18n.apiUnavailable);
return;
}
renderResult(resp.data);
}
).fail(function () {
showProcessing(false);
displayError(aiHelperData.i18n.apiUnavailable);
});
}

function buildPayload() {
const textInput = $('#ai-helper-source').val();
const scenario = state.currentScenario;
const settings = collectSettings(scenario);
const isSourceUrl = isUrl(textInput);
const globalSettings = {
global_seo_goal: $('#ai-helper-global-goal').val(),
style_guide: $('#ai-helper-style-guide').val(),
temperature: $('#ai-helper-temperature').val(),
material_type: $('#ai-helper-material-type').val(),
language: $('#ai-helper-language').val(),
};
const payload = {
scenario,
text: isSourceUrl ? '' : textInput,
url: isSourceUrl ? textInput : '',
settings,
global_settings: globalSettings,
};

if (!textInput) {
throw new Error(aiHelperData.i18n.emptyField);
}

if (!isSourceUrl && textInput.length > 20000) {
throw new Error(aiHelperData.i18n.overLimit);
}

return payload;
}

$(document).ready(function () {
setScenario('links_only');
const firstPanel = $('.ai-helper-scenario-panel').first();
if (firstPanel.length) {
firstPanel.removeClass('hidden');
}
updateCosts(0, state.costTotal);

$('#ai-helper-scenario').on('change', function () {
setScenario($(this).val());
});

$('input[name="ai-helper-mode"]').on('change', function () {
setScenario($(this).val());
});

$('.ai-helper-tab').on('click', function (e) {
e.preventDefault();
const target = $(this).data('tab');
$('.ai-helper-tab').removeClass('active');
$(this).addClass('active');
$('.ai-helper-tab-content').addClass('hidden');
$(`#ai-helper-tab-${target}`).removeClass('hidden');
});

$('#ai-helper-source').on('blur', function () {
const value = $(this).val();
if (isUrl(value)) {
parseUrl(value);
}
});

$('#ai-helper-process').on('click', function (e) {
e.preventDefault();
try {
const payload = buildPayload();
processRequest(payload);
} catch (error) {
displayError(error.message);
}
});

$('#ai-helper-repeat').on('click', function (e) {
e.preventDefault();
if (state.lastPayload) {
processRequest(state.lastPayload);
}
});

$('#ai-helper-copy-text').on('click', function (e) {
e.preventDefault();
const text = $('#ai-helper-tab-view').text();
navigator.clipboard.writeText(text).then(function () {
window.alert(aiHelperData.i18n.copyText);
});
});

$('#ai-helper-copy-html').on('click', function (e) {
e.preventDefault();
const html = $('#ai-helper-tab-html pre').text();
navigator.clipboard.writeText(html).then(function () {
window.alert(aiHelperData.i18n.copyHtml);
});
});

$('#ai-helper-save-scenario').on('click', function (e) {
e.preventDefault();
const scenario = state.currentScenario;
const settings = collectSettings(scenario);
$.post(
aiHelperData.ajaxUrl,
{
action: 'ai_helper_save_scenario',
nonce: aiHelperData.nonce,
scenario,
settings,
},
function (resp) {
if (resp && resp.success) {
window.alert(aiHelperData.i18n.scenarioSaved);
}
}
);
});
});
})(jQuery);
