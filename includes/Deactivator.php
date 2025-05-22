<?php
namespace CFT;

defined('ABSPATH') || exit;

class Deactivator {
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('cft_daily_cleanup');
        
        // Optionally clean up data - comment out if you want to preserve data
        // self::cleanup_data();
    }
    
    private static function cleanup_data() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cft_wallets");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cft_transactions");
        
        delete_option('cft_installed');
        delete_option('cft_version');
    }
}