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
        <table id="roster-table" class="usctdp-custom-post-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <h2> Actions </h2>
        <ul>
            <li>
                <a id="register-student-button">
                    Register Student
                </a>
            </li>
            <li>
                <a id="print-roster-button" href="javascript:void(0)">
                    Print Roster
                </a>
            </li>
        </ul>
    </div>
</div>