<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="main-content">
        <div id="context-selectors"></div>
        <div id="history-container" class="hidden">
            <h2>Registration History for <span id="student-name"></span></h2>
            <div id="history-table-wrap">
                <table id="history-table" class="usctdp-custom-post-table">
                    <thead>
                        <tr>
                            <th>Registration Details</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
