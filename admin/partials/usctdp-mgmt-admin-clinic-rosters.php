<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="selection-section">
        <div id="session-selection-section" class>
            <h2> Select a Session </h2>
            <select id="session-selector" class="selector">
            </select>
        </div>
        <div id="activity-selection-section" class="hidden">
            <h2> Select an Activity </h2>
            <select id="activity-selector" class="selector">
            </select>
        </div>
    </div>

    <div id="roster-section" class="hidden">
        <h2> Roster </h2>
        <div id="roster-table-wrap">
            <table id="roster-table" class="usctdp-datatable">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Age</th>
                        <th>Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>

        <h2> Actions </h2>
        <div class="roster-actions">
            <div id="register-student-action">
                <a class="button button-primary" id="register-student-button">
                    Register Student
                </a>
            </div>
            <div id="roster-print-action">
                <button id="print-roster-button" class="button button-primary">
                    <span class="button-text">Print Roster</span>
                </button>
                <div id="roster-print-status">
                    <span id="roster-print-success" class="print-status success hidden">
                        Success!
                        <a href="" id="roster-link" target="_blank" rel="noopener noreferrer">Click to Open</a>
                    </span>
                    <span id="roster-print-error" class="print-status error hidden">
                        Failed to generate roster.
                        <span id="roster-error"></span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>