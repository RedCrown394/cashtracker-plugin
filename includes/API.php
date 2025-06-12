<?php
namespace CFT;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;


defined('ABSPATH') || exit;

class API
{
    public function register_routes()
    {

        register_rest_route('cashflow-tracker/v1', '/wallets/shared', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_shared_wallets'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route('cashflow-tracker/v1', '/wallets/owned', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_owned_wallets'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        // Sharable users endpoint
        register_rest_route('cashflow-tracker/v1', '/shareable-users', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_shareable_users'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        // In your REST API controller, to avoid pending more than 2 requests
        register_rest_route('cashflow/v1', '/combined-data', [
            'methods' => 'GET',
            'callback' => function ($request) {
                $wallet_id = $request->get_param('wallet_id');

                return [
                    'transactions' => get_transactions($wallet_id),
                    'summary' => get_summary($wallet_id)
                ];
            }
        ]);

        // Wallet sharing endpoint
        register_rest_route('cashflow-tracker/v1', '/wallets/(?P<wallet_id>\d+)/shares', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'share_wallet'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'user_id' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param); // Simplified validation
                        }
                    ],
                    'permission' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return in_array($param, ['view', 'edit']);
                        }
                    ]
                ]
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_wallet_shares'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        // Share revocation endpoint
        register_rest_route('cashflow-tracker/v1', '/shares/(?P<share_id>\d+)', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'revoke_share'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);


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
                        'validate_callback' => function ($param) {
                            return !empty($param);
                        }
                    ],
                    'balance' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
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
                        'validate_callback' => function ($param) {
                            return !empty($param);
                        }
                    ],
                    'amount' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && $param > 0;
                        }
                    ],
                    'wallet_id' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ],
                    'type' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
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
                'permission_callback' => function () {
                    return is_user_logged_in();
                }
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_transaction'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                }
            ]
        ]);


        // Add wallet endpoint for the dropdown for edit modal
        add_action('rest_api_init', function () {
            register_rest_route('cft/v1', '/wallets', [
                'methods' => 'GET',
                'callback' => [$this, 'get_user_wallets'],
                'permission_callback' => function () {
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

        register_rest_route('cashflow-tracker/v1', '/wallets/all', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_all_user_wallets'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
    }


    public function get_all_user_wallets(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $wallets = $this->get_user_wallets_with_sharing($user_id);
        return rest_ensure_response($wallets);
    }

    public function get_user_wallets_with_sharing($user_id)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                w.*,
                IF(s.shared_with_id IS NULL, 0, 1) AS is_shared,
                s.permission AS share_permission,
                owner.display_name AS owner_name,
                owner.user_email AS owner_email,
                (w.user_id = %d) AS is_owned
            FROM {$wpdb->prefix}cft_wallets w
            LEFT JOIN {$wpdb->prefix}cft_wallet_shares s 
                ON w.id = s.wallet_id AND s.shared_with_id = %d
            LEFT JOIN {$wpdb->users} owner 
                ON w.user_id = owner.ID
            WHERE w.user_id = %d OR s.shared_with_id = %d
            ORDER BY is_owned DESC, w.name ASC", // Owned wallets first
                $user_id,
                $user_id,
                $user_id,
                $user_id
            )
        ) ?: [];
    }

    public function get_shareable_users()
    {
        global $wpdb;
        $current_user_id = get_current_user_id();

        // Use prepare() to properly escape the SQL query
        $query = $wpdb->prepare(
            "SELECT ID as id, display_name as name, user_email as email 
                FROM {$wpdb->users} 
                WHERE ID != %d",
            $current_user_id
        );

        // Execute the query
        $users = $wpdb->get_results($query);

        return rest_ensure_response($users);
    }

    public function share_wallet(WP_REST_Request $request)
    {
        global $wpdb;
        $wallet_id = $request['wallet_id'];
        $user_id = $request['user_id'];
        $permission = $request['permission'];

        // Verify wallet ownership
        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}cft_wallets WHERE id = %d",
            $wallet_id
        ));

        if ($owner_id != get_current_user_id()) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not own this wallet'),
                ['status' => 403]
            );
        }

        // Check if already shared
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cft_wallet_shares 
            WHERE wallet_id = %d AND shared_with_id = %d",
            $wallet_id,
            $user_id
        ));

        if ($exists) {
            return new WP_Error(
                'rest_duplicate',
                __('Wallet already shared with this user'),
                ['status' => 409]
            );
        }

        $wpdb->insert("{$wpdb->prefix}cft_wallet_shares", [
            'wallet_id' => $wallet_id,
            'owner_id' => $owner_id,
            'shared_with_id' => $user_id,
            'permission' => $permission
        ]);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Wallet shared successfully!')
        ]);
    }
    public function get_wallet_shares(WP_REST_Request $request)
    {
        global $wpdb;
        $wallet_id = $request['wallet_id'];

        $shares = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.permission, u.ID as user_id, u.display_name as user_name
            FROM {$wpdb->prefix}cft_wallet_shares s
            JOIN {$wpdb->users} u ON s.shared_with_id = u.ID
            WHERE s.wallet_id = %d",
            $wallet_id
        ));

        return rest_ensure_response($shares);
    }

    public function revoke_share(WP_REST_Request $request)
    {
        global $wpdb;
        $share_id = $request['share_id'];

        // Verify ownership
        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT owner_id FROM {$wpdb->prefix}cft_wallet_shares WHERE id = %d",
            $share_id
        ));

        if ($owner_id != get_current_user_id()) {
            return new WP_Error(
                'rest_forbidden',
                __('You can only revoke your own shares'),
                ['status' => 403]
            );
        }

        $wpdb->delete("{$wpdb->prefix}cft_wallet_shares", ['id' => $share_id]);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Share revoked successfully!')
        ]);
    }

    public function delete_transaction(WP_REST_Request $request) {
        global $wpdb;
        
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $shares_table = $wpdb->prefix . 'cft_wallet_shares';
        
        $user_id = get_current_user_id();
        $txn_id = $request['id'];

        // First check if user has permission to delete this transaction
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, s.permission 
            FROM $txn_table t
            JOIN $wallet_table w ON t.wallet_id = w.id
            LEFT JOIN $shares_table s ON w.id = s.wallet_id AND s.shared_with_id = %d
            WHERE t.id = %d AND (t.user_id = %d OR s.permission = 'edit')",
            $user_id, $txn_id, $user_id
        ));

        if (!$transaction) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Transaction not found or no permission to delete'
            ], 403);
        }

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Adjust wallet balance (reverse the transaction effect)
            $amount = $transaction->type === 'IN' ? -$transaction->amount : $transaction->amount;
            
            $wallet_updated = $wpdb->query($wpdb->prepare(
                "UPDATE $wallet_table 
                SET balance = balance + %f 
                WHERE id = %d",
                $amount,
                $transaction->wallet_id
            ));

            if ($wallet_updated === false) {
                throw new Exception('Failed to update wallet balance');
            }

            // Delete the transaction
            $deleted = $wpdb->delete(
                $txn_table,
                ['id' => $txn_id],
                ['%d']
            );

            if ($deleted === false) {
                throw new Exception('Failed to delete transaction');
            }

            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'message' => 'Transaction deleted successfully!'
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
    public function get_user_wallets()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cft_wallets';

        $wallets = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM $table WHERE user_id = %d",
            get_current_user_id()
        ));

        return $wallets ?: [];
    }

    //Check if user is logged in
    public function check_permission()
    {
        return is_user_logged_in();
    }


    public function update_transaction(\WP_REST_Request $request) {
        global $wpdb;
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $shares_table = $wpdb->prefix . 'cft_wallet_shares';

        $user_id = get_current_user_id();
        $txn_id = $request['id'];

        // First try to get JSON data
        $json_params = $request->get_json_params();
        if (!empty($json_params)) {
            $request = array_merge($request->get_params(), $json_params);
        }

        // Validate required fields
        $required = ['description', 'amount', 'type', 'wallet_id'];
        foreach ($required as $field) {
            if (!isset($request[$field]) || empty($request[$field])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => sprintf(__('%s is required', 'cashflow-tracker'), $field)
                ], 400);
            }
        }

        // Get original transaction with permission check
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, s.permission 
            FROM $txn_table t
            LEFT JOIN $wallet_table w ON t.wallet_id = w.id
            LEFT JOIN $shares_table s ON w.id = s.wallet_id AND s.shared_with_id = %d
            WHERE t.id = %d AND (t.user_id = %d OR s.permission = 'edit')",
            $user_id, $txn_id, $user_id
        ));

        if (!$original) {
            return new WP_Error('not_found', 'Transaction not found or no permission to edit', ['status' => 403]);
        }

        // Verify wallet access (either owned or shared with edit permission)
        $wallet_access = $wpdb->get_row($wpdb->prepare(
            "SELECT w.id 
            FROM $wallet_table w
            LEFT JOIN $shares_table s ON w.id = s.wallet_id AND s.shared_with_id = %d
            WHERE w.id = %d AND (w.user_id = %d OR s.permission = 'edit')",
            $user_id, $request['wallet_id'], $user_id
        ));

        if (!$wallet_access) {
            return new WP_Error('invalid_wallet', 'Invalid wallet selected or no permission', ['status' => 403]);
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
                ['id' => $txn_id],
                ['%s', '%f', '%s', '%d', '%s'],
                ['%d']
            );

            if ($updated === false) {
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
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $e->getMessage(), ['status' => 500]);
        }
    }

    // Helper: Adjust wallet balance
    private function adjust_wallet_balance($wallet_id, $amount)
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}cft_wallets 
            SET balance = balance + %f 
            WHERE id = %d",
            $amount,
            $wallet_id
        ));
    }

    //Fetch all wallets for the current user
    public function get_wallets()
    {
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
    public function get_wallet($request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cft_wallets';
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $request['id'],
            get_current_user_id()
        ));

        if (!$wallet) {
            return new WP_Error('not_found', 'Wallet not found', ['status' => 404]);
        }

        return rest_ensure_response($wallet);
    }

    //Update a specific wallet by ID
    public function update_wallet($request)
    {
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

    public function delete_wallet($request)
    {
        global $wpdb;
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $txn_table = $wpdb->prefix . 'cft_transactions';

        $user_id = get_current_user_id();
        $wallet_id = $request['id'];

        // Verify wallet belongs to user
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $wallet_table WHERE id = %d AND user_id = %d",
            $wallet_id,
            $user_id
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

    private function verify_tables()
    {
        global $wpdb;

        $wallets_table = $wpdb->prefix . 'cft_wallets';
        $transactions_table = $wpdb->prefix . 'cft_transactions';

        // Check if tables exist
        if (
            $wpdb->get_var("SHOW TABLES LIKE '$wallets_table'") != $wallets_table ||
            $wpdb->get_var("SHOW TABLES LIKE '$transactions_table'") != $transactions_table
        ) {

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

    public function add_wallet($request)
    {
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

    public function get_transactions(WP_REST_Request $request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $wallet_id = $request->get_param('wallet_id');
        
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $shares_table = $wpdb->prefix . 'cft_wallet_shares';
        $users_table = $wpdb->users;

        // Base query with owner information and share permissions
        $query = "SELECT t.*, 
                        w.name as wallet_name,
                        w.user_id as wallet_owner_id,
                        owner.display_name as owner_name,
                        s.permission as share_permission
                FROM $txn_table t
                JOIN $wallet_table w ON t.wallet_id = w.id
                LEFT JOIN $users_table owner ON w.user_id = owner.ID
                LEFT JOIN $shares_table s ON w.id = s.wallet_id AND s.shared_with_id = %d
                WHERE %d"; // Start with dummy condition
        $params = [$user_id, 1]; // Dummy value

        if ($wallet_id) {
            // Check if user has access to this wallet (owner or shared)
            $access_check = $wpdb->get_row($wpdb->prepare(
                "SELECT w.user_id as owner_id, s.permission 
                FROM $wallet_table w
                LEFT JOIN $shares_table s ON w.id = s.wallet_id AND s.shared_with_id = %d
                WHERE w.id = %d AND (w.user_id = %d OR s.shared_with_id = %d)",
                $user_id, $wallet_id, $user_id, $user_id
            ));

            if (!$access_check) {
                return new WP_Error('rest_forbidden', __('You do not have access to this wallet'), ['status' => 403]);
            }

            // Show transactions only for this specific wallet
            $query .= " AND t.wallet_id = %d";
            $params[] = $wallet_id;
        } else {
            // Default view - only show transactions from OWNED wallets
            $query .= $wpdb->prepare(" AND t.user_id = %d", $user_id);
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

        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $shares_table = $wpdb->prefix . 'cft_wallet_shares';

        // Check wallet exists and user has access (either owner or shared with edit permission)
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT w.*, s.permission 
            FROM $wallet_table w
            LEFT JOIN $shares_table s ON w.id = s.wallet_id AND s.shared_with_id = %d
            WHERE w.id = %d AND (w.user_id = %d OR s.permission = 'edit')",
            $user_id, $wallet_id, $user_id
        ));

        if (!$wallet) {
            return new \WP_Error(
                'invalid_wallet', 
                __('Invalid wallet selected or no permission to add transactions.', 'cashflow-tracker'), 
                ['status' => 403]
            );
        }

        // Check balance for cash out
        if ($type === 'OUT' && $wallet->balance < $amount) {
            return new \WP_Error(
                'insufficient_balance', 
                __('Insufficient balance in wallet.', 'cashflow-tracker'), 
                ['status' => 400]
            );
        }

        // Add transaction
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $txn_result = $wpdb->insert($txn_table, [
            'user_id' => $wallet->user_id, // Always use wallet owner's user_id
            'wallet_id' => $wallet_id,
            'description' => $desc,
            'amount' => $amount,
            'type' => $type
        ]);

        if (!$txn_result) {
            return new \WP_Error(
                'db_error', 
                __('Could not add transaction.', 'cashflow-tracker'), 
                ['status' => 500]
            );
        }

        // Update wallet balance
        $new_balance = $type === 'IN' ? $wallet->balance + $amount : $wallet->balance - $amount;
        $wpdb->update(
            $wallet_table,
            ['balance' => $new_balance],
            ['id' => $wallet_id]
        );

        return rest_ensure_response([
            'success' => true,
            'new_balance' => $new_balance,
            'message' => __('Transaction added successfully!', 'cashflow-tracker')
        ]);
    }

    public function get_summary($request) {
        global $wpdb;
        $wallet_table = $wpdb->prefix . 'cft_wallets';
        $txn_table = $wpdb->prefix . 'cft_transactions';
        $shares_table = $wpdb->prefix . 'cft_wallet_shares';

        $user_id = get_current_user_id();
        $wallet_id = $request->get_param('wallet_id');

        $summary = [
            'total_balance' => 0.00,
            'total_in' => 0.00,
            'total_out' => 0.00,
            'monthly_data' => []
        ];

        try {
            if (!$this->tables_exist($wallet_table, $txn_table)) {
                return rest_ensure_response($summary);
            }

            // ===== 1. TOTAL BALANCE ===== //
            if ($wallet_id) {
                // Check access for specific wallet
                $access_check = $wpdb->get_row($wpdb->prepare(
                    "SELECT w.user_id as owner_id, s.permission 
                    FROM $wallet_table w
                    LEFT JOIN $shares_table s ON w.id = s.wallet_id AND s.shared_with_id = %d
                    WHERE w.id = %d AND (w.user_id = %d OR s.shared_with_id = %d)",
                    $user_id, $wallet_id, $user_id, $user_id
                ));

                if (!$access_check) {
                    return new WP_Error('rest_forbidden', __('You do not have access to this wallet', 'cashflow-tracker'), ['status' => 403]);
                }

                $balance_query = $wpdb->prepare(
                    "SELECT balance FROM $wallet_table WHERE id = %d",
                    $wallet_id
                );
                $summary['total_balance'] = (float) $wpdb->get_var($balance_query);
            } else {
                // ONLY include OWNED wallets in total balance
                $balance_query = $wpdb->prepare(
                    "SELECT COALESCE(SUM(balance), 0) FROM $wallet_table 
                    WHERE user_id = %d",
                    $user_id
                );
                $summary['total_balance'] = (float) $wpdb->get_var($balance_query);
            }

            // ===== 2. TRANSACTION TOTALS ===== //
            $txn_query = $wpdb->prepare(
                "SELECT 
                    COALESCE(SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END), 0) as total_in,
                    COALESCE(SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END), 0) as total_out
                FROM $txn_table t
                WHERE %d", 1); // Dummy condition

            if ($wallet_id) {
                // Specific wallet (already access-checked)
                $txn_query .= $wpdb->prepare(" AND t.wallet_id = %d", $wallet_id);
            } else {
                // ONLY include transactions from OWNED wallets
                $txn_query .= $wpdb->prepare(" AND t.user_id = %d", $user_id);
            }

            $totals = $wpdb->get_row($txn_query);
            $summary['total_in'] = (float) $totals->total_in;
            $summary['total_out'] = (float) $totals->total_out;

            // ===== 3. MONTHLY DATA ===== //
            $monthly_query = $wpdb->prepare(
                "SELECT 
                    DATE_FORMAT(created_at, '%%Y-%%m') as month,
                    COALESCE(SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END), 0) as expense
                FROM $txn_table t
                WHERE %d", 1); // Dummy condition

            if ($wallet_id) {
                $monthly_query .= $wpdb->prepare(" AND t.wallet_id = %d", $wallet_id);
            } else {
                $monthly_query .= $wpdb->prepare(" AND t.user_id = %d", $user_id);
            }

            $monthly_query .= " GROUP BY month ORDER BY month ASC";
            $monthly_data = $wpdb->get_results($monthly_query, ARRAY_A);

            $summary['monthly_data'] = array_map(function ($month) {
                return [
                    'month' => $month['month'],
                    'income' => (float) $month['income'],
                    'expense' => (float) $month['expense']
                ];
            }, $monthly_data ?: []);

        } catch (Exception $e) {
            error_log('CFT Summary Error: ' . $e->getMessage());
        }

        return rest_ensure_response($summary);
    }

    // Helper function to check table existence
    private function tables_exist()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'cft_wallets',
            $wpdb->prefix . 'cft_transactions',
            //$wpdb->prefix . 'cft_wallet_shares'
        ];

        $missing_tables = [];

        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                $missing_tables[] = $table;
            }
        }

        if (!empty($missing_tables)) {
            error_log('Missing CFT tables: ' . implode(', ', $missing_tables));
            return false;
        }

        return true;
    }
}