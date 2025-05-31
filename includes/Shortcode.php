<?php
namespace CFT;

defined('ABSPATH') || exit;

class Shortcode {
    public function render($atts = [], $content = null) {
        // Only show to logged in users
        if (!is_user_logged_in()) {
            return '<div class="cft-notice">' . 
                   __('Please log in to access the Cash Flow Tracker.', 'cashflow-tracker') . 
                   '</div>';
        }
        
        ob_start();
        ?>
        <div id="cashflow-tracker">
            <header class="tracker-header">
                <h1><?php _e('Cash Flow Tracker', 'cashflow-tracker'); ?> 💰</h1>
                <nav>
                    
                    <a href="#" id="refresh-btn"><?php _e('Refresh', 'cashflow-tracker'); ?></a>
                    <a href="#" id="#"><?php _e('Share Cashflow', 'cashflow-tracker'); ?></a>
                    <a href="#" id="manage-wallets"><?php _e('Manage Wallets', 'cashflow-tracker'); ?></a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"><?php _e('Logout', 'cashflow-tracker'); ?></a>
                </nav>
            </header>
            <div class="welcome"><?php 
                printf(
                    __('Welcome, %s!', 'cashflow-tracker'),
                    '<span id="user-name">' . esc_html(wp_get_current_user()->display_name) . '</span>'
                ); 
            ?></div>

            <div class="balance-card">
                <div class="label"><?php _e('Current Balance', 'cashflow-tracker'); ?></div>
                <div class="amount" id="current-balance">₱0.00</div>
                <div class="flows">
                    <div><div class="label"><?php _e('Cash In', 'cashflow-tracker'); ?></div><div class="amount in" id="total-in">₱0.00</div></div>
                    <div><div class="label"><?php _e('Cash Out', 'cashflow-tracker'); ?></div><div class="amount out" id="total-out">₱0.00</div></div>
                </div>
            </div>

            <section class="wallets">
                <h2>
                    <?php _e('Wallet Status', 'cashflow-tracker'); ?>
                    <button id="add-wallet">+ <?php _e('Add Wallet', 'cashflow-tracker'); ?></button>
                    <!-- <button id="reset-filter" class="button"><?php _e('Show All', 'cashflow-tracker'); ?></button> -->
                </h2>
                <div id="wallet-list" class="wallet-list"></div>
            </section>

            <section class="chart">
                <h2><?php _e('Monthly Cash Flow', 'cashflow-tracker'); ?> <small style="font-weight: normal; font-size: .9rem;">(<?php echo date('Y'); ?>)</small></h2>
                <canvas id="cashflow-chart"></canvas>
            </section>

            <section class="txn-form">
                <h2><?php _e('Add New Transaction', 'cashflow-tracker'); ?></h2>
                <form id="transaction-form">
                    <input type="text" name="desc" placeholder="<?php esc_attr_e('What was it for?', 'cashflow-tracker'); ?>" required />
                    <input type="number" name="amount" placeholder="<?php esc_attr_e('Amount (₱)', 'cashflow-tracker'); ?>" step="0.01" required />
                    <select name="wallet" required id="wallet-select">
                        <option value=""><?php _e('Select a wallet', 'cashflow-tracker'); ?></option>
                    </select>
                    <div class="btn-group">
                        <button type="submit" class="btn success" id="cash-in">+ <?php _e('Cash In', 'cashflow-tracker'); ?></button>
                        <button type="submit" class="btn danger" id="cash-out">– <?php _e('Cash Out', 'cashflow-tracker'); ?></button>
                    </div>
                </form>
            </section>

            <section class="history">
                <h2><?php _e('Transaction History', 'cashflow-tracker'); ?> <button id="clear-all"><?php _e('Clear All', 'cashflow-tracker'); ?></button></h2>
                <ul id="txn-history"></ul>
            </section>
        </div>

        <!--modal for add wallet-->
        <div class="modal" id="wallet-modal">
            <div class="modal-content">
                <h3><?php _e('Add New Wallet', 'cashflow-tracker'); ?></h3>
                <input type="text" id="wallet-name" placeholder="<?php esc_attr_e('e.g., Cash, Bank, E-wallet', 'cashflow-tracker'); ?>">
                <input type="number" id="wallet-balance" placeholder="<?php esc_attr_e('Initial Balance (₱)', 'cashflow-tracker'); ?>" value="0.00">
                <div class="modal-footer">
                    <button id="cancel-wallet-btn"><?php _e('Cancel', 'cashflow-tracker'); ?></button>
                    <button id="add-wallet-btn" style="background:#007aff;color:#fff;padding:.5rem 1rem;border:none;border-radius:4px"><?php _e('Add Wallet', 'cashflow-tracker'); ?></button>

                </div>
            </div>
        </div>


        <!--modal for manage wallet-->
        <div class="modal" id="manage-wallets-modal">
            <div class="modal-content">
                <h3><?php _e('Manage Wallets', 'cashflow-tracker'); ?></h3>
                
                <div class="wallet-selector">
                <select id="wallet-edit-select">
                    <option value=""><?php _e('Select a wallet', 'cashflow-tracker'); ?></option>
                </select>
                </div>

                <div class="wallet-edit-form" style="display:none;">
                <div class="form-group">
                    <label><?php _e('Wallet Name:', 'cashflow-tracker'); ?></label>
                    <input type="text" id="edit-wallet-name">
                </div>
                
                <div class="form-group">
                    <label><?php _e('Balance:', 'cashflow-tracker'); ?></label>
                    <input type="number" step="0.01" id="edit-wallet-balance">
                </div>

                <!-- WILL DELETE THIS SECTION -->
                <!-- Delete options section
                <div class="form-group delete-options" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 14px; color: #666;">
                            <?php _e('When deleting this wallet:', 'cashflow-tracker'); ?>
                        </span>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="delete-transactions" name="delete_transactions" 
                                style="margin: 0; accent-color: #dc3545;">
                            <span style="font-size: 14px;">
                                <?php _e('Delete all transactions', 'cashflow-tracker'); ?>
                            </span>
                        </label>
                    </div>
                    <p style="margin: 8px 0 0 0; font-size: 13px; color: #888;">
                        <?php _e('If unchecked, transactions will be kept but marked as "Wallet deleted"', 'cashflow-tracker'); ?>
                    </p>
                </div> -->

                <!-- PREFER TO USE THIS ONE -->
                <!-- Delete Options Section -->
                <!-- <div class="delete-options" style="margin: 15px 0; padding: 12px; background: rgba(240,240,240,0.3); border-radius: 4px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 14px; color: #666;">
                            <?php _e('Delete Option:', 'cashflow-tracker'); ?>
                        </span>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" id="delete-transactions" style="margin: 0; accent-color: #dc3545;">
                            <span style="font-size: 14px;"><?php _e('With all transactions', 'cashflow-tracker'); ?></span>
                        </label>

                    </div>

                    <p style="margin: 8px 0 0 0; font-size: 13px; color: #888;">
                        <?php _e('If unchecked, transactions will be kept but marked as "Wallet deleted"', 'cashflow-tracker'); ?>
                    </p>
                </div> -->


                <!-- TO BE CONTINUED 
                Manage Wallet modal refreshes 
                -->


                <div class="form-actions">
                    <button id="save-wallet-changes" class="btn success">
                    <?php _e('Save Changes', 'cashflow-tracker'); ?>
                    </button>
                    <button id="delete-wallet" class="btn danger">
                    <?php _e('Delete', 'cashflow-tracker'); ?>
                    </button>
                </div>
                </div>

                <div class="modal-footer">
                <button id="add-new-wallet-btn" class="btn">
                    <?php _e('+ Add New Wallet', 'cashflow-tracker'); ?>
                </button>
                <button id="close-manage-wallet-modal" class="btn">
                    <?php _e('Close', 'cashflow-tracker'); ?>
                </button>
                </div>
            </div>
        </div>

        <!-- Add this anywhere in your #cashflow-tracker div -->
         <!-- Remove 'show' class later for JS control -->
        <!-- <div id="transaction-modal" class="modal show"> 
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.25rem;">Edit Transaction</h3>
            <button class="cft-modal-close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            
            <form>
            <input type="hidden" id="txn-edit-id" value="123">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">Description</label>
                <input 
                type="text" 
                value="Groceries" 
                style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                >
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">Type</label>
                <select style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                <option value="IN">Income</option>
                <option value="OUT" selected>Expense</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">Amount (₱)</label>
                <input 
                type="number" 
                value="50.00" 
                step="0.01"
                style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                >
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">Wallet</label>
                <select style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                <option value="1" selected>Main Wallet</option>
                <option value="2">Savings</option>
                <option value="3">Emergency Fund</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 1rem;">
                <button 
                type="button" 
                style="padding: 0.75rem 1.5rem; background: #f5f7fa; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
                >
                Cancel
                </button>
                <button 
                type="submit" 
                style="padding: 0.75rem 1.5rem; background: #3366ff; color: white; border: none; border-radius: 4px; cursor: pointer;"
                >
                Save Changes
                </button>
            </div>
            </form>
        </div>
        </div> -->

        
        <!-- Transaction Edit Modal -->
        <div class="modal" id="transaction-edit-modal">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3><?php _e('Edit Transaction', 'cashflow-tracker'); ?></h3>
                    <button class="cft-modal-close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>
                
                <form id="transaction-edit-form">
                    <input type="hidden" id="txn-edit-id">
                    <input type="hidden" id="txn-edit-wallet-id"> <!-- Added hidden wallet ID field -->
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Wallet', 'cashflow-tracker'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="txn-edit-wallet-name"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            readonly
                        >
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Description', 'cashflow-tracker'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="txn-edit-title"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            required
                        >
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Type', 'cashflow-tracker'); ?>
                        </label>
                        <select 
                            id="txn-edit-type"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            required
                        >
                            <option value="IN"><?php _e('Income', 'cashflow-tracker'); ?></option>
                            <option value="OUT"><?php _e('Expense', 'cashflow-tracker'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Amount (₱)', 'cashflow-tracker'); ?>
                        </label>
                        <input 
                            type="number" 
                            id="txn-edit-amount"
                            step="0.01"
                            min="0"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            required
                        >
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 1rem;">
                        <button 
                            type="button" 
                            id="cancel-txn-edit"
                            style="padding: 0.75rem 1.5rem; background: #f5f7fa; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
                        >
                            <?php _e('Cancel', 'cashflow-tracker'); ?>
                        </button>
                        <button 
                            type="submit" 
                            style="padding: 0.75rem 1.5rem; background: #3366ff; color: white; border: none; border-radius: 4px; cursor: pointer;"
                        >
                            <?php _e('Save Changes', 'cashflow-tracker'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }
}