<?php
namespace CFT;

defined('ABSPATH') || exit;

class Shortcode
{
    public function render($atts = [], $content = null)
    {
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
                    <a href="#" id="share-cashflow"><?php _e('Share Wallet', 'cashflow-tracker'); ?></a>
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
                    <div>
                        <div class="label"><?php _e('Cash In', 'cashflow-tracker'); ?></div>
                        <div class="amount in" id="total-in">₱0.00</div>
                    </div>
                    <div>
                        <div class="label"><?php _e('Cash Out', 'cashflow-tracker'); ?></div>
                        <div class="amount out" id="total-out">₱0.00</div>
                    </div>
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
                <h2><?php _e('Monthly Cash Flow', 'cashflow-tracker'); ?> <small
                        style="font-weight: normal; font-size: .9rem;">(<?php echo date('Y'); ?>)</small></h2>
                <canvas id="cashflow-chart"></canvas>
            </section>

            <section class="txn-form">
                <h2><?php _e('Add New Transaction', 'cashflow-tracker'); ?></h2>
                <form id="transaction-form">
                    <input type="text" name="desc" placeholder="<?php esc_attr_e('What was it for?', 'cashflow-tracker'); ?>"
                        required />
                    <input type="number" name="amount" placeholder="<?php esc_attr_e('Amount (₱)', 'cashflow-tracker'); ?>"
                        step="0.01" required />
                    <select name="wallet" required id="wallet-select">
                        <option value=""><?php _e('Select a wallet', 'cashflow-tracker'); ?></option>
                    </select>
                    <div class="btn-group">
                        <button type="submit" class="btn success" id="cash-in">+
                            <?php _e('Cash In', 'cashflow-tracker'); ?></button>
                        <button type="submit" class="btn danger" id="cash-out">–
                            <?php _e('Cash Out', 'cashflow-tracker'); ?></button>
                    </div>
                </form>
            </section>

            <section class="history">
                <h2><?php _e('Transaction History', 'cashflow-tracker'); ?> <button
                        id="clear-all"><?php _e('Clear All', 'cashflow-tracker'); ?></button></h2>
                <ul id="txn-history"></ul>
            </section>
        </div>

        <!--modal for add wallet-->
        <div class="modal" id="wallet-modal">
            <div class="modal-content">
                <h3><?php _e('Add New Wallet', 'cashflow-tracker'); ?></h3>
                <input type="text" id="wallet-name"
                    placeholder="<?php esc_attr_e('e.g., Cash, Bank, E-wallet', 'cashflow-tracker'); ?>">
                <input type="number" id="wallet-balance"
                    placeholder="<?php esc_attr_e('Initial Balance (₱)', 'cashflow-tracker'); ?>" value="0.00">
                <div class="modal-footer">
                    <button id="cancel-wallet-btn"><?php _e('Cancel', 'cashflow-tracker'); ?></button>
                    <button id="add-wallet-btn"
                        style="background:#007aff;color:#fff;padding:.5rem 1rem;border:none;border-radius:4px"><?php _e('Add Wallet', 'cashflow-tracker'); ?></button>

                </div>
            </div>
        </div>

        <!--modal for manage wallet-->
        <div class="modal" id="manage-wallets-modal">
            <div class="modal-content">
                <h3><?php _e('Manage Wallets', 'cashflow-tracker'); ?></h3>

                <div class="wallet-selector">
                    <select id="wallet-edit-select">
                        <option value=""><?php _e('Select a wallet', 'cashflow-tracker'); ?><!--Wallets will go here.--> </option>
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

        <!-- Transaction Edit Modal -->
        <div class="modal" id="transaction-edit-modal">
            <div class="modal-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3><?php _e('Edit Transaction', 'cashflow-tracker'); ?></h3>
                    <button class="cft-modal-close"
                        style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>

                <form id="transaction-edit-form">
                    <input type="hidden" id="txn-edit-id">
                    <input type="hidden" id="txn-edit-wallet-id"> <!-- Added hidden wallet ID field -->

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Wallet', 'cashflow-tracker'); ?>
                        </label>
                        <input type="text" id="txn-edit-wallet-name"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            readonly>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Description', 'cashflow-tracker'); ?>
                        </label>
                        <input type="text" id="txn-edit-title"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            required>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Type', 'cashflow-tracker'); ?>
                        </label>
                        <select id="txn-edit-type"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            required>
                            <option value="IN"><?php _e('Income', 'cashflow-tracker'); ?></option>
                            <option value="OUT"><?php _e('Expense', 'cashflow-tracker'); ?></option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">
                            <?php _e('Amount (₱)', 'cashflow-tracker'); ?>
                        </label>
                        <input type="number" id="txn-edit-amount" step="0.01" min="0"
                            style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;"
                            required>
                    </div>

                    <div
                        style="display: flex; gap: 0.75rem; justify-content: space-between; border-top: 1px solid #eee; padding-top: 1.5rem;">
                        <button type="button" id="cancel-txn-edit"
                            style="font-size: 0.9rem; padding: 0.5rem 1rem; background: #f5f7fa; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">
                            <?php _e('Cancel', 'cashflow-tracker'); ?>
                        </button>
                        <button type="submit"
                            style="font-size: 0.9rem; padding: 0.5rem 1rem; background: #3366ff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            <?php _e('Save Changes', 'cashflow-tracker'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Structure -->
        <!-- <div id="share-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Share Wallet', 'cashflow-tracker'); ?></h3>
                    <button class="modal-close">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="share-user-select"><?php _e('Select User', 'cashflow-tracker'); ?></label>
                        <select id="share-user-select" class="wallet-selector">
                            <option value=""><?php _e('Loading users...', 'cashflow-tracker'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Permission Level', 'cashflow-tracker'); ?></label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="permission" value="view" checked>
                                <span><?php _e('View Only', 'cashflow-tracker'); ?></span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="permission" value="edit">
                                <span><?php _e('Can Edit', 'cashflow-tracker'); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="button-secondary modal-close"><?php _e('Cancel', 'cashflow-tracker'); ?></button>
                        <button id="confirm-share" class="button-primary">
                            <span class="btn-text"><?php _e('Share', 'cashflow-tracker'); ?></span>
                            <span class="spinner"></span>
                        </button>
                    </div>
                </div>
        </div> -->

        <div id="share-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Share Wallet', 'cashflow-tracker'); ?></h3>
            <button class="modal-close">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Added wallet info section -->
            <div class="wallet-info-section">
                <h4><?php _e('Sharing this wallet:', 'cashflow-tracker'); ?></h4>
                <div class="wallet-info">
                    <span id="share-wallet-name">Not selected</span>
                    <span id="share-wallet-balance"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="share-user-select"><?php _e('Select User', 'cashflow-tracker'); ?></label>
                <select id="share-user-select" class="wallet-selector">
                    <option value=""><?php _e('Loading users...', 'cashflow-tracker'); ?><!--Users will go here.--></option>
                </select>
            </div>

            <div class="form-group">
                <label><?php _e('Permission Level', 'cashflow-tracker'); ?></label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="permission" value="view" checked>
                        <span><?php _e('View Only', 'cashflow-tracker'); ?></span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="permission" value="edit">
                        <span><?php _e('Can Edit', 'cashflow-tracker'); ?></span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button class="button-secondary modal-close"><?php _e('Cancel', 'cashflow-tracker'); ?></button>
                <button id="confirm-share" class="button-primary">
                    <span class="btn-text"><?php _e('Share', 'cashflow-tracker'); ?></span>
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>
</div> c

            <?php
            return ob_get_clean();
    }
}