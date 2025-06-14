(function ($) {
    class CashFlowTracker {
        constructor() {
            this.userId = cftData.user_id;
            this.currentWallet = null;
            this.activeRequests = [];
            this.initElements();
            this.bindEvents();
            this.initLoadingNotice();
            this.initShareModal();
            this.loadData();
        }

        initElements() {
            this.$shareModal = $('#share-modal');
            this.$shareWalletName = $('#share-wallet-name');
            this.$shareWalletBalance = $('#share-wallet-balance');

            // Core elements
            this.$balance = $("#current-balance");
            this.$totalIn = $("#total-in");
            this.$totalOut = $("#total-out");
            this.$walletList = $("#wallet-list");
            this.$walletSelect = $("#wallet-select");
            this.$txnHistory = $("#txn-history");
            this.$txnForm = $("#transaction-form");

            // Verify transaction history table structure
            if (!this.$txnHistory.length) {
                console.error("Transaction history table not found");
                return;
            }

            // Ensure tbody exists
            if (!this.$txnHistory.find("tbody").length) {
                this.$txnHistory.append("<tbody></tbody>");
            }

            this.chart = null;

            // Add Wallet Modal
            this.$walletModal = $("#wallet-modal");
            this.$walletName = $("#wallet-name");
            this.$walletBalance = $("#wallet-balance");
            this.$cancelWalletBtn = $("#cancel-wallet-btn");

            //Manage wallet
            this.$manageModal = $("#manage-wallets-modal");
            this.$walletEditSelect = $("#wallet-edit-select");
            this.$editName = $("#edit-wallet-name");
            this.$editBalance = $("#edit-wallet-balance");
            this.initModals();

            // Edit Transaction Modal elements
            this.$txnEditModal = $("#transaction-edit-modal");
            this.$txnEditForm = $("#transaction-edit-form");
            this.$txnEditId = $("#txn-edit-id");
            this.$txnEditWalletId = $("#txn-edit-wallet-id");
            this.$txnEditWalletName = $("#txn-edit-wallet-name");
            this.$txnEditTitle = $("#txn-edit-title");
            this.$txnEditAmount = $("#txn-edit-amount");
            this.$txnEditType = $("#txn-edit-type");


            
            this.$transactionList = $('#transaction-list');
            this.$balance = $('#current-balance');
            
            this.currentWallet = null;
            
            // Initialize with empty data
            this.renderTransactions([]);
            this.renderSummary({
                total_balance: 0,
                total_in: 0,
                total_out: 0,
                monthly_data: []
            });
            }

        // ====================MANAGE WALLET MODAL==================== //
        //? Grouping the modal related methods for better organization  //
        // =========================================================== //
        initModals() {
            // 1. Manage Wallets Modal
            $("#manage-wallets").on("click", (e) => {
                e.preventDefault();
                this.showManageModal();
            });

            // 2. Switch to Add Wallet modal
            $("#add-new-wallet-btn").on("click", (e) => {
                e.preventDefault();
                this.hideManageModal();
                this.showAddWalletModal();
            });

            // 3. Close Button (specific to this modal)
            $("#close-manage-wallet-modal").on("click", (e) => {
                e.preventDefault();
                this.hideManageModal();
            });

            // 4. Load Wallet Data When Selected
            $("#wallet-edit-select").on("change", () => this.loadWalletForEditing());

            // 5. Save Changes
            $("#save-wallet-changes").on("click", (e) => {
                e.preventDefault();
                this.saveWalletChanges();
            });

            // 6. Delete Wallet
            $("#delete-wallet").on("click", (e) => {
                e.preventDefault();
                this.deleteSelectedWallet();
            });
        }
        // ====================END OF MANAGE WALLET MODAL==================== //
        //? Grouping the modal related methods for better organization        //
        // ================================================================== //

        initShareModal() {
        // Open modal
        $("#share-cashflow").on("click", (e) => {
            e.preventDefault();
            if (!this.currentWallet) {
                alert("Please select a wallet first");
                return;
            }
            
            // Get the active wallet data
            const $activeWallet = this.$walletList.find('.wallet-card.active');
            if ($activeWallet.length) {
                const walletData = $activeWallet.data('wallet');
                this.updateShareModalWalletInfo(walletData);
                this.loadShareableUsers();
                this.$shareModal.addClass("show");
            } else {
                alert("No wallet selected or wallet data not found");
            }
        });

        // Close modal
        $(".modal-close").on("click", () => {
            this.$shareModal.removeClass("show");
        });

        // Handle share
        $("#confirm-share").on("click", (e) => {
            e.preventDefault();
            this.handleShare();
        });
    }

    updateShareModalWalletInfo(walletData) {
        if (walletData && walletData.name && walletData.balance) {
            this.$shareWalletName.text(walletData.name);
            this.$shareWalletBalance.text('₱' + parseFloat(walletData.balance).toFixed(2));
        } else {
            console.error("Invalid wallet data:", walletData);
            this.$shareWalletName.text("Error: Invalid wallet data");
            this.$shareWalletBalance.text("");
        }
    }

        
        loadShareableUsers() {
        $.ajax({
            url: cftData.rest_url + "/shareable-users",
            method: "GET",
            headers: { "X-WP-Nonce": cftData.nonce },
            beforeSend: () => {
                $("#share-user-select").html(
                    '<option value="">Loading users...</option>'
                );
            },
            success: (users) => {
                //!Change this to FIELDTEXT
                $("#share-user-select")
                    .empty()
                    .append('<option value="">Select user</option>');
                users.forEach((user) => {
                    $("#share-user-select").append(
                        `<option value="${user.id}">${user.name} (${user.email})</option>`
                    );
                });
            },
            error: () => {
                $("#share-user-select").html(
                    '<option value="">Error loading users</option>'
                );
            },
        });
    }

        handleShare() {
        const $btn = $("#confirm-share");
        const walletId = this.currentWallet;
        const userId = $("#share-user-select").val();
        const permission = $('input[name="permission"]:checked').val();

        if (!userId) {
            //alert("Please select a user");
            this.showToast(
                    'Please select a user to share with.',
                    'error'
                );
            return;
        }

        // Show loading state
        $btn.addClass("loading").prop("disabled", true);

        $.ajax({
            url: `${cftData.rest_url}/wallets/${walletId}/shares`,
            method: "POST",
            headers: {
                "X-WP-Nonce": cftData.nonce,
                "Content-Type": "application/json",
            },
            data: JSON.stringify({
                user_id: userId,
                permission: permission,
            }),
            success: () => {
                
               // alert("Wallet shared successfully!");
                this.$shareModal.removeClass("show");
                this.showToast(
                    'Wallet shared successfully!',
                    'success'
                );
            },
            error: (xhr) => {
                const error = xhr.responseJSON || {};
                //alert(error.message || "Failed to share wallet");
                this.showToast(
                    'Wallet already shared with this user or an error occurred.',
                    'error'
                );
            },
            complete: () => {
                $btn.removeClass("loading").prop("disabled", false);
            },
        });
    }



        // Add this method to your CashFlowTracker class
        showShareModal(walletData) {
            // Update the modal with wallet info
            $('#shared-wallet-name').text(walletData.name);
            $('#shared-wallet-balance').text('₱' + parseFloat(walletData.balance).toFixed(2));
            
            // Load shareable users
            this.loadShareableUsers();
            
            // Show the modal
            $('#share-modal').addClass('show');
            
            // Store current wallet ID
            this.currentWallet = walletData.id;
        }

        

        bindEvents() {
            // Modify your existing wallet card click handler to use this:
            this.$walletList.on("click", ".wallet-card", (e) => {
                this.$walletList.find(".wallet-card").removeClass("active");
                $(e.currentTarget).addClass("active");
                
                const walletData = $(e.currentTarget).data("wallet");
                //this.filterByWallet(walletData.id, walletData);
                
                // When showing share modal, pass the wallet data
                // You'll call this from your "Share" button click handler
                // this.showShareModal(walletData);
            });

            // Update your share button click handler to pass the wallet data
            $('#share-wallet-btn').on('click', () => {
                const activeWallet = this.$walletList.find('.wallet-card.active');
                if (activeWallet.length) {
                    const walletData = activeWallet.data('wallet');
                    this.showShareModal(walletData);
                } else {
                    alert('Please select a wallet first');
                }
            });


            // ===================ADD WALLET MODAL=================== //
            $("#add-wallet").on("click", (e) => {
                e.preventDefault();
                this.showAddWalletModal();
            });

            $("#add-wallet-btn").on("click", (e) => {
                e.preventDefault();
                this.addWallet();
            });

            $("#cancel-wallet-btn").on("click", (e) => {
                e.preventDefault();
                this.hideAddWalletModal();
            });
            // ==============END OF ADD WALLET MODALL=================== //

            // Add refresh button handler
            $(document).on("click", "#refresh-btn", (e) => {
                e.preventDefault();
                this.handleRefresh();
            });

            //Clear All Transactions
            $("#clear-all").on("click", (e) => {
                e.preventDefault();
                if (
                    confirm(
                        cftData.i18n.clear_confirm ||
                        "Are you sure you want to clear all data?"
                    )
                ) {
                    this.clearAllData();
                }
            });

            this.$txnForm.on("submit", (e) => {
                e.preventDefault();
                const $submitBtn = $(e.originalEvent.submitter);
                const type = $submitBtn.attr("id") === "cash-in" ? "IN" : "OUT";
                this.addTransaction(type);
            });

            //Show Transaction edit modal
            $(document).on("click", ".edit-txn-btn", (e) => this.showEditModal(e));
            // Modal close handlers
            $("#cancel-txn-edit, #transaction-edit-modal .cft-modal-close").on(
                "click",
                () => this.hideEditModal()
            );
            // Form submission
            this.$txnEditForm.on("submit", (e) => this.saveEditedTransaction(e));

            // Add this for delete functionality for transactions history
            $(document).on("click", ".delete-txn-btn", (e) => {
                e.preventDefault();
                const txnId = $(e.currentTarget).data("txn-id");
                this.deleteTransaction(txnId);
            });
        }

        showEditModal(e) {
            const txnData = $(e.currentTarget).data("txn");

            // Populate form with transaction data
            this.$txnEditId.val(txnData.id);
            this.$txnEditWalletId.val(txnData.wallet_id);
            this.$txnEditWalletName.val(txnData.wallet_name); // Display wallet name
            this.$txnEditTitle.val(txnData.description);
            this.$txnEditAmount.val(parseFloat(txnData.amount).toFixed(2));
            this.$txnEditType.val(txnData.type);

            // Show modal
            this.$txnEditModal.addClass("show");
        }

        initLoadingNotice() {
            // Add loading notice element to DOM
            $("body").append(`
            <div id="wallet-loading-notice" class="cft-loading-notice">
                <div class="cft-loading-spinner"></div>
                Loading wallet data...
            </div>
        `);
        }

        showLoading() {
            $("#wallet-loading-notice").addClass("show");
        }

        hideLoading() {
            $("#wallet-loading-notice").removeClass("show");
        }

        // Abort all pending requests
        abortPendingRequests() {
            this.activeRequests.forEach((req) => req.abort());
            this.activeRequests = [];
        }

        filterByWallet(walletId, walletData) {
            this.showLoading();
            this.abortPendingRequests(); // Cancel any ongoing requests

            // Toggle selection
            if (this.currentWallet === walletId) {
                this.resetWalletFilter();
                return;
            }

            // Update selection
            this.currentWallet = walletId;
            this.$walletList.find(".wallet-card").removeClass("active");
            this.$walletList
                .find(`.wallet-card[data-id="${walletId}"]`)
                .addClass("active");

            // Load filtered data (using your existing endpoints)
            const req1 = $.ajax({
                url: `${cftData.rest_url}/transactions?wallet_id=${walletId}`,
                method: "GET",
                headers: { "X-WP-Nonce": cftData.nonce },
                complete: () => this.hideLoading(),
                success: (transactions) => {
                    this.renderTransactions(transactions);
                },
            });

            const req2 = $.ajax({
                url: `${cftData.rest_url}/summary?wallet_id=${walletId}`,
                method: "GET",
                headers: { "X-WP-Nonce": cftData.nonce },
                complete: () => this.hideLoading(),
                success: (summary) => {
                    this.renderSummary(summary);
                    this.renderChart(summary.monthly_data, walletData.name);
                },
            });

            this.activeRequests = [req1, req2];
        }




        resetWalletFilter() {
            this.showLoading();
            this.abortPendingRequests();
            this.currentWallet = null;
            this.$walletList.find(".wallet-card").removeClass("active");

            // Load all data
            const req1 = $.ajax({
                url: `${cftData.rest_url}/transactions`,
                method: "GET",
                headers: { "X-WP-Nonce": cftData.nonce },
                complete: () => this.hideLoading(),
                success: (transactions) => {
                    this.renderTransactions(transactions);
                },
            });

            const req2 = $.ajax({
                url: `${cftData.rest_url}/summary`,
                method: "GET",
                headers: { "X-WP-Nonce": cftData.nonce },
                complete: () => this.hideLoading(),
                success: (summary) => {
                    this.renderSummary(summary);
                    this.renderChart(summary.monthly_data, "All Wallets");
                },
            });

            this.activeRequests = [req1, req2];
        }

        handleRefresh() {
            const $btn = $("#refresh-btn");

            // Save original text (optional, but useful)
            const originalText = $btn.text();

            // Show loading spinner and message
            $btn
                .addClass("loading")
                .prop("disabled", true)
                .html(`<span class="spinner is-active"></span> Refreshing...`);

            // Call your data loading method
            this.loadData();

            // Restore button text after a delay (simulate data load)
            setTimeout(() => {
                $btn.removeClass("loading").prop("disabled", false).text(originalText); // Restore the original "Refresh" text
            }, 1000);
        }

        hideEditModal() {
            this.$txnEditModal.removeClass("show");
            this.$txnEditForm.trigger("reset");
        }

        deleteTransaction(txnId) {
            if (!confirm("Are you sure you want to delete this transaction?")) {
                return;
            }

            const $btn = $(`.delete-txn-btn[data-txn-id="${txnId}"]`);
            $btn.prop("disabled", true).text("Deleting...");

            $.ajax({
                //url: `${cftData.rest_url}transactions/${txnId}`,
                url: `${cftData.rest_url}/transactions/${txnId}`,

                method: "DELETE",
                headers: {
                    "X-WP-Nonce": cftData.nonce,
                    "Content-Type": "application/json",
                },
                success: (response) => {
                    if (response.success) {
                        $(`li[data-txn-id="${txnId}"]`).fadeOut(300, () => {
                            $(this).remove();
                        });
                        //this.updateWalletBalances(); // Refresh wallet balances if needed
                        this.loadData(); // Refresh main wallet list
                    } else {
                        alert(response.message || "Failed to delete transaction");
                    }
                },
                error: (xhr) => {
                    console.error("Delete error:", xhr.responseText);
                    alert("Error deleting transaction");
                },
                complete: () => {
                    $btn.prop("disabled", false).text("Delete");
                },
            });
        }

        saveEditedTransaction(e) {
            e.preventDefault();

            const transactionData = {
                id: this.$txnEditId.val(),
                description: this.$txnEditTitle.val(),
                amount: this.$txnEditAmount.val(),
                type: this.$txnEditType.val(),
                wallet_id: this.$txnEditWalletId.val(),
            };

            // Validate amount
            if (parseFloat(transactionData.amount) <= 0) {
                //alert("Amount must be greater than 0");
                this.showToast(response.message ||
                        'Amount must be greater than 0',
                        'error'
                    );
                return;
            }

            // Show loading state
            const $submitBtn = this.$txnEditForm.find('[type="submit"]');
            $submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: cftData.rest_url + "/transactions/" + transactionData.id,
                method: "PATCH",
                headers: {
                    "X-WP-Nonce": cftData.nonce,
                    "Content-Type": "application/json",
                },
                data: JSON.stringify(transactionData),
                dataType: "json",
                success: (response) => {
                    if (response.success) {
                        //this.showSuccessMessage("Transaction updated successfully!");
                        this.showToast(
                        'Transaction edited successfully!',
                        'success'
                    );
                        this.loadData();
                        this.hideEditModal();
                    } else {
                        //this.showErrorMessage(response.message || "Update failed");
                    }
                },
                error: (xhr) => {
                    let errorMsg = "Failed to update transaction";
                    try {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        errorMsg = jsonResponse.message || errorMsg;
                        
                        // Handle specific error cases
                        if (jsonResponse.code === 'invalid_wallet') {
                            errorMsg = "You don't have permission to edit this transaction or the wallet is invalid";
                        }
                    } catch (e) {
                        errorMsg += ` (Status: ${xhr.status})`;
                    }
                    //this.showErrorMessage(errorMsg);
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text('Save Changes');
                }
            });
        }


        // ===================METHODS FOR MANAGE WALLET=================== //
        showManageModal() {
            this.$walletEditSelect
                .empty()
                .append('<option value="">Select a wallet</option>');
            this.$manageModal.addClass("show");
            this.loadWalletsForManagement();
        }

        hideManageModal() {
            this.$manageModal.removeClass("show");
            this.$walletEditSelect.val("");
            $(".wallet-edit-form").hide();
        }

        loadWalletsForManagement() {
            $.ajax({
                url: cftData.rest_url + "/wallets",
                method: "GET",
                beforeSend: (xhr) => {
                    xhr.setRequestHeader("X-WP-Nonce", cftData.nonce);
                },
                success: (response) => {
                    response.forEach((wallet) => {
                        this.$walletEditSelect.append(
                            `<option value="${wallet.id}">${wallet.name} (₱${wallet.balance})</option>`
                        );
                    });
                },
            });
        }

        loadWalletForEditing() {
            const walletId = this.$walletEditSelect.val();
            if (!walletId) {
                $(".wallet-edit-form").hide();
                return;
            }

            $.ajax({
                url: `${cftData.rest_url}/wallets/${walletId}`,
                method: "GET",
                beforeSend: (xhr) => {
                    xhr.setRequestHeader("X-WP-Nonce", cftData.nonce);
                },
                success: (wallet) => {
                    this.$editName.val(wallet.name);
                    this.$editBalance.val(wallet.balance);
                    this.toggleBalanceWarning(wallet.balance);
                    $(".wallet-edit-form").show();
                },
            });
        }

        saveWalletChanges() {
            const walletId = this.$walletEditSelect.val();
            const newName = this.$editName.val().trim();
            const newBalance = parseFloat(this.$editBalance.val());

            const $saveWalletBtn = $("#save-wallet-changes");
            $saveWalletBtn.prop("disabled", true).text("Saving...");

            if (!newName) {
                alert(cftData.i18n.invalid_data);
                $saveWalletBtn.prop("disabled", false).text("Save Changes");
                return;
            }

            $.ajax({
                url: `${cftData.rest_url}/wallets/${walletId}`,
                method: "POST",
                beforeSend: (xhr) => {
                    xhr.setRequestHeader("X-WP-Nonce", cftData.nonce);
                },
                data: {
                    name: newName,
                    balance: newBalance,
                },
                success: () => {
                    // 1. Update the dropdown OPTION directly (immediate UI update)
                    const $dropdown = this.$walletEditSelect;
                    $dropdown
                        .find(`option[value="${walletId}"]`)
                        .text(`${newName} (₱${newBalance.toFixed(2)})`);

                    this.showToast(
                        'Wallet edited successfully!',
                        'success'
                    );

                    // 2. Refresh other components
                    this.loadData(); // Refresh main wallet list
                    //this.loadWalletSelector(); // Refresh transaction form dropdown

                    // 3. Reset and close the form
                    $saveWalletBtn.prop("disabled", false).text("Save Changes");
                    //this.$walletEditSelect.val('');
                    //$('.wallet-edit-form').hide();
                },
                error: (xhr) => {
                    $saveWalletBtn.prop("disabled", false).text("Save Changes");
                    alert("Error: " + (xhr.responseJSON?.message || "Update failed"));
                },
            });
        }

        deleteSelectedWallet() {
            const walletId = this.$walletEditSelect.val();
            const walletName = this.$editName.val();
            const $deleteBtn = $("#delete-wallet");

            // Disable button during operation
            $deleteBtn.prop("disabled", true).text("Deleting...");

            if (
                !confirm(
                    `Delete "${walletName}" and ALL its transactions? This cannot be undone!`
                )
            ) {
                $deleteBtn.prop("disabled", false).text("Delete Wallet");
                return;
            }

            $.ajax({
                url: `${cftData.rest_url}/wallets/${walletId}`,
                method: "DELETE",
                beforeSend: (xhr) => {
                    xhr.setRequestHeader("X-WP-Nonce", cftData.nonce);
                },
                success: () => {
                    // 1. Remove from dropdown immediately
                    this.$walletEditSelect.find(`option[value="${walletId}"]`).remove();

                    // 2. Refresh all data
                    this.loadData(); // Main list and balances
                    // this.loadWalletSelector(); // Transaction form

                    // 3. Reset form and UI
                    this.$walletEditSelect.val("");
                    $(".wallet-edit-form").hide();
                    $deleteBtn.prop("disabled", false).text("Delete Wallet");

                    // 4. Simple alert feedback

                    this.showToast(
                        'Wallet deleted successfully!',
                        'success'
                    );
                    //alert(`"${walletName}" was successfully deleted`);
                },
                error: (xhr) => {
                    $deleteBtn.prop("disabled", false).text("Delete Wallet");
                    alert("Error: " + (xhr.responseJSON?.message || "Deletion failed"));
                },
            });
        }

        toggleBalanceWarning(balance) {
            this.$editBalance.toggleClass("negative", balance < 0);
        }
        // ================ END OF METHODS FOR MANAGE WALLET=================== //

        // ================ ADD WALLET MODAL CONTROL METHODS ================ //
        showAddWalletModal() {
            this.$walletModal.addClass("show");
            this.$walletName.val("");
            this.$walletBalance.val("0.00");
        }

        hideAddWalletModal() {
            this.$walletModal.removeClass("show");
        }

        addWallet() {
            const name = $("#wallet-name").val().trim();
            const balance = parseFloat($("#wallet-balance").val()) || 0;

            const $addBtn = $("#add-wallet-btn");
            $addBtn.prop("disabled", true).text("Adding..."); // Disable during add

            if (!name) {
                alert(cftData.i18n.invalid_data);
                $addBtn.prop("disabled", false).text("Add Wallet"); // Re-enable on validation fail
                return;
            }

            $.ajax({
                url: cftData.rest_url + "/wallets",
                method: "POST",
                beforeSend: (xhr) => {
                    xhr.setRequestHeader("X-WP-Nonce", cftData.nonce);
                    xhr.setRequestHeader("Content-Type", "application/json");
                },
                data: JSON.stringify({
                    name: name,
                    balance: balance,
                }),
                success: (response) => {
                    this.$walletModal.removeClass("show");
                    this.$walletName.val("");
                    this.$walletBalance.val("");
                    $addBtn.prop("disabled", false).text("Add Wallet"); // Success re-enable
                    this.showToast(
                        response.message || 'Wallet added successfully!',
                        'success'
                    );

                    this.loadData();
                },
                error: (xhr) => {
                    let errorMsg = "Error adding wallet";
                    if (xhr.responseJSON) {
                        errorMsg = xhr.responseJSON.message || "Database error";
                        if (xhr.responseJSON.data?.db_error) {
                            errorMsg += ` (Technical details: ${xhr.responseJSON.data.db_error})`;
                        }
                    }
                    $addBtn.prop("disabled", false).text("Add Wallet"); // Error re-enable
                    alert(errorMsg);
                    console.error("Full error:", xhr.responseJSON || xhr.statusText);
                },
            });
        }



        addTransaction(type) {
            const formData = this.$txnForm.serializeArray();
            const data = {};

            formData.forEach((item) => {
                data[item.name] = item.value;
            });

            // Validate required fields
            if (!data.desc || !data.amount || !data.wallet) {
                alert(cftData.i18n.invalid_data);
                return;
            }

            // Show loading state
            const $submitBtn = this.$txnForm.find('[type="submit"]');
            const originalHtml = $submitBtn.html(); // Store full button HTML

            $submitBtn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: cftData.rest_url + "/transactions",
                method: "POST",
                headers: {
                    "X-WP-Nonce": cftData.nonce,
                    "Content-Type": "application/json"
                },
                data: JSON.stringify({
                    desc: data.desc,
                    amount: parseFloat(data.amount),
                    wallet_id: parseInt(data.wallet),
                    type: type
                }),
                success: (response) => {
                    this.$txnForm.trigger("reset");
                    //this.showSuccessMessage(response.message || cftData.i18n.transaction_added);
                    this.showToast(
                        response.message || cftData.i18n.transaction_added || 'Transaction added successfully!',
                        'success'
                    );
                    this.loadData();
                },
                error: (xhr) => {
                    const error = xhr.responseJSON || {};
                    let errorMsg = error.message || cftData.i18n.error;
                    
                    // Handle specific error cases
                    if (error.code === 'invalid_wallet') {
                        errorMsg = cftData.i18n.invalid_wallet || "You don't have permission to add transactions to this wallet";
                    } else if (error.code === 'insufficient_balance') {
                        errorMsg = cftData.i18n.insufficient_balance || "Insufficient balance in wallet";
                    }
                    
                    this.showToast(errorMsg, 'error');
                    //this.showErrorMessage(errorMsg);
                    console.error("Error adding transaction:", error);
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).html(originalHtml); // Restore full HTML
                }
            });
        }

        // Add this method to your class
        showToast(message, type = 'success') {
            // Remove any existing toasts first
            $('.cft-toast').remove();
            
            // Create toast element
            const toast = $(`
                <div class="cft-toast cft-toast-${type}">
                    ${message}
                </div>
            `);
            
            // Add to body and animate in
            $('body').append(toast);
            toast.hide().fadeIn(200);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                toast.fadeOut(200, () => toast.remove());
            }, 5000);
        }
        // ================ END OF ADD WALLET MODAL CONTROL METHODS ================ //

        loadWalletSelector() {
            const $walletSelect = $("#wallet-select");
            $walletSelect.empty().append('<option value="">Select a wallet</option>');

            $.ajax({
                url: cftData.rest_url + "/wallets/all", // Use endpoint that returns all accessible wallets
                method: "GET",
                headers: {
                    "X-WP-Nonce": cftData.nonce
                },
                success: (wallets) => {
                    wallets.forEach((wallet) => {
                        const isShared = wallet.is_shared && wallet.user_id != cftData.user_id;
                        const suffix = isShared ? ` (Shared ${wallet.share_permission})` : '';
                        
                        $walletSelect.append(
                            `<option value="${wallet.id}">${wallet.name}${suffix} (₱${wallet.balance})</option>`
                        );
                    });
                },
                error: (xhr) => {
                    console.error("Error loading wallets:", xht.responseText);
                    //this.showErrorMessage(cftData.i18n.error_loading_wallets || "Error loading wallets");
                }
            });
        }

        loadData() {
            this.loadWallets();
            this.loadTransactions();
            this.loadSummary();
        }

        loadWallets() {
            $.ajax({
                url: `${cftData.rest_url}/wallets/all`,
                method: "GET",
                headers: { "X-WP-Nonce": cftData.nonce },
                success: (wallets) => {
                    this.renderWallets(wallets);
                },
                error: (xhr) => {
                    console.error("Failed to load wallets:", xhr);
                    alert(cftData.i18n.error || "Failed to load wallets");
                }
            });
        }

        

        loadTransactions() {
            $.ajax({
                url: cftData.rest_url + "/transactions",
                method: "GET",
                beforeSend: (xhr) => {
                    xhr.setRequestHeader("X-WP-Nonce", cftData.nonce);
                },
                success: (response) => {
                    this.renderTransactions(response);
                },
                error: (xhr) => {
                    console.error("Error loading transactions:", xhr.responseText);
                    alert(cftData.i18n.error);
                },
            });
        }


        loadSummary() {
            $.ajax({
                url: cftData.rest_url + "/summary",
                method: "GET",
                beforeSend: (xhr) => {
                    xhr.setRequestHeader("X-WP-Nonce", cftData.nonce);
                },
                success: (response) => {
                    this.renderSummary(response);
                    this.renderChart(response.monthly_data);
                },
                error: (xhr) => {
                    console.error("Error loading summary:", xhr.responseText);
                    alert(cftData.i18n.error);
                },
            });
        }

        
        renderWallets(wallets) {
            this.$walletList.empty();
            this.$walletSelect.empty().append(
                '<option value="">' + (cftData.i18n.select_wallet || "Select a wallet") + '</option>'
            );

            const currentUserId = cftData.user_id;
            
            wallets.forEach((wallet) => {
                const isShared = wallet.is_shared && wallet.user_id != currentUserId;
                const isOwned = wallet.user_id == currentUserId;
                
                // Only show badge and owner info if it's a shared wallet (not owned by current user)
                const badge = isShared ? 
                    `<span class="shared-badge ${wallet.share_permission}" 
                        title="Shared with ${wallet.owner_name} (${wallet.share_permission} access)">
                        <i class="fas fa-share-alt"></i> ${wallet.share_permission}
                    </span>` : '';

                // Wallet card
                const walletCard = `
                    <div class="wallet-card ${isShared ? 'shared' : ''} ${isOwned ? 'owned' : ''}" 
                        data-id="${wallet.id}" 
                        data-wallet='${JSON.stringify(wallet)}'>
                        <div class="wallet-header">
                            <span class="wallet-name">${wallet.name}</span>
                            ${badge}
                        </div>
                        <div class="wallet-balance">₱${parseFloat(wallet.balance).toFixed(2)}</div>
                        ${isShared ? `<div class="wallet-owner">Owner: ${wallet.owner_name}</div>` : ''}
                    </div>
                `;

                this.$walletList.append(walletCard);

                // Dropdown option
                this.$walletSelect.append(`
                    <option value="${wallet.id}">
                        ${wallet.name} ${isShared ? '(Shared)' : ''} 
                        (₱${parseFloat(wallet.balance).toFixed(2)})
                    </option>
                `);
            });

            // Click handler - only highlight clicked wallet
            this.$walletList.on("click", ".wallet-card", (e) => {
                this.$walletList.find(".wallet-card").removeClass("active");
                $(e.currentTarget).addClass("active");
                
                const walletId = $(e.currentTarget).data("id");
                const walletData = $(e.currentTarget).data("wallet");
                this.filterByWallet(walletId, walletData);
            });
        }


        renderWalletItem(wallet, isShared) {
            const badge = isShared ? 
                `<span class="shared-badge ${wallet.share_permission}" 
                    title="Shared by ${wallet.owner_name} (${wallet.share_permission} access)">
                    <i class="fas fa-share-alt"></i> ${wallet.share_permission}
                </span>` : '';

            const walletItem = `
                <div class="wallet-card ${isShared ? 'shared' : ''}" 
                    data-id="${wallet.id}" 
                    data-wallet='${JSON.stringify(wallet)}'>
                    <div class="wallet-header">
                        <span class="wallet-name">${wallet.name}</span>
                        ${badge}
                    </div>
                    <div class="wallet-balance">₱${parseFloat(wallet.balance).toFixed(2)}</div>
                    ${isShared ? `<div class="wallet-owner">Owner: ${wallet.owner_name}</div>` : ''}
                </div>
            `;

            this.$walletList.append(walletItem);
            this.$walletSelect.append(`
                <option value="${wallet.id}">
                    ${wallet.name} ${isShared ? '(Shared)' : ''} 
                    (₱${parseFloat(wallet.balance).toFixed(2)})
                </option>
            `);
        }

        renderTransactions(transactions) {
            this.$txnHistory.empty();
            const currentUserId = cftData.user_id;

            transactions.forEach((txn) => {
                const label = txn.type === "IN" ? "+₱" : "-₱";
                const color = txn.type === "IN" ? "in" : "out";
                const date = new Date(txn.created_at);
                
                // Determine ownership and permissions
                const isOwned = txn.wallet_owner_id == currentUserId;
                const isShared = !isOwned && txn.share_permission;
                const canEdit = isOwned || (isShared && txn.share_permission === 'edit');
                const canDelete = canEdit; // Same permissions as edit
                
                // Owner info display
                const ownerInfo = isShared ? 
                    `<span class="txn-owner">(Shared from ${txn.owner_name})</span>` : 
                    '';

                this.$txnHistory.append(`
                    <li class="transaction-item ${isShared ? 'shared-wallet' : ''}">
                        <div class="txn-content">
                            <div class="txn-description">${txn.description}</div>
                            <div class="txn-meta-line">
                                <span class="txn-date">${date.toLocaleString()}</span>
                                <span class="txn-wallet">– ${txn.wallet_name}</span>
                                ${ownerInfo}
                            </div>
                            <div class="txn-actions">
                                <button class="edit-txn-btn" 
                                        data-txn-id="${txn.id}"
                                        data-txn='${JSON.stringify(txn)}'
                                        ${canEdit ? '' : 'disabled title="You don\'t have edit permissions"'}>
                                    Edit
                                </button>
                                <button class="delete-txn-btn" 
                                        data-txn-id="${txn.id}"
                                        ${canDelete ? '' : 'disabled title="You don\'t have delete permissions"'}>
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div class="txn-amount ${color}">${label}${parseFloat(txn.amount).toFixed(2)}</div>
                    </li>
                `);
            });
        }

        renderSummary(summary) {
            this.$balance.text("₱" + summary.total_balance.toFixed(2));
            this.$totalIn.text("₱" + summary.total_in.toFixed(2));
            this.$totalOut.text("₱" + summary.total_out.toFixed(2));
        }

        renderChart(monthlyData, walletName = "All Wallets") {
            const ctx = document.getElementById("cashflow-chart").getContext("2d");

            // Destroy previous chart if it exists
            if (this.chart) {
                this.chart.destroy();
                this.chart = null; // Explicitly clear reference
            }

            // Check if data is valid
            if (!monthlyData || monthlyData.length === 0) {
                console.warn("No data to render chart");
                return;
            }

            const labels = monthlyData.map((item) => item.month);
            const incomeData = monthlyData.map((item) => item.income || 0); // Fallback to 0 if undefined
            const expenseData = monthlyData.map((item) => item.expense || 0);

            this.chart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: "Income",
                            data: incomeData,
                            backgroundColor: "#b8e994",
                            borderColor: "#78e08f",
                            borderWidth: 1,
                        },
                        {
                            label: "Expenses",
                            data: expenseData,
                            backgroundColor: "#ff7979",
                            borderColor: "#eb4d4b",
                            borderWidth: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: `${walletName}`,
                        },
                    },
                    scales: {
                        y: { beginAtZero: true },
                    },
                },
            });
        }

        clearAllData() {
            // Implement if needed
            console.log("Clear all data functionality would go here");
        }
    }

    $(document).ready(() => {
        if ($("#cashflow-tracker").length) {
            new CashFlowTracker();
        }
    });
})(jQuery);
