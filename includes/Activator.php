<?php
namespace CFT;

defined('ABSPATH') || exit;

class Activator {
    public static function activate() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Wallets table
        $table_name = $wpdb->prefix . 'cft_wallets';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(100) NOT NULL,
            balance decimal(15,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        error_log("Wallet table creation result: " . print_r($result, true));
        
        // Transactions table
        $table_name = $wpdb->prefix . 'cft_transactions';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            wallet_id mediumint(9) NOT NULL,
            description varchar(255) NOT NULL,
            amount decimal(15,2) NOT NULL,
            type enum('IN','OUT') NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY wallet_id (wallet_id)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        error_log("Transaction table creation result: " . print_r($result, true));
        
        // Add default wallet for existing users if first activation
        if (!get_option('cft_installed')) {
            $users = get_users();
            foreach ($users as $user) {
                $wpdb->insert(
                    $wpdb->prefix . 'cft_wallets',
                    [
                        'user_id' => $user->ID,
                        'name' => 'Cash',
                        'balance' => 0.00
                    ]
                );
            }
            
            update_option('cft_installed', time());
            update_option('cft_version', CFT_VERSION);
        }
    }
}