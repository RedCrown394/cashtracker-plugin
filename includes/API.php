<?php
namespace CFT;

defined('ABSPATH') || exit;

class API {
    public function register_routes() {

        //Handle Wallets
        register_rest_route('cashflow-tracker/v1', '/wallets', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_wallets'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_wallet'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'name' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return !empty($param);
                        }
                    ],
                    'balance' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ]
                ]
            ]
        ]);
        
        //Handle Transactions
        register_rest_route('cashflow-tracker/v1', '/transactions', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_transactions'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_transaction'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'desc' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return !empty($param);
                        }
                    ],
                    'amount' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        }
                    ],
                    'wallet_id' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ],
                    'type' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return in_array($param, ['IN', 'OUT']);
                        }
                    ]
                ]
            ]
        ]);
        
        //Handle Summary
        register_rest_route('cashflow-tracker/v1', '/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_summary'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        //Handle or Fetch one specific wallet by ID
        register_rest_route('cashflow-tracker/v1', '/wallets/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_wallet'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_wallet'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_wallet'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
    }
    
    //Check if user is logged in
    public function check_permission() {
        return is_user_logged_in();
    }
    
    //Fetch all wallets for the current user
    public function get_wallets() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'cft_wallets';
        
        $wallets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
        
        return rest_ensure_response($wallets);
    }

    //Fetch one specific wallet by ID
    public function get_wallet($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'cft_wallets';
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $request['id'], get_current_user_id()
        ));
        
        if (!$wallet) {
            return new WP_Error('not_found', 'Wallet not found', ['status' => 404]);
        }
        
        return rest_ensure_response($wallet);
    }

    //Update a specific wallet by ID
    public function update_wallet($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'cft_wallets';
        
        $result = $wpdb->update(
            $table,
            [
                'name' => sanitize_text_field($request['name']),
                'balance' => floatval($request['balance'])
            ],
            [
                'id' => $request['id'],
                'user_id' => get_current_user_id()
            ],
            ['%s', '%f'],
            ['%d', '%d']
        );
        
        if (false === $result) {
            return new WP_Error('db_error', 'Could not update wallet', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }

    // //Delete a specific wallet by ID
    // public function delete_wallet($request) {
    //     global $wpdb;
    //     $table = $wpdb->prefix . 'cft_wallets';
        
    //     // First verify wallet belongs to user
    //     $exists = $wpdb->get_var($wpdb->prepare(
    //         "SELECT id FROM $table WHERE id = %d AND user_id = %d",
    //         $request['id'], get_current_user_id()
    //     ));
        
    //     if (!$exists) {
    //         return new WP_Error('not_found', 'Wallet not found', ['status' => 404]);
    //     }
        
    //     $result = $wpdb->delete(
    //         $table,
    //         ['id' => $request['id']],
    //         ['%d']
    //     );
        
    //     if (false === $result) {
    //         return new WP_Error('db_error', 'Could not delete wallet', ['status' => 500]);
    //     }
        
    //     return rest_ensure_response(['success' => true]);
    // }

    public function delete_wallet($request) {
        global $wpdb;
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $txn_table = $wpdb->prefix . 'cft_transactions';
        
        $user_id = get_current_user_id();
        $wallet_id = $request['id'];
        $delete_transactions = $request->get_param('delete_transactions') === 'true';
        
        // Verify wallet belongs to user
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $wallet_table WHERE id = %d AND user_id = %d",
            $wallet_id, $user_id
        ));
        
        if (!$wallet) {
            return new WP_Error('not_found', 'Wallet not found', ['status' => 404]);
        }
        
        // Handle transactions based on user choice
        if ($delete_transactions) {
            // Option 1: Delete all related transactions
            $wpdb->delete(
                $txn_table,
                ['wallet_id' => $wallet_id],
                ['%d']
            );
        } else {
            // Option 2: Mark transactions as belonging to deleted wallet
            $wpdb->query($wpdb->prepare(
                "UPDATE $txn_table 
                SET wallet_id = 0, 
                    description = CONCAT(description, %s) 
                WHERE wallet_id = %d",
                ' (Deleted Wallet)',
                $wallet_id
            ));
        }
        
        // Delete the wallet
        $result = $wpdb->delete(
            $wallet_table,
            ['id' => $wallet_id],
            ['%d']
        );
        
        if (false === $result) {
            return new WP_Error('db_error', 'Could not delete wallet', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
        
    private function verify_tables() {
    global $wpdb;
    
    $wallets_table = $wpdb->prefix . 'cft_wallets';
    $transactions_table = $wpdb->prefix . 'cft_transactions';
    
    // Check if tables exist
    if ($wpdb->get_var("SHOW TABLES LIKE '$wallets_table'") != $wallets_table ||
        $wpdb->get_var("SHOW TABLES LIKE '$transactions_table'") != $transactions_table) {
        
        error_log("Cash Flow Tracker tables missing - attempting to recreate");
        
        try {
            // Use fully qualified class name
            \CFT\Activator::activate();
            
            // Verify creation was successful
            if ($wpdb->get_var("SHOW TABLES LIKE '$wallets_table'") != $wallets_table) {
                throw new \Exception("Failed to create wallet table");
            }
            
            error_log("Tables successfully recreated");
        } catch (\Exception $e) {
            error_log("Table recreation failed: " . $e->getMessage());
            wp_die('Cash Flow Tracker tables could not be created. Please deactivate and reactivate the plugin.');
        }
    }
}

    public function add_wallet($request) {
    global $wpdb;
    
    // Verify tables exist
    $this->verify_tables();
    
    // Get and validate parameters
    $params = $request->get_params();
    $user_id = get_current_user_id();
    $name = sanitize_text_field($params['name']);
    $balance = floatval($params['balance']);
    
    // Validate input
    if (empty($name)) {
        return new \WP_Error('invalid_data', __('Wallet name cannot be empty', 'cashflow-tracker'), ['status' => 400]);
    }
    
    $table = $wpdb->prefix . 'cft_wallets';
    
    // Insert with explicit data formats
    $result = $wpdb->insert(
        $table,
        [
            'user_id' => $user_id,
            'name' => $name,
            'balance' => $balance
        ],
        [
            '%d', // user_id is integer
            '%s', // name is string
            '%f'  // balance is float
        ]
    );
    
    if (false === $result) {
        error_log("DB Error: " . $wpdb->last_error);
        error_log("Last Query: " . $wpdb->last_query);
        
        return new \WP_Error('db_error', __('Database error occurred', 'cashflow-tracker'), [
            'status' => 500,
            'db_error' => $wpdb->last_error
        ]);
    }
    
    $wallet_id = $wpdb->insert_id;
    
    // Add initial transaction if balance > 0
    if ($balance > 0) {
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $wpdb->insert(
            $txn_table,
            [
                'user_id' => $user_id,
                'wallet_id' => $wallet_id,
                'description' => __('Initial balance', 'cashflow-tracker'),
                'amount' => $balance,
                'type' => 'IN'
            ],
            [
                '%d', // user_id
                '%d', // wallet_id
                '%s', // description
                '%f', // amount
                '%s'  // type
            ]
        );
    }
    
    return rest_ensure_response([
        'success' => true,
        'id' => $wallet_id,
        'balance' => $balance
    ]);
}
    
    public function get_transactions($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $wallet_id = $request->get_param('wallet_id');
        
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        
        $query = "SELECT t.*, w.name as wallet_name 
                  FROM $txn_table t
                  JOIN $wallet_table w ON t.wallet_id = w.id
                  WHERE t.user_id = %d";
        
        $params = [$user_id];
        
        if ($wallet_id) {
            $query .= " AND t.wallet_id = %d";
            $params[] = $wallet_id;
        }
        
        $query .= " ORDER BY t.created_at DESC LIMIT 50";
        
        $transactions = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        return rest_ensure_response($transactions);
    }
    
    public function add_transaction($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $wallet_id = intval($request['wallet_id']);
        $amount = floatval($request['amount']);
        $type = $request['type'];
        $desc = sanitize_text_field($request['desc']);
        
        // Check wallet exists and belongs to user
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $wallet_table WHERE id = %d AND user_id = %d",
            $wallet_id, $user_id
        ));
        
        if (!$wallet) {
            return new \WP_Error('invalid_wallet', __('Invalid wallet selected.', 'cashflow-tracker'), ['status' => 400]);
        }
        
        // Check balance for cash out
        if ($type === 'OUT' && $wallet->balance < $amount) {
            return new \WP_Error('insufficient_balance', __('Insufficient balance in wallet.', 'cashflow-tracker'), ['status' => 400]);
        }
        
        // Add transaction
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $txn_result = $wpdb->insert($txn_table, [
            'user_id' => $user_id,
            'wallet_id' => $wallet_id,
            'description' => $desc,
            'amount' => $amount,
            'type' => $type
        ]);
        
        if (!$txn_result) {
            return new \WP_Error('db_error', __('Could not add transaction.', 'cashflow-tracker'), ['status' => 500]);
        }
        
        // Update wallet balance
        $new_balance = $type === 'IN' ? $wallet->balance + $amount : $wallet->balance - $amount;
        $wpdb->update($wallet_table, 
            ['balance' => $new_balance],
            ['id' => $wallet_id]
        );
        
        return rest_ensure_response([
            'success' => true,
            'new_balance' => $new_balance
        ]);
    }
    
    public function get_summary() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $txn_table = $wpdb->prefix . 'cft_transactions';
        
        // Get total balance
        $total_balance = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(balance) FROM $wallet_table WHERE user_id = %d",
            $user_id
        )) ?: 0;
        
        // Get cash in/out totals
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as total_out
             FROM $txn_table
             WHERE user_id = %d",
            $user_id
        ));
        
        // Get monthly data for chart
        $monthly_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m') as month,
                SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as expense
             FROM $txn_table
             WHERE user_id = %d
             GROUP BY month
             ORDER BY month DESC
             LIMIT 12",
            $user_id
        ), ARRAY_A);
        
        return rest_ensure_response([
            'total_balance' => floatval($total_balance),
            'total_in' => floatval($totals->total_in ?: 0),
            'total_out' => floatval($totals->total_out ?: 0),
            'monthly_data' => array_reverse($monthly_data)
        ]);
    }
}