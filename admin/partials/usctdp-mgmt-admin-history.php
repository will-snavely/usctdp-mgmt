<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="main-content">
        <div id="context-selectors"></div>
        <div id="history-container" class="hidden">
            <h2>Registration History for <span id="family-name"></span></h2>
            <div id="history-table-wrap">
                <div id="table-filters">
                    <div id="student-filter-section" class="dt-layout-cell dt-layout-start">
                        <label for="student-filter">Filter by Student:</label>
                        <select id="student-filter"></select>
                    </div>
                    <div id="session-filter-section" class="dt-layout-cell dt-layout-start">
                        <label for="session-filter">Filter by Session:</label>
                        <select id="session-filter"></select>
                    </div>
                </div>
                <table id="history-table" class="reg-history-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Activity</th>
                            <th>Balance</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
