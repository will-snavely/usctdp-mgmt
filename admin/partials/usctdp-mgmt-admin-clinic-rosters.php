<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="context-selectors">
    </div>
    <div id="roster-section" class="hidden">
        <dialog id="waitlist-student-modal">
            <div class="modal-wrap">
                <form id="waitlist-student-form" method="dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Add to Waitlist</h2>
                        </div>
                        <div class="modal-body">
                            <div id="waitlist-selectors"></div>
                        </div>
                        <div class="actions-footer modal-footer">
                            <button type="submit" class="button button-primary" id="add-waitlist-btn">Add</button>
                            <button type="button" class="button" id="cancel-waitlist-btn">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </dialog>

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

        <h2> Waitlist </h2>
        <div id="waitlist-table-wrap">
            <table id="waitlist-table" class="usctdp-datatable">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>

        <h2> Actions </h2>
        <div class="roster-actions">
            <div id="waitlist-student-action">
                <button class="button button-primary" id="waitlist-student-btn">
                    Waitlist Student
                </button>
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
