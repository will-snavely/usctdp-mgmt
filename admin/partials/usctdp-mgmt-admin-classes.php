<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div class="usctdp-mgmt-section">
        <section id="usctdp-classes-section">
            <div id="usctdp-classes-container">
                <div id="table-filters">
                    <div id="clinic-filter-section" class="dt-layout-cell dt-layout-start">
                        <label for="clinic-filter">Filter by Clinic:</label>
                        <select id="clinic-filter"></select>
                    </div>
                    <div id="session-filter-section" class="dt-layout-cell dt-layout-start">
                        <label for="session-filter">Filter by Session:</label>
                        <select id="session-filter"></select>
                    </div>
                </div>
                <table id="usctdp-upcoming-classes-table" class="usctdp-custom-post-table hidden">
                    <thead>
                        <tr>
                            <th>Clinic</th>
                            <th>Session</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Capacity</th>
                            <th>Staff</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usctdp-upcoming-classes-table-body">
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>