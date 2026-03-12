<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="main-content">
        <dialog id="post-payment-modal">
            <h2>Post Payment</h2>
            <div id="registration-payment-table"></div>
            <div class="actions-footer">
                <button type="button" class="button" id="close-payment-modal">Cancel</button>
            </div>
        </dialog>

        <dialog id="payment-history-modal">
            <h2>Payment History</h2>
            <div id="payment-history-table-wrap">
                <table id="payment-history-table" class="usctdp-datatable">
                    <thead>
                        <tr>
                            <th>Date Posted</th>
                            <th>Status</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Credits Used</th>
                            <th>Reference Number</th>
                            <th>Order Link</th>
                        </tr>
                    </thead>
                    <tbody id="clinics-table-body"></tbody>
                </table>
            </div>
            <div class="actions-footer">
                <button type="button" class="button" id="close-payment-history-modal">Cancel</button>
            </div>
        </dialog>

        <div id="context-selectors">
        </div>
        <div id="history-container" class="hidden">
            <h2>Registration History for <span id="family-name"></span></h2>
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
                                            <option value="post-payments">Post Payments</option>
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
