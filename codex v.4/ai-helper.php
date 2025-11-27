<?php
/**
 * Plugin Name: AI Helper
 * Description: Admin interface for AI content assistant.
 * Version: 1.0.0
 * Author: OpenAI
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! defined( 'AI_HELPER_PATH' ) ) {
define( 'AI_HELPER_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'AI_HELPER_URL' ) ) {
define( 'AI_HELPER_URL', plugin_dir_url( __FILE__ ) );
}

require_once AI_HELPER_PATH . 'includes/class-ai-helper-utils.php';
require_once AI_HELPER_PATH . 'includes/class-ai-helper-api-client.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-settings.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-request.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-admin-ui.php';

/**
 * Bootstrap the plugin.
 */
function ai_helper_init() {
AI_Helper_Admin_UI::get_instance();
AI_Helper_Request::get_instance();
}
add_action( 'plugins_loaded', 'ai_helper_init' );
