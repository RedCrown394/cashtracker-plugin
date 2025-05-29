<?php
namespace CFT;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;


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

        //Handle Update, delete Transaction History
        register_rest_route('cashflow-tracker/v1', '/transactions/(?P<id>\d+)', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'update_transaction'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_transaction'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ] 
        ]);


        // Add wallet endpoint for the dropdown for edit modal
        add_action('rest_api_init', function() {
            register_rest_route('cft/v1', '/wallets', [
                'methods' => 'GET',
                'callback' => [$this, 'get_user_wallets'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                }
            ]);
        });
        
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

    // Delete transaction handler
    public function delete_transaction(\WP_REST_Request $request) {
        global $wpdb;
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        
        $user_id = get_current_user_id();
        $txn_id = $request['id'];

        // Get transaction first
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $txn_table WHERE id = %d AND user_id = %d",
            $txn_id, $user_id
        ));

        if (!$transaction) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // 1. Adjust wallet balance (reverse the transaction effect)
            $amount = $transaction->type === 'IN' 
                ? -$transaction->amount 
                : $transaction->amount;
            
            $wallet_updated = $wpdb->query($wpdb->prepare(
                "UPDATE $wallet_table 
                SET balance = balance + %f 
                WHERE id = %d AND user_id = %d",
                $amount,
                $transaction->wallet_id,
                $user_id
            ));

            if ($wallet_updated === false) {
                throw new Exception('Failed to update wallet balance');
            }

            // 2. Delete the transaction
            $deleted = $wpdb->delete(
                $txn_table,
                ['id' => $txn_id, 'user_id' => $user_id],
                ['%d', '%d']
            );

            if ($deleted === false) {
                throw new Exception('Failed to delete transaction');
            }

            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => 'Transaction deleted successfully',
                'wallet_balance_adjusted' => $amount
            ];

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //Fetch user wallets for dropdown for edit modal
    public function get_user_wallets() {
        global $wpdb;
        $table = $wpdb->prefix . 'cft_wallets';
        
        $wallets = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM $table WHERE user_id = %d",
            get_current_user_id()
        ));
        
        return $wallets ?: [];
        }
    
    //Check if user is logged in
    public function check_permission() {
        return is_user_logged_in();
    }

    // Handle transaction update
    public function update_transaction(\WP_REST_Request $request) {
        global $wpdb;
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        
        $user_id = get_current_user_id();
        $txn_id = $request['id'];

        // First try to get JSON data
        $json_params = $request->get_json_params();
        if (!empty($json_params)) {
            $request = array_merge($request->get_params(), $json_params);
        }

        // Debug log
        error_log('Update Transaction Request: ' . print_r($request, true));

        // Validate required fields
        $required = ['description', 'amount', 'type', 'wallet_id'];
        foreach ($required as $field) {
            if (!isset($request[$field]) || empty($request[$field])) {
                error_log('Missing field in update: ' . $field);
                return new WP_REST_Response([
                    'success' => false,
                    'message' => sprintf(__('%s is required', 'cashflow-tracker'), $field)
                ], 400);
            }
        }

        // Get original transaction
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $txn_table WHERE id = %d AND user_id = %d", 
            $txn_id, $user_id
        ));

        if (!$original) {
            return new WP_Error('not_found', 'Transaction not found', ['status' => 404]);
        }

        // Verify wallet belongs to user
        $valid_wallet = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $wallet_table WHERE id = %d AND user_id = %d",
            $request['wallet_id'], $user_id
        ));

        if (!$valid_wallet) {
            return new WP_Error('invalid_wallet', 'Invalid wallet selected', ['status' => 400]);
        }

        // Prepare update data
        $update_data = [
            'description' => sanitize_text_field($request['description']),
            'amount' => floatval($request['amount']),
            'type' => in_array($request['type'], ['IN', 'OUT']) ? $request['type'] : $original->type,
            'wallet_id' => intval($request['wallet_id']),
            'updated_at' => current_time('mysql')
        ];

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // 1. Revert original transaction's effect
            $this->adjust_wallet_balance(
                $original->wallet_id,
                $original->type === 'IN' ? -$original->amount : $original->amount
            );

            // 2. Update transaction record
            $updated = $wpdb->update(
                $txn_table,
                $update_data,
                ['id' => $txn_id, 'user_id' => $user_id],
                ['%s', '%f', '%s', '%d', '%s'], // format for values
                ['%d', '%d'] // format for where clauses
            );
            // Check if update was successful
            if ($updated === false) {
                error_log('Database update failed. Last error: ' . $wpdb->last_error);
                error_log('Attempted query: ' . $wpdb->last_query);
                throw new Exception('Database update failed: ' . $wpdb->last_error);
            }

            // 3. Apply new transaction's effect
            $this->adjust_wallet_balance(
                $update_data['wallet_id'],
                $update_data['type'] === 'IN' ? $update_data['amount'] : -$update_data['amount']
            );

            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => 'Transaction updated',
                'data' => $update_data
            ];

        } catch (Exception $e) {
            error_log('Transaction update failed: ' . $e->getMessage());
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage(), ['status' => 500]);
        }

        error_log('Transaction updated successfully');
        return [
            'success' => true,
            'message' => 'Transaction updated',
            'data' => $update_data
        ];
    }

    // Helper: Adjust wallet balance
    private function adjust_wallet_balance($wallet_id, $amount) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}cft_wallets 
            SET balance = balance + %f 
            WHERE id = %d",
            $amount, $wallet_id
        ));
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

    public function delete_wallet($request) {
        global $wpdb;
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $txn_table = $wpdb->prefix . 'cft_transactions';
        
        $user_id = get_current_user_id();
        $wallet_id = $request['id'];
        
        // Verify wallet belongs to user
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $wallet_table WHERE id = %d AND user_id = %d",
            $wallet_id, $user_id
        ));
        
        if (!$wallet) {
            return new WP_Error('not_found', 'Wallet not found', ['status' => 404]);
        }
        
        // Start transaction to ensure atomic operation
        $wpdb->query('START TRANSACTION');
        
        try {
            // First delete all transactions (explicitly, in case CASCADE fails)
            $wpdb->delete(
                $txn_table,
                ['wallet_id' => $wallet_id],
                ['%d']
            );
            
            // Then delete the wallet
            $result = $wpdb->delete(
                $wallet_table,
                ['id' => $wallet_id],
                ['%d']
            );
            
            if (false === $result) {
                throw new Exception('Wallet deletion failed');
            }
            
            $wpdb->query('COMMIT');
            return rest_ensure_response(['success' => true]);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Could not delete wallet and transactions', ['status' => 500]);
        }
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
                    'description' => __('Wallet Created', 'cashflow-tracker'),
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
        
        $query .= " ORDER BY t.created_at DESC LIMIT 50"; //limit to 50 most recent transactions
        
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
    


    public function get_summary($request) {
        global $wpdb;
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $txn_table = $wpdb->prefix . 'cft_transactions';
        
        $user_id = get_current_user_id();
        
        // Initialize default response with proper structure
        $summary = [
            'total_balance' => 0.00,
            'total_in' => 0.00,
            'total_out' => 0.00,
            'monthly_data' => []
        ];
        
        try {
            // Check if tables exist
            $wallet_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s", $wallet_table
            )) === $wallet_table;
            
            $txn_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s", $txn_table
            )) === $txn_table;
            
            if (!$wallet_exists || !$txn_exists) {
                return rest_ensure_response($summary);
            }
            
            // Get total balance
            $total_balance = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(balance), 0) FROM $wallet_table WHERE user_id = %d",
                $user_id
            ));
            $summary['total_balance'] = (float)$total_balance;
            
            // Get transaction totals
            $totals = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COALESCE(SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END), 0) as total_in,
                    COALESCE(SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END), 0) as total_out
                FROM $txn_table
                WHERE user_id = %d",
                $user_id
            ));
            
            $summary['total_in'] = (float)$totals->total_in;
            $summary['total_out'] = (float)$totals->total_out;
            
            // Get monthly data with explicit field initialization
            $monthly_data = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    DATE_FORMAT(created_at, '%%Y-%%m') as month,
                    COALESCE(SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END), 0) as expense
                FROM $txn_table
                WHERE user_id = %d
                GROUP BY month
                ORDER BY month ASC",  // ASC to avoid needing reverse
                $user_id
            ), ARRAY_A);
            
            // Ensure consistent structure even with empty results
            $summary['monthly_data'] = array_map(function($month) {
                return [
                    'month' => $month['month'] ?? '',
                    'income' => (float)($month['income'] ?? 0),
                    'expense' => (float)($month['expense'] ?? 0)
                ];
            }, $monthly_data ?: []);
            
        } catch (Exception $e) {
            error_log('CFT Summary Error: ' . $e->getMessage());
        }
        
        return rest_ensure_response($summary);
    } 
}