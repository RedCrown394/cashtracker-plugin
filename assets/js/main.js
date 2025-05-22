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
            this.$walletModal = $('#wallet-modal');
            this.chart = null;

            //Newly added for fix
            this.$walletName = $('#wallet-name');
            this.$walletBalance = $('#wallet-balance');
        }
        
        bindEvents() {

            // $('#add-wallet-btn').on('click', (e) => {
            //     e.preventDefault();
            //     this.addWallet();
            // });

            // $('#manage-wallets').on('click', (e) => {
            //     e.preventDefault();
            //     this.$walletModal.addClass('show');
            // });
            
            
            $('#add-wallet').on('click', (e) => {
                e.preventDefault();
                this.$walletModal.addClass('show');
            });
            
            $('.modal-footer button:first').on('click', (e) => {
                e.preventDefault();
                this.$walletModal.removeClass('show');
            });
            
            $('.modal-footer button:last').on('click', (e) => {
                e.preventDefault();
                this.addWallet();
            });
            
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
        
        renderWallets(wallets) {
            this.$walletList.empty();
            this.$walletSelect.empty().append('<option value="">' + (cftData.i18n.select_wallet || 'Select a wallet') + '</option>');
            
            wallets.forEach((wallet) => {
                this.$walletList.append(`
                    <div class="wallet-card" data-id="${wallet.id}">
                        <span>${wallet.name}</span>
                        <span>₱${parseFloat(wallet.balance).toFixed(2)}</span>
                    </div>
                `);
                
                this.$walletSelect.append(`
                    <option value="${wallet.id}">${wallet.name} (₱${parseFloat(wallet.balance).toFixed(2)})</option>
                `);
            });
        }
        
        renderTransactions(transactions) {
            this.$txnHistory.empty();
            
            transactions.forEach((txn) => {
                const label = txn.type === 'IN' ? '+₱' : '-₱';
                const color = txn.type === 'IN' ? 'in' : 'out';
                const date = new Date(txn.created_at);
                
                this.$txnHistory.append(`
                    <li>
                        <span>${txn.description}<br/><small>${date.toLocaleString()} – ${txn.wallet_name}</small></span>
                        <span class="${color}">${label}${parseFloat(txn.amount).toFixed(2)}</span>
                    </li>
                `);
            });
        }
        
        renderSummary(summary) {
            this.$balance.text('₱' + summary.total_balance.toFixed(2));
            this.$totalIn.text('₱' + summary.total_in.toFixed(2));
            this.$totalOut.text('₱' + summary.total_out.toFixed(2));
        }
        
        renderChart(monthlyData) {
            const ctx = document.getElementById('cashflow-chart').getContext('2d');
            
            if (this.chart) {
                this.chart.destroy();
            }
            
            const labels = monthlyData.map(item => item.month);
            const incomeData = monthlyData.map(item => item.income);
            const expenseData = monthlyData.map(item => item.expense);
            
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
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        addWallet() {
            const name = $('#wallet-name').val().trim();
            const balance = parseFloat($('#wallet-balance').val()) || 0;
            
            if (!name) {
                alert(cftData.i18n.invalid_data);
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