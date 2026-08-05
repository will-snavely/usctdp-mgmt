<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div id="roster-toolbar" class="flex-row gap-10 align-center">
        <button type="button" id="regenerate-all-rosters-btn" class="button button-primary">
            <span class="button-text">Regenerate All Rosters</span>
        </button>
        <button type="button" id="create-roster-btn" class="button button-secondary">
            <span class="button-text">Create Roster</span>
        </button>
        <span id="regenerate-all-status"></span>
    </div>

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
                    <!-- Nothing is saved on the server until "Save" is clicked - name,
                         additions, and removals are all held as local state until then. -->
                    <div class="modal_field">
                        <label for="edit-roster-name-input">Roster Name</label>
                        <input type="text" id="edit-roster-name-input" placeholder="Defaults to the primary session's name">
                    </div>

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
                    <button type="button" class="button button-primary" id="edit-roster-save-btn">Save</button>
                    <button type="button" class="button" id="edit-roster-cancel-btn">Cancel</button>
                </div>
            </div>
        </div>
    </dialog>

    <dialog id="create-roster-modal">
        <div class="modal-wrap">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Create Roster</h2>
                </div>
                <div class="modal-body flex-col gap-10">
                    <div class="modal_field">
                        <label for="create-roster-name-input">Roster Name</label>
                        <input type="text" id="create-roster-name-input" required>
                    </div>

                    <!-- Nothing is created on the server until "Create" is clicked -
                         this list is purely client-side state until then. -->
                    <div id="create-roster-sessions-wrap">
                        <label>Sessions to add</label>
                        <div id="create-roster-sessions-list" class="flex-col gap-5"></div>
                    </div>

                    <div id="create-roster-add-session-wrap" class="flex-row gap-10 align-center">
                        <div id="create-roster-add-session-select-wrap">
                            <select id="create-roster-session-select"></select>
                        </div>
                        <button type="button" id="create-roster-add-session-btn" class="button button-secondary">Add Session</button>
                    </div>
                </div>
                <div class="actions-footer modal-footer">
                    <button type="button" class="button button-primary" id="create-roster-submit-btn">Create</button>
                    <button type="button" class="button" id="create-roster-cancel-btn">Cancel</button>
                </div>
            </div>
        </div>
    </dialog>
</div>
