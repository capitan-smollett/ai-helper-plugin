<?php
/**
 * Plugin Name:       ИИ‑Помощник
 * Plugin URI:        https://example.com/ai-helper
 * Description:       Панель администратора для отправки текстов во внешний API‑мост и получения SEO/контентных результатов.
 * Version:           1.0.0
 * Author:            AI Helper Team
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ai-helper
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

if ( ! defined( 'AI_HELPER_VERSION' ) ) {
    define( 'AI_HELPER_VERSION', '1.0.0' );
}

require_once AI_HELPER_PATH . 'includes/class-ai-helper-utils.php';
require_once AI_HELPER_PATH . 'includes/class-ai-helper-settings.php';
require_once AI_HELPER_PATH . 'includes/class-ai-helper-api-client.php';
require_once AI_HELPER_PATH . 'includes/class-ai-helper-request.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-admin-ui.php';

/**
 * Bootstrap plugin.
 */
class AI_Helper_Plugin {
    /**
     * Admin UI instance.
     *
     * @var AI_Helper_Admin_UI
     */
    protected $admin_ui;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->admin_ui = new AI_Helper_Admin_UI();
        $this->hooks();
    }

    /**
     * Register hooks.
     */
    protected function hooks() {
        add_action( 'admin_init', array( 'AI_Helper_Request', 'register_ajax' ) );
        add_action( 'admin_menu', array( $this->admin_ui, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this->admin_ui, 'enqueue_assets' ) );
    }
}

new AI_Helper_Plugin();
