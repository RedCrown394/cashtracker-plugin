<?php
namespace CFT;

defined('ABSPATH') || exit;

class Deactivator {
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('cft_daily_cleanup');
        
        // Optionally clean up data - comment out if you want to preserve data
         self::cleanup_data();
    }
    
    private static function cleanup_data() {
        global $wpdb;

        // Remove all tables
        $tables = [
            'cft_wallet_shares',
            'cft_transactions',
            'cft_wallets'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
            error_log("Dropped table: {$wpdb->prefix}{$table}");
        }
        
        // Remove options
        delete_option('cft_db_version');
        error_log("Removed cft_db_version option");

        // Remove all tables
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cft_wallet_shares");
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cft_transactions");
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cft_wallets");
    
    
        
        // delete_option('cft_installed');
        // delete_option('cft_version');
    }
}