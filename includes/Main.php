<?php
namespace CFT;

defined('ABSPATH') || exit;


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