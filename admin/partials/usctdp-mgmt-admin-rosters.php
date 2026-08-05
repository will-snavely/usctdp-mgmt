<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div id="roster-toolbar" class="flex-row gap-10 align-center">
        <button type="button" id="regenerate-all-rosters-btn" class="button button-primary">
            <span class="button-text">Regenerate All Rosters</span>
        </button>
        <span id="regenerate-all-status"></span>
    </div>

    <h2 class="nav-tab-wrapper" id="rosters-tab-nav">
        <a href="#" class="nav-tab" data-tab="sessions-tab">Sessions</a>
        <a href="#" class="nav-tab" data-tab="activities-tab">Activities</a>
    </h2>

    <div id="sessions-tab" class="roster-tab-panel hidden">
        <div id="session-rosters-container">
            <table id="session-rosters-table" class="usctdp-datatable hidden">
                <thead>
                    <tr>
                        <th>Roster</th>
                        <th>Document</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="session-rosters-table-body">
                </tbody>
            </table>
        </div>

        <dialog id="edit-roster-modal">
            <div class="modal-wrap">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Edit Roster</h2>
                    </div>
                    <div class="modal-body flex-col gap-10">
                        <form id="edit-roster-name-form" class="flex-row gap-10 align-center">
                            <div class="modal_field">
                                <label for="edit-roster-name-input">Roster Name</label>
                                <input type="text" id="edit-roster-name-input" placeholder="Defaults to the primary session's name">
                            </div>
                            <button type="submit" id="edit-roster-save-name-btn" class="button button-secondary">Save Name</button>
                        </form>

                        <div id="edit-roster-sessions-wrap">
                            <label>Sessions in this roster</label>
                            <div id="edit-roster-sessions-list" class="flex-col gap-5"></div>
                        </div>

                        <div id="edit-roster-add-session-wrap" class="flex-row gap-10 align-center">
                            <div id="edit-roster-add-session-select-wrap">
                                <select id="edit-roster-add-session-select"></select>
                            </div>
                            <button type="button" id="edit-roster-add-session-btn" class="button button-secondary">Add Session</button>
                        </div>
                    </div>
                    <div class="actions-footer modal-footer">
                        <button type="button" class="button" id="edit-roster-close-btn">Close</button>
                    </div>
                </div>
            </div>
        </dialog>
    </div>

    <div id="activities-tab" class="roster-tab-panel hidden">
        <div id="context-selectors">
        </div>
        <div id="listings-section" class="hidden flex-row gap-20 flex-wrap">
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

            <div id="rosters-section" class="flex-col gap-10">
                <h2> Roster </h2>
                <div id="roster-actions" class="flex-col gap-5">
                    <div id="roster-link-info" class="flex-row gap-10 align-center">
                        <span id="roster-link-generated" class="hidden">
                            <a href="" id="roster-existing-link" target="_blank" rel="noopener noreferrer">View in Google Drive</a>
                            &mdash; Generated: <span id="roster-generated-at"></span>
                        </span>
                        <span id="roster-link-none" class="hidden">Not yet generated</span>
                    </div>
                    <div id="roster-print-action" class="flex-row gap-10 align-center">
                        <button id="print-roster-button" class="button button-primary">
                            <span class="button-text">Regenerate & Open</span>
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
                <div id="roster-table-wrap">
                    <table id="roster-table" class="usctdp-datatable">
                        <thead>
                            <tr>
                                <th>First</th>
                                <th>Last</th>
                                <th>Age</th>
                                <th>Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="waitlist-section" class="flex-col gap-10">
                <h2> Waitlist </h2>
                <div id="waitlist-actions" class="flex-col gap-5">
                    <div id="waitlist-add-action" class="flex-row gap-10 align-center">
                        <button class="button button-primary" id="waitlist-student-btn">
                            Waitlist Student
                        </button>
                    </div>
                </div>
                <div id="waitlist-table-wrap">
                    <table id="waitlist-table" class="usctdp-datatable">
                        <thead>
                            <tr>
                                <th>First</th>
                                <th>Last</th>
                                <th>Date Added</th>
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
</div>
