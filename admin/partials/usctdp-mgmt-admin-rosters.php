<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="selection-section">
        <div id="session-selection-section">
            <h2> Select a Session </h2>
            <select id="session-selector" style="margin-left: 10px;">
            </select>
        </div>
        <div id="class-selection-section">
            <h2> Select a Class </h2>
            <select id="class-selector" style="margin-left: 10px;">
            </select>
        </div>
    </div>
    <div id="roster-section">
        <h2> Roster </h2>
        <div id="roster-table-wrap">
            <table id="roster-table" class="usctdp-custom-post-table">
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
        <ul>
            <li>
                <a class="button button-primary" id="register-student-button">
                    Register Student
                </a>
            </li>
            <li>
                <div id="roster-print-action">
                    <button id="print-roster-button" class="button button-primary">
                        <span class="button-text">Print Roster</span>
                    </button>
                    <span id="roster-print-success" class="success">
                        Success!
                        <a href="" id="roster-link" target="_blank" rel="noopener noreferrer">Click to Open</a>
                    </span>
                    <span id="roster-print-error" class="error">Failed to generate roster. <span id="roster-error"></span></span>
                </div>
            </li>
        </ul>
    </div>
</div>
