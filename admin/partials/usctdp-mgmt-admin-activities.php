<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

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

        <div id="activity-details-section" class="flex-col gap-10">
            <div class="flex-row gap-10 align-center">
                <h2> Activity Details </h2>
                <button type="button" id="edit-activity-details-btn" class="button button-small">Modify</button>
                <button type="button" id="save-activity-details-btn" class="button button-small hidden">Save</button>
                <button type="button" id="cancel-activity-details-btn" class="button button-small hidden">Cancel</button>
            </div>
            <div id="activity-detail-fields" class="flex-row gap-20 flex-wrap">
                <div id="activity-level-wrap" class="detail-field flex-col gap-5">
                    <label for="activity-level-input" class="upper-heavy">Level</label>
                    <input type="text" id="activity-level-input" placeholder="e.g. 1, 1.5, 2.0-2.5" readonly>
                </div>
                <div id="activity-schedule-wrap" class="flex-row gap-20 flex-wrap hidden">
                    <div class="detail-field flex-col gap-5">
                        <label for="activity-day-select" class="upper-heavy">Day</label>
                        <select id="activity-day-select" disabled>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                    <div class="detail-field flex-col gap-5">
                        <label for="activity-start-time-input" class="upper-heavy">Start</label>
                        <input type="time" id="activity-start-time-input" disabled>
                    </div>
                    <div class="detail-field flex-col gap-5">
                        <label for="activity-end-time-input" class="upper-heavy">End</label>
                        <input type="time" id="activity-end-time-input" disabled>
                    </div>
                </div>
            </div>
            <div id="activity-instructors-wrap" class="flex-col gap-5">
                <label>Instructors</label>
                <div id="activity-instructors-list" class="flex-col gap-5"></div>
                <div id="activity-add-instructor-wrap" class="flex-row gap-10 align-center">
                    <div id="activity-add-instructor-select-wrap">
                        <select id="activity-add-instructor-select"></select>
                    </div>
                    <button type="button" class="button button-secondary" id="activity-add-instructor-btn">
                        Add Instructor
                    </button>
                </div>
            </div>
        </div>

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
            <div id="roster-shared-activities-note" class="edit-note hidden">
                <span>
                    This roster is shared with: <strong id="roster-shared-activities-list"></strong>.
                    The table below lists registrants from every merged class.
                </span>
            </div>
            <div id="roster-table-wrap">
                <table id="roster-table" class="usctdp-datatable">
                    <thead>
                        <tr>
                            <th>First</th>
                            <th>Last</th>
                            <th>Age</th>
                            <th>Level</th>
                            <th>Class</th>
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
