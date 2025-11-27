<?php
/**
 * Plugin Name: ИИ‑Помощник
 * Description: Админ‑панель для формирования запросов к внешнему AI‑мосту без собственной ИИ‑логики.
 * Version: 0.1.0
 * Author: AI Helper
 * Text Domain: ai-helper
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! defined( 'AI_HELPER_VERSION' ) ) {
define( 'AI_HELPER_VERSION', '0.1.0' );
}

if ( ! defined( 'AI_HELPER_PLUGIN_DIR' ) ) {
define( 'AI_HELPER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'AI_HELPER_PLUGIN_URL' ) ) {
define( 'AI_HELPER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once AI_HELPER_PLUGIN_DIR . 'includes/class-ai-helper-utils.php';
require_once AI_HELPER_PLUGIN_DIR . 'includes/class-ai-helper-api-client.php';
require_once AI_HELPER_PLUGIN_DIR . 'admin/class-ai-helper-settings.php';
require_once AI_HELPER_PLUGIN_DIR . 'admin/class-ai-helper-request.php';
require_once AI_HELPER_PLUGIN_DIR . 'admin/class-ai-helper-admin-ui.php';

add_action( 'plugins_loaded', 'ai_helper_bootstrap' );

/**
 * Bootstrap plugin classes.
 *
 * @return void
 */
function ai_helper_bootstrap() {
new AI_Helper_Admin_UI();
new AI_Helper_Request();
}
