<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="class-selection">
        <div id="session-selector-wrapper" class="dt-layout-cell dt-layout-start">
            <h2> Select a Session </h2>
            <select id="session-selector" style="margin-left: 10px;">
            </select>
        </div>
        <div id="class-selector-wrapper" class="dt-layout-cell dt-layout-start">
            <h2> Select a Class </h2>
            <select id="class-selector" style="margin-left: 10px;">
            </select>
        </div>
    </div>
    <div id="roster-table-wrapper">
        <h2> Roster </h2>
        <table id="roster-table" class="usctdp-custom-post-table">
            <thead>
                <tr>
                    <th>Student</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>