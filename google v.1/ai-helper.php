<?php
/**
 * Plugin Name: AI Helper
 * Description: Интерфейс ИИ-Помощника для контента и SEO.
 * Version: 1.2
 * Author: Your Name
 * Text Domain: ai-helper
 */

defined( 'ABSPATH' ) || exit;

// Константы
define( 'AI_HELPER_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_HELPER_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_HELPER_API_ENDPOINT', 'https://api.your-bridge-domain.com/v1/process' ); // Заглушка, меняется в настройках или коде
define( 'AI_HELPER_API_KEY', 'YOUR_SECRET_KEY' );

// Подключение классов
require_once AI_HELPER_PATH . 'admin/class-ai-helper-admin.php';
require_once AI_HELPER_PATH . 'admin/class-ai-helper-ajax.php';

// Инициализация
function ai_helper_init() {
    new AI_Helper_Admin();
    new AI_Helper_Ajax();
}
add_action( 'plugins_loaded', 'ai_helper_init' );

// Активация (создание опций по умолчанию)
register_activation_hook( __FILE__, function() {
    if ( ! get_option( 'ai_helper_global_settings' ) ) {
        update_option( 'ai_helper_global_settings', [
            'global_seo'   => '',
            'style_guide'  => '',
            'temperature'  => 0.7,
            'material_type'=> 'news',
            'language'     => 'ru-RU'
        ]);
    }
    if ( ! get_option( 'ai_helper_cost_total' ) ) {
        update_option( 'ai_helper_cost_total', 0.00 );
    }
});
