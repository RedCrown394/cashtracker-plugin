<?php
namespace CFT;

defined('ABSPATH') || exit;

// After ABSPATH check
// define('CFT_VERSION', '1.0');
// define('CFT_PLUGIN_DIR', plugin_dir_path(__FILE__));
// define('CFT_PLUGIN_URL', plugin_dir_url(__FILE__));

// // Initialize the plugin
// function cft_init() {
//     // First verify tables exist
//     global $wpdb;
//     $wallets_table = $wpdb->prefix . 'cft_wallets';
    
//     if ($wpdb->get_var("SHOW TABLES LIKE '$wallets_table'") != $wallets_table) {
//         // If tables don't exist, run activation
//         require_once CFT_PLUGIN_DIR . 'includes/Activator.php';
//         CFT\Activator::activate();
//     }
    
    

//     // Then initialize the plugin
//     $plugin = new CFT\Main();
//     $plugin->run();
// }

// add_action('plugins_loaded', 'cft_init');

class Main {
    private $loader;
    private $shortcode;
    private $api;
    
    public function __construct() {
        $this->loader = new Loader();
        $this->shortcode = new Shortcode();
        $this->api = new API();
    }
    
    public function run() {
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_assets');
        $this->loader->add_action('rest_api_init', $this->api, 'register_routes');
        $this->loader->add_shortcode('cashflow_tracker', $this->shortcode, 'render');
        
        $this->loader->run();
    }
    
    public function enqueue_assets() {
        if (is_page() && has_shortcode(get_post()->post_content, 'cashflow_tracker')) {
            wp_enqueue_style(
                'cft-styles',
                CFT_PLUGIN_URL . 'assets/css/style.css',
                [],
                CFT_VERSION
            );
            
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '3.7.1',
                true
            );
            
            wp_enqueue_script(
                'cft-script',
                CFT_PLUGIN_URL . 'assets/js/main.js',
                ['jquery', 'chart-js'],
                CFT_VERSION,
                true
            );
            
            wp_localize_script('cft-script', 'cftData', [
                'rest_url' => rest_url('cashflow-tracker/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
                'user_id' => get_current_user_id(),
                'i18n' => [
                    'error' => __('An error occurred. Please try again.', 'cashflow-tracker'),
                    'invalid_data' => __('Invalid data provided.', 'cashflow-tracker'),
                    'insufficient_balance' => __('Insufficient balance.', 'cashflow-tracker')
                ]
            ]);
        }
    }
}