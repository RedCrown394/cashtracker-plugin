<?php
namespace CFT;

defined('ABSPATH') || exit;

class Activator {
    public static function activate() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Force InnoDB for foreign key support
        $charset_collate = $wpdb->get_charset_collate() . ' ENGINE=InnoDB';

        // 1. Wallets Table
        $wallets_table = $wpdb->prefix . 'cft_wallets';
        $wallet_sql = "CREATE TABLE $wallets_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(100) NOT NULL,
            balance decimal(15,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($wallet_sql);

        // 2. Transactions Table with CASCADE DELETE
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $txn_sql = "CREATE TABLE $txn_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            wallet_id mediumint(9) NOT NULL,
            description varchar(255) NOT NULL,
            amount decimal(15,2) NOT NULL,
            type enum('IN','OUT') NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY wallet_id (wallet_id),
            FOREIGN KEY (wallet_id) REFERENCES $wallets_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($txn_sql);

        update_option('cft_db_version', '1.0');
    }
}