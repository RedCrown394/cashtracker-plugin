<?php
namespace CFT;

defined('ABSPATH') || exit;

class Activator {
    public static function activate() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 1. First disable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        
        $charset_collate = $wpdb->get_charset_collate() . ' ENGINE=InnoDB';

        // 2. Create basic tables without foreign keys first
        $tables = [
            'wallets' => "CREATE TABLE {$wpdb->prefix}cft_wallets (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                name varchar(100) NOT NULL,
                balance decimal(15,2) NOT NULL DEFAULT 0.00,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id)
            ) $charset_collate",
            
            'transactions' => "CREATE TABLE {$wpdb->prefix}cft_transactions (
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
                KEY wallet_id (wallet_id)
            ) $charset_collate",
            
            'wallet_shares' => "CREATE TABLE {$wpdb->prefix}cft_wallet_shares (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                wallet_id mediumint(9) NOT NULL,
                owner_id bigint(20) NOT NULL,
                shared_with_id bigint(20) NOT NULL,
                permission enum('view','edit') NOT NULL DEFAULT 'view',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY wallet_user_pair (wallet_id, shared_with_id)
            ) $charset_collate"
        ];

        foreach ($tables as $table => $sql) {
            dbDelta($sql);
            error_log("CFT: Created table {$wpdb->prefix}cft_{$table}");
        }

        // 3. Now add foreign key constraints
        self::add_foreign_keys();
        
        // 4. Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        update_option('cft_db_version', '1.0');
    }

    private static function add_foreign_keys() {
        global $wpdb;
        
        // Wait a moment to ensure tables exist
        sleep(1);
        
        // 1. Transactions -> Wallets
        $wpdb->query("ALTER TABLE {$wpdb->prefix}cft_transactions 
            ADD CONSTRAINT fk_transaction_wallet
            FOREIGN KEY (wallet_id) REFERENCES {$wpdb->prefix}cft_wallets(id)
            ON DELETE CASCADE");
        
        // 2. Wallet Shares -> Wallets
        $wpdb->query("ALTER TABLE {$wpdb->prefix}cft_wallet_shares 
            ADD CONSTRAINT fk_share_wallet
            FOREIGN KEY (wallet_id) REFERENCES {$wpdb->prefix}cft_wallets(id)
            ON DELETE CASCADE");
        
        // 3. Wallet Shares -> Users (owner)
        $wpdb->query("ALTER TABLE {$wpdb->prefix}cft_wallet_shares 
            ADD CONSTRAINT fk_share_owner
            FOREIGN KEY (owner_id) REFERENCES {$wpdb->users}(ID)
            ON DELETE CASCADE");
        
        // 4. Wallet Shares -> Users (shared_with)
        $wpdb->query("ALTER TABLE {$wpdb->prefix}cft_wallet_shares 
            ADD CONSTRAINT fk_shared_with
            FOREIGN KEY (shared_with_id) REFERENCES {$wpdb->users}(ID)
            ON DELETE CASCADE");
        
        error_log("CFT: Added all foreign key constraints");
    }
}