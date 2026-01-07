<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div class="wp-dashboard-wrapper">
        <aside class="master-pane">
            <div id="master-content">
                <h2>
                    <span id="master-title">Balances by Family</span>
                </h2>
                <table id="balances-table" class="usctdp-custom-post-table hidden">
                    <thead>
                        <tr>
                            <th>Family</th>
                            <th>Outstanding Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </aside>

        <main class="detail-pane">
            <div id="detail-content">
                <h2>
                    <span id="detail-title">Select an account from the left</span>
                </h2>
                <div id="balances-table-detail-container" class="hidden">
                    <table id="balances-table-detail" class="usctdp-custom-post-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Session</th>
                                <th>Outstanding Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>