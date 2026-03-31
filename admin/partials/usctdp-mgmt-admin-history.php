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
            <h2>Post Refund</h2>
            <form id="refund-form">
                <div id="refund-fields" class="modal_field_group">
                    <div class="modal_field">
                        <label for="refund-amount">Amount</label>
                        <input type="number" id="refund-amount" name="refund-amount" step="0.01" min="0" required>
                    </div>
                    <div class="modal_field">
                        <label for="refund-method">Payment Method</label>
                        <select id="refund-method" name="refund-method" required>
                            <option value="">Select Method</option>
                            <option value="house_credit">House Credit</option>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="modal_field">
                        <label for="refund-reason">Reason</label>
                        <input type="text" id="refund-reason" name="refund-reason" required>
                    </div>
                </div>
                <div class="actions-footer">
                    <button type="submit" class="button" id="post-refund-btn">Post Refund</button>
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
                    <span class="summary-label">Current Balance</span>
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
                            <th>Charge</th>
                            <th>Payment</th>
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
            <div id="family-balance">
                <label>Balance</label>
                <span id="family-total-balance"></span>
            </div>
            <div id="history-table-wrap">
                <div id="table-filters">
                    <div id="student-filter-section" class="dt-layout-cell dt-layout-start">
                        <select id="student-filter"></select>
                    </div>
                    <div id="session-filter-section" class="dt-layout-cell dt-layout-start">
                        <select id="session-filter"></select>
                    </div>
                    <div id="type-filter-section" class="dt-layout-cell dt-layout-start">
                        <select id="type-filter">
                            <option value=""></option>
                            <option value="registration">Registration</option>
                            <option value="merchandise">Merchandise</option>
                        </select>
                    </div>
                    <div id="owes-filter-section" class="dt-layout-cell dt-layout-start">
                        <label for="owes-filter">Owes Money:</label>
                        <input type="checkbox" id="owes-filter" name="owes-filter" value="1">
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