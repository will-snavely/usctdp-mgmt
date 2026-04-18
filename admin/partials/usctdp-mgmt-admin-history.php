<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="main-content" class="flex-col gap-10">
        <dialog id="post-payment-modal">
            <h2>Post Payment</h2>
            <div id="registration-payment-table"></div>
            <div class="actions-footer">
                <button type="button" class="button" id="close-payment-modal">Cancel</button>
            </div>
        </dialog>

        <dialog id="post-refund-modal">
            <h2>Financial Adjustment & Refund</h2>
            <form id="refund-form">
                <div id="refund-action" class="flex-col gap-10">
                    <label for="refund-mode">Action Type</label>
                    <select id="refund-mode" name="refund-mode" required>
                        <option value="">Select Action</option>
                        <option value="standard">Refund (Adjustment + Payout)</option>
                        <option value="adjust_only">Adjustment Only</option>
                        <option value="payout_only">Payout Only</option>
                    </select>
                    <div class="field-help">
                        <span id="mode-description">Select an action to continue.</span>
                    </div>
                </div>
                <div id="refund-fields" class="modal_field_group hidden">
                    <div class="modal_field" id="direction-field-wrapper">
                        <label for="refund-direction">Adj. Type</label>
                        <select id="refund-direction" name="refund-direction">
                            <option value="">Select Direction</option>
                            <option value="decrease">Price Decrease</option>
                            <option value="increase">Price Increase</option>
                        </select>
                    </div>
                    <div class="modal_field">
                        <label for="refund-amount">Amount ($)</label>
                        <input type="number" id="refund-amount" name="refund-amount" step="0.01" min="0" required
                            placeholder="0.00">
                    </div>
                    <div class="modal_field" id="method-field-wrapper">
                        <label for="refund-method">Payout Method</label>
                        <select id="refund-method" name="refund-method" required>
                            <option value="">Select Method</option>
                            <option value="house_credit">House Credit</option>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="card">Card/PayPal</option>
                        </select>
                    </div>
                    <div class="modal_field hidden" id="check-number-field-wrapper">
                        <label for="refund-check-number">Check #</label>
                        <input type="text" id="refund-check-number" name="refund-check-number">
                    </div>
                    <div class="modal_field">
                        <label for="refund-reason">Reason / Internal Note</label>
                        <input type="text" id="refund-reason" name="refund-reason" required
                            placeholder="e.g., Injury, Class move, Sibling discount">
                    </div>
                </div>

                <div class="actions-footer">
                    <button type="submit" class="button button-primary" id="post-refund-btn">
                        Submit
                    </button>
                    <button type="button" class="button" id="close-refund-modal">Cancel</button>
                </div>
            </form>
        </dialog>

        <dialog id="payment-history-modal">
            <h2>Payment History</h2>
            <div class="usctdp-ledger-summary">
                <div class="summary-group">
                    <span class="summary-label">Status</span>
                    <div id="ledger-status-text" class="summary-value status-pending">Loading...</div>
                </div>
                <div class="summary-group text-right">
                    <span class="summary-label">Balance</span>
                    <div id="ledger-total-balance" class="summary-value balance-amount">$0.00</div>
                </div>
            </div>
            <div id="payment-history-table-wrap">
                <table id="payment-history-table" class="usctdp-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Event</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody id="payment-history-table-body"></tbody>
                </table>
            </div>
            <div class="actions-footer">
                <button type="button" class="button" id="close-payment-history-modal">Close</button>
            </div>
        </dialog>

        <div id="context-selectors">
        </div>
        <div id="history-container" class="hidden">
            <h2>Purchase History for <span id="family-name"></span></h2>
            <div id="family-balance-section" class="flex-row gap-10">
                <div class="family-financial-summary">
                    <label>Balance</label>
                    <span id="family-total-balance" class="balance-amt"></span>
                </div>
                <div class="family-financial-summary">
                    <label>Credit</label>
                    <span id="family-total-house-credit" class="balance-amt green-bg"></span>
                </div>
            </div>
            <div id="history-table-wrap">
                <div id="table-filters">
                    <div id="student-filter-section" class="dt-layout-cell dt-layout-start">
                        <select id="student-filter" class="table-filter"></select>
                    </div>
                    <div id="session-filter-section" class="dt-layout-cell dt-layout-start">
                        <select id="session-filter" class="table-filter"></select>
                    </div>
                    <div id="type-filter-section" class="dt-layout-cell dt-layout-start">
                        <select id="type-filter" class="table-filter">
                            <option value=""></option>
                            <option value="registration">Registration</option>
                            <option value="merchandise">Merchandise</option>
                        </select>
                    </div>
                    <div id="status-filter-section" class="dt-layout-cell dt-layout-start">
                        <select id="status-filter" class="table-filter">
                            <option value=""></option>
                            <option value="active">Active</option>
                            <option value="void">Void</option>
                        </select>
                    </div>
                    <div id="owes-filter-section" class="dt-layout-cell dt-layout-start">
                        <div class="flex-row gap-5">
                            <label for="owes-filter">Owes Money:</label>
                            <input type="checkbox" id="owes-filter" name="owes-filter" value="1" class="table-filter">
                        </div>
                    </div>
                </div>

                <table id="history-table" class="reg-history-table">
                    <thead>
                        <tr>
                            <th>
                                <div class="table-header-controls">
                                    <div class="select-all-control">
                                        <input id="cb-select-all" type="checkbox" class="cb-select-all">
                                        <label for="cb-select-all">Select All Visible</label>
                                    </div>
                                    <div class="bulk-actions">
                                        <select id="bulk-action-selector">
                                            <option value=""></option>
                                            <option value="post-payments">Post Payment</option>
                                        </select>
                                        <button id="apply-bulk-btn" class="button action" disabled>
                                            Apply
                                        </button>
                                        <span id="selection-status" class="count-badge hidden">
                                            <span id="selected-count">0</span> item(s) selected
                                        </span>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>