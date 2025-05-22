<?php
/*
Plugin Name: Cash Flow Tracker
Description: A personal finance management tool for tracking income and expenses.
Version: 1.0
Author: Renzo Lim
Text Domain: cashflow-tracker
*/

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('CFT_VERSION', '1.0');
define('CFT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check for required PHP version
if (version_compare(PHP_VERSION, '7.0', '<')) {
    add_action('admin_notices', 'cft_php_version_notice');
    return;
}

function cft_php_version_notice() {
    echo '<div class="error"><p>';
    printf(
        __('Cash Flow Tracker requires PHP 7.0 or higher. Your server is running PHP %s. Please upgrade.', 'cashflow-tracker'),
        PHP_VERSION
    );
    echo '</p></div>';
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'CFT\\';
    $base_dir = CFT_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function cft_init() {
    // Register activation/deactivation hooks
    register_activation_hook(__FILE__, ['CFT\\Activator', 'activate']);
    register_deactivation_hook(__FILE__, ['CFT\\Deactivator', 'deactivate']);
    
    // Initialize the main plugin class
    $plugin = new CFT\Main();
    $plugin->run();
}

add_action('plugins_loaded', 'cft_init');