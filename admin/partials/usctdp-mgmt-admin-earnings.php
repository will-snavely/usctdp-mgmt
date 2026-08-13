<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="usctdp-dashboard">
        <div class="dashboard-grid dashboard-grid-tiles">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Gross Revenue</span>
                </div>
                <div class="dashboard-card-body">
                    <div class="stat-tile">
                        <span id="tile-gross-revenue" class="stat-value">&mdash;</span>
                        <span class="stat-label">Total Sales</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">PayPal Fees</span>
                </div>
                <div class="dashboard-card-body">
                    <div class="stat-tile">
                        <span id="tile-paypal-fees" class="stat-value">&mdash;</span>
                        <span class="stat-label">For Filtered Range</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Net Revenue</span>
                </div>
                <div class="dashboard-card-body">
                    <div class="stat-tile">
                        <span id="tile-net-revenue" class="stat-value">&mdash;</span>
                        <span class="stat-label">Gross &minus; PayPal Fees</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span class="card-title">Accounts Receivable</span>
                </div>
                <div class="dashboard-card-body">
                    <div class="stat-tile">
                        <span id="tile-receivable" class="stat-value">&mdash;</span>
                        <span class="stat-label">Still Owed by Families</span>
                    </div>
                </div>
            </div>
        </div>

        <p class="paypal-fee-note">PayPal fees are shown as a single total for the filtered date range, not broken
            out per session &mdash; a single order can cover items from more than one session, so PayPal's one
            per-order fee can't be split between them without guessing.</p>

        <div class="dashboard-card card-wide">
            <div class="dashboard-card-header">
                <span class="card-title">Earnings by Session</span>
            </div>
            <div class="dashboard-card-body">
                <div id="table-filters">
                    <div class="filter-row">
                        <div id="date-from-filter-section" class="filter-item flex-row gap-5 align-center">
                            <label for="date-from-filter" class="table-filter-label">From</label>
                            <input type="date" id="date-from-filter" class="table-filter" name="date-from-filter">
                        </div>
                        <div id="date-to-filter-section" class="filter-item flex-row gap-5 align-center">
                            <label for="date-to-filter" class="table-filter-label">To</label>
                            <input type="date" id="date-to-filter" class="table-filter" name="date-to-filter">
                        </div>
                    </div>
                </div>

                <div class="table-scroll-x">
                    <table id="earnings-table" class="usctdp-mini-table" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Dates</th>
                                <th>Gross Revenue</th>
                                <th>Accounts Receivable</th>
                                <th>Collected</th>
                            </tr>
                        </thead>
                        <tbody id="earnings-table-body">
                            <tr class="loading-row">
                                <td colspan="5">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
