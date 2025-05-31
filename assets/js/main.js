(function($) {
    class CashFlowTracker {
        constructor() {
            this.userId = cftData.user_id;
            this.initElements();
            this.bindEvents();
            this.loadData();
        }
        
        initElements() {
            this.$balance = $('#current-balance');
            this.$totalIn = $('#total-in');
            this.$totalOut = $('#total-out');
            this.$walletList = $('#wallet-list');
            this.$walletSelect = $('#wallet-select');
            this.$txnHistory = $('#txn-history');
            this.$txnForm = $('#transaction-form');
            
            this.chart = null;

            // Add Wallet Modal
            this.$walletModal = $('#wallet-modal');
            this.$walletName = $('#wallet-name');
            this.$walletBalance = $('#wallet-balance');
            this.$cancelWalletBtn = $('#cancel-wallet-btn');

            //Manage wallet
            this.$manageModal = $('#manage-wallets-modal');
            this.$walletEditSelect = $('#wallet-edit-select');
            this.$editName = $('#edit-wallet-name');
            this.$editBalance = $('#edit-wallet-balance');
            this.initModals();

            // Edit Transaction Modal elements
            this.$txnEditModal = $('#transaction-edit-modal');
            this.$txnEditForm = $('#transaction-edit-form');
            this.$txnEditId = $('#txn-edit-id');
            this.$txnEditWalletId = $('#txn-edit-wallet-id');
            this.$txnEditWalletName = $('#txn-edit-wallet-name');
            this.$txnEditTitle = $('#txn-edit-title');
            this.$txnEditAmount = $('#txn-edit-amount');
            this.$txnEditType = $('#txn-edit-type');

            

        }

        // ====================MANAGE WALLET MODAL==================== //
        //? Grouping the modal related methods for better organization  //
        // =========================================================== //
        initModals() {
            // 1. Manage Wallets Modal
            $('#manage-wallets').on('click', (e) => {
                e.preventDefault();
                this.showManageModal();
            });

            // 2. Switch to Add Wallet modal
            $('#add-new-wallet-btn').on('click', (e) => {
                e.preventDefault();
                this.hideManageModal();
                this.showAddWalletModal();
            });

            // 3. Close Button (specific to this modal)
            $('#close-manage-wallet-modal').on('click', (e) => {
                e.preventDefault();
                this.hideManageModal();
            });

            // 4. Load Wallet Data When Selected
            $('#wallet-edit-select').on('change', () => this.loadWalletForEditing());

            // 5. Save Changes
            $('#save-wallet-changes').on('click', (e) => {
                e.preventDefault();
                this.saveWalletChanges();
            });

            // 6. Delete Wallet
            $('#delete-wallet').on('click', (e) => {
                e.preventDefault();
                this.deleteSelectedWallet();                
            });

        }
        // ====================END OF MANAGE WALLET MODAL==================== //
        //? Grouping the modal related methods for better organization        //
        // ================================================================== //

        
        bindEvents() {
            // ===================ADD WALLET MODAL=================== //
            $('#add-wallet').on('click', (e) => {
                e.preventDefault();
                this.showAddWalletModal();
            });

            $('#add-wallet-btn').on('click', (e) => {
                e.preventDefault();
                this.addWallet();
            });

            $('#cancel-wallet-btn').on('click', (e) => {
                e.preventDefault();
                this.hideAddWalletModal();
            });
            // ==============END OF ADD WALLET MODALL=================== //


            // Add refresh button handler
            $(document).on('click', '#refresh-btn', (e) => {
                e.preventDefault();
                this.handleRefresh();
            });

            //Clear All Transactions
            $('#clear-all').on('click', (e) => {
                e.preventDefault();
                if (confirm(cftData.i18n.clear_confirm || 'Are you sure you want to clear all data?')) {
                    this.clearAllData();
                }
            });
            
            this.$txnForm.on('submit', (e) => {
                e.preventDefault();
                const $submitBtn = $(e.originalEvent.submitter);
                const type = $submitBtn.attr('id') === 'cash-in' ? 'IN' : 'OUT';
                this.addTransaction(type);
            });

            //Show Transaction edit modal
            $(document).on('click', '.edit-txn-btn', (e) => this.showEditModal(e));
            // Modal close handlers
            $('#cancel-txn-edit, #transaction-edit-modal .cft-modal-close').on('click', () => this.hideEditModal());
            // Form submission
            this.$txnEditForm.on('submit', (e) => this.saveEditedTransaction(e));  
            
            // Add this for delete functionality for transactions history
            $(document).on('click', '.delete-txn-btn', (e) => {
                e.preventDefault();
                const txnId = $(e.currentTarget).data('txn-id');
                this.deleteTransaction(txnId);
            });
        }

        showEditModal(e) {
        const txnData = $(e.currentTarget).data('txn');
        
        
        // Populate form with transaction data
        this.$txnEditId.val(txnData.id);
        this.$txnEditWalletId.val(txnData.wallet_id);
        this.$txnEditWalletName.val(txnData.wallet_name); // Display wallet name
        this.$txnEditTitle.val(txnData.description);
        this.$txnEditAmount.val(parseFloat(txnData.amount).toFixed(2));
        this.$txnEditType.val(txnData.type);
        
        // Show modal
        this.$txnEditModal.addClass('show');
    }

    handleRefresh() {
        const $btn = $('#refresh-btn');
        
        // Save original text (optional, but useful)
        const originalText = $btn.text();
        
        // Show loading spinner and message
        $btn.addClass('loading').prop('disabled', true)
            .html(`<span class="spinner is-active"></span> Refreshing...`);
        
        // Call your data loading method
        this.loadData();

        // Restore button text after a delay (simulate data load)
        setTimeout(() => {
            $btn.removeClass('loading').prop('disabled', false)
                .text(originalText); // Restore the original "Refresh" text
        }, 1000);
    }


    hideEditModal() {
        this.$txnEditModal.removeClass('show');
        this.$txnEditForm.trigger('reset');
    }

    deleteTransaction(txnId) {
        if (!confirm('Are you sure you want to delete this transaction?')) {
            return;
        }

        const $btn = $(`.delete-txn-btn[data-txn-id="${txnId}"]`);
        $btn.prop('disabled', true).text('Deleting...');

        $.ajax({
            //url: `${cftData.rest_url}transactions/${txnId}`,
            url: `${cftData.rest_url}/transactions/${txnId}`,

            method: 'DELETE',
            headers: {
                'X-WP-Nonce': cftData.nonce,
                'Content-Type': 'application/json'
            },
            success: (response) => {
                if (response.success) {
                    $(`li[data-txn-id="${txnId}"]`).fadeOut(300, () => {
                        $(this).remove();
                    });
                    //this.updateWalletBalances(); // Refresh wallet balances if needed
                    this.loadData(); // Refresh main wallet list
                } else {
                    alert(response.message || 'Failed to delete transaction');
                }
            },
            error: (xhr) => {
                console.error('Delete error:', xhr.responseText);
                alert('Error deleting transaction');
            },
            complete: () => {
                $btn.prop('disabled', false).text('Delete');
            }
        });
    }

    saveEditedTransaction(e) {
        e.preventDefault();
        
        const transactionData = {
            id: this.$txnEditId.val(),
            description: this.$txnEditTitle.val(),
            amount: this.$txnEditAmount.val(),
            type: this.$txnEditType.val(),
            wallet_id: this.$txnEditWalletId.val()
        };

        console.log('Sending transaction data:', transactionData); // Debug log

        // Validate amount
        if (parseFloat(transactionData.amount) <= 0) {
            alert('Amount must be greater than 0');
            return;
        }

        // Show loading state
        // const $submitBtn = this.$txnEditForm.find('[type="submit"]');
        // $submitBtn.prop('disabled', true).text('Saving...');

        $.ajax({
            //url: cftData.rest_url + 'transactions/' + transactionData.id,
            url: cftData.rest_url + '/transactions/' + transactionData.id,

            method: 'PATCH',
            headers: { 
                'X-WP-Nonce': cftData.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(transactionData),
            dataType: 'json',
            success: (response) => {
                console.log('API Response:', response);
                if (response.success) {
                    alert('Transaction updated successfully!');

                    // // 1. Update the UI directly
                    // const transactionId = transactionData.id;
                    // this.updateTransactionInUI(transactionId, transactionData);

                    this.loadData(); // Refresh transaction list

                    this.hideEditModal();
                    //this.fetchTransactions(); // Consider refreshing just the data instead of full page reload
                } else {
                    alert(response.message || 'Update failed');
                }
            },
            
            error: (xhr) => {
                console.error('Error:', xhr.responseText);
                let errorMsg = 'Failed to update transaction';
                try {
                    const jsonResponse = JSON.parse(xhr.responseText);
                    errorMsg = jsonResponse.message || errorMsg;
                    if (jsonResponse.data && jsonResponse.data.params) {
                        errorMsg += '\n' + JSON.stringify(jsonResponse.data.params, null, 2);
                    }
                } catch (e) {
                    errorMsg += ` (Status: ${xhr.status})`;
                }
                alert(errorMsg);
            },
            // complete: () => {
            //     $submitBtn.prop('disabled', false).text('Save Changes');
            // }
            
        });
    }   
        // ===================METHODS FOR MANAGE WALLET=================== //
        showManageModal() {
        this.$walletEditSelect.empty().append('<option value="">Select a wallet</option>');
        this.$manageModal.addClass('show');
        this.loadWalletsForManagement();
        }

        hideManageModal() {
            this.$manageModal.removeClass('show');
            this.$walletEditSelect.val('');
            $('.wallet-edit-form').hide();
        }

        loadWalletsForManagement() {
            $.ajax({
                url: cftData.rest_url + '/wallets',
                method: 'GET',
                beforeSend: (xhr) => {
                xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                success: (response) => {
                response.forEach(wallet => {
                    this.$walletEditSelect.append(
                    `<option value="${wallet.id}">${wallet.name} (₱${wallet.balance})</option>`
                    );
                });
                }
            });
        }

        loadWalletForEditing() {
            const walletId = this.$walletEditSelect.val();
            if (!walletId) {
                $('.wallet-edit-form').hide();
                return;
            }

            $.ajax({
                url: `${cftData.rest_url}/wallets/${walletId}`,
                method: 'GET',
                beforeSend: (xhr) => {
                xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                success: (wallet) => {
                this.$editName.val(wallet.name);
                this.$editBalance.val(wallet.balance);
                this.toggleBalanceWarning(wallet.balance);
                $('.wallet-edit-form').show();
                }
            });
        }   

        saveWalletChanges() {
            const walletId = this.$walletEditSelect.val();
            const newName = this.$editName.val().trim();
            const newBalance = parseFloat(this.$editBalance.val());

            const $saveWalletBtn = $('#save-wallet-changes');
            $saveWalletBtn.prop('disabled', true).text('Saving...');

            if (!newName) {
                alert(cftData.i18n.invalid_data);
                $saveWalletBtn.prop('disabled', false).text('Save Changes');
                return;
            }

            $.ajax({
                url: `${cftData.rest_url}/wallets/${walletId}`,
                method: 'POST',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                data: {
                    name: newName,
                    balance: newBalance
                },
                success: () => {
                    // 1. Update the dropdown OPTION directly (immediate UI update)
                    const $dropdown = this.$walletEditSelect;
                    $dropdown.find(`option[value="${walletId}"]`)
                        .text(`${newName} (₱${newBalance.toFixed(2)})`);

                    // 2. Refresh other components
                    this.loadData(); // Refresh main wallet list
                    //this.loadWalletSelector(); // Refresh transaction form dropdown

                    // 3. Reset and close the form
                    $saveWalletBtn.prop('disabled', false).text('Save Changes');
                    //this.$walletEditSelect.val('');
                    //$('.wallet-edit-form').hide();
                    
                },
                error: (xhr) => {
                    $saveWalletBtn.prop('disabled', false).text('Save Changes');
                    alert('Error: ' + (xhr.responseJSON?.message || 'Update failed'));
                }
            });
        }

        deleteSelectedWallet() {
            const walletId = this.$walletEditSelect.val();
            const walletName = this.$editName.val();
            const $deleteBtn = $('#delete-wallet');

            // Disable button during operation
            $deleteBtn.prop('disabled', true).text('Deleting...');

            if (!confirm(`Delete "${walletName}" and ALL its transactions? This cannot be undone!`)) {
                $deleteBtn.prop('disabled', false).text('Delete Wallet');
                return;
            }

            $.ajax({
                url: `${cftData.rest_url}/wallets/${walletId}`,
                method: 'DELETE',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                success: () => {
                    // 1. Remove from dropdown immediately
                    this.$walletEditSelect.find(`option[value="${walletId}"]`).remove();
                    
                    // 2. Refresh all data 
                    this.loadData(); // Main list and balances
                   // this.loadWalletSelector(); // Transaction form
                    
                    // 3. Reset form and UI
                    this.$walletEditSelect.val('');
                    $('.wallet-edit-form').hide();
                    $deleteBtn.prop('disabled', false).text('Delete Wallet');
                    
                    // 4. Simple alert feedback
                    alert(`"${walletName}" was successfully deleted`);
                },
                error: (xhr) => {
                    $deleteBtn.prop('disabled', false).text('Delete Wallet');
                    alert('Error: ' + (xhr.responseJSON?.message || 'Deletion failed'));
                }
            });
        }

        toggleBalanceWarning(balance) {
        this.$editBalance.toggleClass('negative', balance < 0);
        }
        // ================ END OF METHODS FOR MANAGE WALLET=================== //


        // ================ ADD WALLET MODAL CONTROL METHODS ================ //
        showAddWalletModal() {
            this.$walletModal.addClass('show');
            this.$walletName.val('');
            this.$walletBalance.val('0.00');
        }

        hideAddWalletModal() {
            this.$walletModal.removeClass('show');
        }

        addWallet() {
            const name = $('#wallet-name').val().trim();
            const balance = parseFloat($('#wallet-balance').val()) || 0;

            const $addBtn = $('#add-wallet-btn');
            $addBtn.prop('disabled', true).text('Adding...'); // Disable during add

            
            if (!name) {
                alert(cftData.i18n.invalid_data);
                $addBtn.prop('disabled', false).text('Add Wallet'); // Re-enable on validation fail
                return;
            }
            
            $.ajax({
                url: cftData.rest_url + '/wallets',
                method: 'POST',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                data: JSON.stringify({
                    name: name,
                    balance: balance
                }),
                success: (response) => {
                    this.$walletModal.removeClass('show');
                    this.$walletName.val('');
                    this.$walletBalance.val('');
                    $addBtn.prop('disabled', false).text('Add Wallet'); // Success re-enable

                    this.loadData();
                },
                error: (xhr) => {
                    let errorMsg = 'Error adding wallet';
                    if (xhr.responseJSON) {
                        errorMsg = xhr.responseJSON.message || 'Database error';
                        if (xhr.responseJSON.data?.db_error) {
                            errorMsg += ` (Technical details: ${xhr.responseJSON.data.db_error})`;
                        }
                    }
                    $addBtn.prop('disabled', false).text('Add Wallet'); // Error re-enable
                    alert(errorMsg);
                    console.error('Full error:', xhr.responseJSON || xhr.statusText);
                }
            });
        }
        
        addTransaction(type) {
            const formData = this.$txnForm.serializeArray();
            const data = {};
            
            formData.forEach(item => {
                data[item.name] = item.value;
            });
            
            if (!data.desc || !data.amount || !data.wallet) {
                alert(cftData.i18n.invalid_data);
                return;
            }
            
            $.ajax({
                url: cftData.rest_url + '/transactions',
                method: 'POST',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                data: {
                    desc: data.desc,
                    amount: parseFloat(data.amount),
                    wallet_id: parseInt(data.wallet),
                    type: type
                },
                success: () => {
                    this.$txnForm.trigger('reset');
                    this.loadData();
                },
                error: (xhr) => {
                    const error = xhr.responseJSON || {};
                    console.error('Error adding transaction:', error);
                    alert(error.message || cftData.i18n.error);
                }
            });
        }
        // ================ END OF ADD WALLET MODAL CONTROL METHODS ================ //
    
        loadWalletSelector() {
            const $walletSelect = $('#wallet-select');
            $walletSelect.empty().append('<option value="">Select a wallet</option>');
            
            $.ajax({
                url: cftData.rest_url + '/wallets',
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                success: (wallets) => {
                    wallets.forEach(wallet => {
                        $walletSelect.append(
                            `<option value="${wallet.id}">${wallet.name} (₱${wallet.balance})</option>`
                        );
                    });
                },
                error: (xhr) => {
                    console.error('Error loading wallets:', xhr.responseText);
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
                url: cftData.rest_url + '/wallets',
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                success: (response) => {
                    this.renderWallets(response);
                },
                error: (xhr) => {
                    console.error('Error loading wallets:', xhr.responseText);
                    alert(cftData.i18n.error);
                }
            });
        }
        
        loadTransactions() {
            $.ajax({
                url: cftData.rest_url + '/transactions',
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                success: (response) => {
                    this.renderTransactions(response);
                },
                error: (xhr) => {
                    console.error('Error loading transactions:', xhr.responseText);
                    alert(cftData.i18n.error);
                }
            });
        }
        
        loadSummary() {
            $.ajax({
                url: cftData.rest_url + '/summary',
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', cftData.nonce);
                },
                success: (response) => {
                    this.renderSummary(response);
                    this.renderChart(response.monthly_data);
                },
                error: (xhr) => {
                    console.error('Error loading summary:', xhr.responseText);
                    alert(cftData.i18n.error);
                }
            });
        }
        
        // renderWallets(wallets) {
        //     this.$walletList.empty();
        //     this.$walletSelect.empty().append('<option value="">' + (cftData.i18n.select_wallet || 'Select a wallet') + '</option>');
            
        //     wallets.forEach((wallet) => {
        //         this.$walletList.append(`
        //             <div class="wallet-card" data-id="${wallet.id}">
        //                 <span>${wallet.name}</span>
        //                 <span>₱${parseFloat(wallet.balance).toFixed(2)}</span>
        //             </div>
        //         `);
                
        //         this.$walletSelect.append(`
        //             <option value="${wallet.id}">${wallet.name} (₱${parseFloat(wallet.balance).toFixed(2)})</option>
        //         `);
        //     });
        // } 

        renderWallets(wallets) {
            this.$walletList.empty();
            this.$walletSelect.empty().append('<option value="">' + (cftData.i18n.select_wallet || 'Select a wallet') + '</option>');
            
            wallets.forEach((wallet) => {
                // Add wallet card with click handler
                this.$walletList.append(`
                    <div class="wallet-card" data-id="${wallet.id}" data-wallet='${JSON.stringify(wallet)}'>
                        <span>${wallet.name}</span>
                        <span>₱${parseFloat(wallet.balance).toFixed(2)}</span>
                    </div>
                `);
                
                this.$walletSelect.append(`
                    <option value="${wallet.id}">${wallet.name} (₱${parseFloat(wallet.balance).toFixed(2)})</option>
                `);
            });

            // Add click handler for wallet cards
            this.$walletList.on('click', '.wallet-card', (e) => {
                const walletId = $(e.currentTarget).data('id');
                const walletData = $(e.currentTarget).data('wallet');
                this.filterByWallet(walletId, walletData);
            });
        }

        // renderTransactions(transactions) {
        //     this.$txnHistory.empty();
            
        //     transactions.forEach((txn) => {
        //         const label = txn.type === 'IN' ? '+₱' : '-₱';
        //         const color = txn.type === 'IN' ? 'in' : 'out';
        //         const date = new Date(txn.created_at);
                
        //         this.$txnHistory.append(`
        //             <li>
        //                 <span>
        //                     ${txn.description}
        //                     <br/>
        //                     <small class="txn-meta">
        //                         ${date.toLocaleString()} – ${txn.wallet_name}
        //                         <button class="edit-txn-btn" 
        //                                 data-txn-id="${txn.id}"
        //                                 data-txn='${JSON.stringify(txn)}'>
        //                             Edit
        //                         </button>
        //                         <button class="delete-txn-btn" 
        //                                 data-txn-id="${txn.id}">
        //                             Delete
        //                         </button>
        //                     </small>
        //                 </span>
        //                 <span class="${color}">${label}${parseFloat(txn.amount).toFixed(2)}</span>
        //             </li>
        //         `);
        //     });
        // }

        renderTransactions(transactions) {
            this.$txnHistory.empty();
            
            transactions.forEach((txn) => {
                const label = txn.type === 'IN' ? '+₱' : '-₱';
                const color = txn.type === 'IN' ? 'in' : 'out';
                const date = new Date(txn.created_at);
                
                this.$txnHistory.append(`
                    <li class="transaction-item">
                        <div class="txn-content">
                            <div class="txn-description">${txn.description}</div>
                            <div class="txn-meta-line">
                                <span class="txn-date">${date.toLocaleString()}</span>
                                <span class="txn-wallet">– ${txn.wallet_name}</span>
                            </div>
                            <div class="txn-actions">
                                <button class="edit-txn-btn" 
                                        data-txn-id="${txn.id}"
                                        data-txn='${JSON.stringify(txn)}'>
                                    Edit
                                </button>
                                <button class="delete-txn-btn" 
                                        data-txn-id="${txn.id}">
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
            this.$balance.text('₱' + summary.total_balance.toFixed(2));
            this.$totalIn.text('₱' + summary.total_in.toFixed(2));
            this.$totalOut.text('₱' + summary.total_out.toFixed(2));
        }
        
        // renderChart(monthlyData) {
        //     const ctx = document.getElementById('cashflow-chart').getContext('2d');
            
        //     if (this.chart) {
        //         this.chart.destroy();
        //     }
            
        //     const labels = monthlyData.map(item => item.month);
        //     const incomeData = monthlyData.map(item => item.income);
        //     const expenseData = monthlyData.map(item => item.expense);
            
        //     this.chart = new Chart(ctx, {
        //         type: 'bar',
        //         data: {
        //             labels: labels,
        //             datasets: [
        //                 {
        //                     label: 'Income',
        //                     data: incomeData,
        //                     backgroundColor: '#b8e994',
        //                     borderColor: '#78e08f',
        //                     borderWidth: 1
        //                 },
        //                 {
        //                     label: 'Expenses',
        //                     data: expenseData,
        //                     backgroundColor: '#ff7979',
        //                     borderColor: '#eb4d4b',
        //                     borderWidth: 1
        //                 }
        //             ]
        //         },
        //         options: {
        //             responsive: true,
        //             scales: {
        //                 y: {
        //                     beginAtZero: true
        //                 }
        //             }
        //         }
        //     });
        // }
        
        renderChart(monthlyData, walletName = 'All Wallets') {
        const ctx = document.getElementById('cashflow-chart').getContext('2d');
        
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

        const labels = monthlyData.map(item => item.month);
        const incomeData = monthlyData.map(item => item.income || 0); // Fallback to 0 if undefined
        const expenseData = monthlyData.map(item => item.expense || 0);

        this.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income',
                        data: incomeData,
                        backgroundColor: '#b8e994',
                        borderColor: '#78e08f',
                        borderWidth: 1
                    },
                    {
                        label: 'Expenses',
                        data: expenseData,
                        backgroundColor: '#ff7979',
                        borderColor: '#eb4d4b',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: `${walletName}`
                    }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // In your CashFlowTracker class
    filterByWallet(walletId, walletData) {
        const $clickedCard = this.$walletList.find(`.wallet-card[data-id="${walletId}"]`);
        const isActive = $clickedCard.hasClass('active');

        // Remove all highlights first
        this.$walletList.find('.wallet-card').removeClass('active');

        if (isActive) {
            // If wallet was already active, reset to show all data
            this.loadData();
        } else {
            // If wallet wasn't active, highlight and filter
            $clickedCard.addClass('active');
            
            // Fetch filtered data
            $.ajax({
                url: `${cftData.rest_url}/transactions?wallet_id=${walletId}`,
                method: 'GET',
                headers: { 'X-WP-Nonce': cftData.nonce },
                success: (transactions) => {
                    this.renderTransactions(transactions);
                }
            });

            $.ajax({
                url: `${cftData.rest_url}/summary?wallet_id=${walletId}`,
                method: 'GET',
                headers: { 'X-WP-Nonce': cftData.nonce },
                success: (summary) => {
                    this.renderSummary(summary);
                    this.renderChart(summary.monthly_data, walletData.name);
                }
            });
        }
    }


        clearAllData() {
            // Implement if needed
            console.log('Clear all data functionality would go here');
        }   

    }
    
    $(document).ready(() => {
        if ($('#cashflow-tracker').length) {
            new CashFlowTracker();
        }
    });

})(jQuery);