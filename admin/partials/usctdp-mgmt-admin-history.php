<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="main-content">
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
