<?php
/**
 * Plugin Name: ИИ‑Помощник
 * Description: Интерфейс для отправки текстов на внешний API‑мост и получения результатов обработки.
 * Version: 1.0.0
 * Author: AI Helper
 * Text Domain: ai-helper
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! defined( 'AI_HELPER_VERSION' ) ) {
  define( 'AI_HELPER_VERSION', '1.0.0' );
}

define( 'AI_HELPER_PLUGIN_FILE', __FILE__ );
define( 'AI_HELPER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_HELPER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AI_HELPER_PLUGIN_DIR . 'includes/class-ai-helper-utils.php';
require_once AI_HELPER_PLUGIN_DIR . 'includes/class-ai-helper-api-client.php';
require_once AI_HELPER_PLUGIN_DIR . 'admin/class-ai-helper-settings.php';
require_once AI_HELPER_PLUGIN_DIR . 'admin/class-ai-helper-request.php';
require_once AI_HELPER_PLUGIN_DIR . 'admin/class-ai-helper-admin-ui.php';

/**
 * Main bootstrap for the plugin.
 */
class AI_Helper_Plugin {
  /**
   * AI_Helper_Plugin constructor.
   */
  public function __construct() {
    add_action( 'plugins_loaded', array( $this, 'init' ) );
  }

  /**
   * Initialize plugin hooks.
   */
  public function init() {
    load_plugin_textdomain( 'ai-helper', false, dirname( plugin_basename( AI_HELPER_PLUGIN_FILE ) ) . '/languages' );
    AI_Helper_Admin_UI::get_instance();
    AI_Helper_Request::get_instance();
  }
}

new AI_Helper_Plugin();
