<?php
/**
 * Plugin Name: ИИ‑Помощник для контента и SEO
 * Plugin URI: https://example.com/ai-helper
 * Description: Интерфейс для внешнего API-моста ИИ-обработки текстов (сценарии: SEO, ссылки, гайды, Rank Math)
 * Version: 1.0.0
 * Author: DevTeam
 * Author URI: https://example.com
 * License: GPL-2.0+
 * Text Domain: ai-helper
 *
 * @package AI_Helper
 */

defined( 'ABSPATH' ) || exit;

// Константы
define( 'AI_HELPER_VERSION', '1.0.0' );
define( 'AI_HELPER_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_HELPER_URL', plugin_dir_url( __FILE__ ) );

// Автозагрузка (простая)
require_once AI_HELPER_PATH . 'includes/class-ai-helper-utils.php';
require_once AI_HELPER_PATH . 'includes/class-ai-helper-api-client.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-settings.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-request.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-admin-ui.php';

// Инициализация
add_action( 'plugins_loaded', 'ai_helper_init' );
function ai_helper_init() {
	if ( is_admin() ) {
		$ui      = new AI_Helper_Admin_UI();
		$settings = new AI_Helper_Settings();
		$request  = new AI_Helper_Request();

		$ui->init();
		$settings->init();
		$request->init();
	}
}
