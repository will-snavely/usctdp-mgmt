(function ($) {
    "use strict";

    $(document).ready(function () {

        function renderRosterLink(driveId, generatedAt) {
            if (!driveId) {
                return '<span class="roster-link-none">Not yet generated</span>';
            }
            var docUrl = 'https://docs.google.com/document/d/' + driveId + '/edit';
            var generatedStr = USCTDP_Admin.formatGeneratedAt(generatedAt);
            return '<div class="roster-link-cell">' +
                '<a href="' + docUrl + '" target="_blank" rel="noopener noreferrer">View in Drive</a>' +
                (generatedStr ? '<div class="roster-generated-at">Generated: ' + generatedStr + '</div>' : '') +
                '</div>';
        }

        function addAction($cell, text, className, data, disabled, disabledTitle) {
            var $actionItem = $('<div></div>')
            $actionItem.addClass('action-item')
            var $button = $('<button></button>')
            $button.addClass(className)
            $button.addClass("button button-small")
            $button.text(text)
            if (disabled) {
                $button.prop('disabled', true)
                if (disabledTitle) {
                    $button.attr('title', disabledTitle)
                }
            }
            for (var key in data) {
                $button.attr('data-' + key, data[key])
            }
            $actionItem.append($button)
            $cell.append($actionItem)
        }

        var sessionsRosterTable = $('#session-rosters-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: true,
            paging: true,
            info: true,
            rowId: function (row) {
                return 'session-row-' + row.id;
            },

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.session_rosters_datatable_action;
                    d.security = usctdp_mgmt_admin.session_rosters_datatable_nonce;
                }
            },
            columns: [
                {
                    data: 'name',
                    render: function (data, type, row) {
                        if (type !== 'display') {
                            return row.name;
                        }
                        var sessionsList = '';
                        if (row.sessions && row.sessions.length > 1) {
                            var titles = row.sessions.map(function (s) { return s.title; }).join(', ');
                            sessionsList = '<div class="roster-session-list">' + titles + '</div>';
                        }
                        return '<div class="roster-name-cell">' + row.name + sessionsList + '</div>';
                    }
                },
                {
                    data: 'drive_id',
                    className: 'roster-link-column',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return renderRosterLink(row.drive_id, row.generated_at);
                        }
                        return '';
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var $cell = $('<div></div>')
                            $cell.addClass('session-actions')
                            // Nothing to generate a document from until the
                            // roster has at least one session in it - grey
                            // the button out rather than removing it.
                            var isEmpty = !row.sessions || row.sessions.length === 0;
                            addAction($cell, 'Regenerate', 'print-session-roster', row, isEmpty, 'There are no sessions in this roster.')
                            addAction($cell, 'Edit', 'edit-roster', row)
                            addAction($cell, 'Delete', 'delete-roster-group', row)
                            return $cell.get(0);
                        }
                        return '';
                    },
                    defaultContent: '',
                }
            ],
            autoWidth: false,
            columnDefs: [
                // The actions column holds up to three buttons side by side,
                // so it needs enough room not to wrap them.
                { width: "35%", targets: 0 },
                { width: "25%", targets: 1 },
                { width: "40%", targets: 2 },
            ],
            "initComplete": function () {
                $('#session-rosters-table').removeClass('hidden');
            }
        });

        // Operates on whatever row $btn is actually in, rather than
        // re-locating the row from an id - the row's own primary-session-
        // keyed DOM id and the roster_group_id being regenerated are two
        // different things now, so there's no single id to re-locate by.
        function generateRosterGroupRow($btn, rosterGroupId, openWhenDone) {
            var $spinner = $('<span class="spinner is-active"></span>');
            $btn.prop('disabled', true);
            $btn.text('Working...');
            $btn.after($spinner);
            return USCTDP_Admin.ajax_generateRosterGroup(rosterGroupId)
                .then(function (response) {
                    var row = sessionsRosterTable.row($btn.closest('tr'));
                    if (row.node()) {
                        var rowData = row.data();
                        rowData.drive_id = response.doc_id;
                        rowData.generated_at = response.generated_at;
                        row.data(rowData).draw(false);
                    }
                    if (openWhenDone) {
                        window.open(response.doc_url, '_blank');
                    }
                    return response;
                })
                .catch(function (error) {
                    window.Swal.fire({
                        title: "Error",
                        text: "Failed to generate roster. Inform a developer.",
                        icon: "error"
                    });
                    throw error;
                })
                .finally(function () {
                    $btn.prop('disabled', false);
                    $btn.text('Regenerate');
                    $spinner.remove();
                });
        }

        $('#session-rosters-table').on('click', 'button.print-session-roster', function () {
            var $btn = $(this);
            var $tr = $btn.closest('tr');
            var rowData = sessionsRosterTable.row($tr).data();
            generateRosterGroupRow($btn, rowData.roster_group_id, true);
        });

        $('#regenerate-all-rosters-btn').on('click', async function () {
            var $btn = $(this);
            var $status = $('#regenerate-all-status');
            $btn.prop('disabled', true);

            try {
                var response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: usctdp_mgmt_admin.roster_regenerate_all_action,
                        security: usctdp_mgmt_admin.roster_regenerate_all_nonce,
                    }
                });
                var rosters = (response && response.data) ? response.data : [];

                for (var i = 0; i < rosters.length; i++) {
                    var roster = rosters[i];
                    $status.text('Regenerating ' + (i + 1) + ' of ' + rosters.length + '… (' + roster.title + ')');
                    var $row = $('#session-row-' + roster.id).find('button.print-session-roster');
                    try {
                        if ($row.length) {
                            await generateRosterGroupRow($row, roster.roster_group_id, false);
                        } else {
                            await USCTDP_Admin.ajax_generateRosterGroup(roster.roster_group_id);
                        }
                    } catch (err) {
                        console.error('Failed to regenerate roster ' + roster.roster_group_id, err);
                    }
                }

                $status.text('Done! Regenerated ' + rosters.length + ' roster(s).');
            } catch (error) {
                console.error('Regenerate All Rosters failed:', error);
                $status.text('An error occurred fetching rosters.');
            } finally {
                sessionsRosterTable.ajax.reload(null, false);
                $btn.prop('disabled', false);
                setTimeout(function () {
                    $status.text('');
                }, 8000);
            }
        });

        /* ---------------------------------------------------------------
         * Edit Roster modal (rename / add / remove sessions)
         * ------------------------------------------------------------- */

        const editRosterModal = document.getElementById('edit-roster-modal');
        // Every row in the table is a real explicit group (search_rosters()
        // only returns those), so rosterGroupId is always known here - there
        // is no "still-implicit roster" case for Edit to worry about.
        var editRosterState = { primarySessionId: null, rosterGroupId: null, originalName: '', originalSessionIds: [] };
        // Local working copy of the roster's sessions, edited freely by Add/
        // Remove below - nothing hits the server until "Save" is clicked,
        // which diffs this against originalSessionIds to figure out what
        // actually changed.
        var editRosterSessions = [];

        $('#edit-roster-add-session-select').select2(
            USCTDP_Admin.select2Options({
                placeholder: 'Search for a session to add...',
                allowClear: true,
                target: 'session',
                dropdownParent: $('#edit-roster-modal'),
                filter: function () {
                    return {
                        active: 1,
                        // Only hides sessions already saved in this roster -
                        // a session can belong to more than one now, so it's
                        // fine (expected, even) to offer one that's already
                        // in some other group. A session removed locally but
                        // not yet saved still won't show up here until Save
                        // actually removes it server-side.
                        exclude_roster_group_id: editRosterState.rosterGroupId || 0
                    };
                }
            }));

        function renderEditRosterSessions() {
            var $list = $('#edit-roster-sessions-list');
            $list.empty();
            if (editRosterSessions.length === 0) {
                $list.append($('<div class="roster-session-item-empty"></div>').text('No sessions in this roster.'));
                return;
            }
            editRosterSessions.forEach(function (session) {
                var $item = $('<div class="roster-session-item flex-row gap-10"></div>');
                $item.append($('<span></span>').text(session.title));
                var $removeBtn = $('<button type="button" class="usctdp-remove-btn remove-roster-session-btn">&times;</button>');
                $removeBtn.attr('data-session-id', session.id);
                $item.append($removeBtn);
                $list.append($item);
            });
        }

        function openEditRosterModal(rowData) {
            editRosterState.primarySessionId = rowData.id;
            editRosterState.rosterGroupId = rowData.roster_group_id;
            editRosterState.originalName = rowData.name;
            editRosterState.originalSessionIds = rowData.sessions.map(function (s) { return s.id; });
            editRosterSessions = rowData.sessions.map(function (s) { return { id: s.id, title: s.title }; });
            $('#edit-roster-name-input').val(rowData.name);
            renderEditRosterSessions();
            $('#edit-roster-add-session-select').val(null).trigger('change');
            editRosterModal.showModal();
        }

        $('#session-rosters-table').on('click', 'button.edit-roster', function () {
            var $tr = $(this).closest('tr');
            var rowData = sessionsRosterTable.row($tr).data();
            openEditRosterModal(rowData);
        });

        $('#edit-roster-add-session-btn').on('click', function () {
            var selected = $('#edit-roster-add-session-select').select2('data');
            if (!selected || selected.length === 0) {
                return;
            }
            var picked = selected[0];
            var alreadyAdded = editRosterSessions.some(function (s) { return s.id === picked.id; });
            if (!alreadyAdded) {
                editRosterSessions.push({ id: picked.id, title: picked.text });
                renderEditRosterSessions();
            }
            $('#edit-roster-add-session-select').val(null).trigger('change');
        });

        $('#edit-roster-sessions-list').on('click', '.remove-roster-session-btn', function () {
            var sessionId = $(this).data('session-id');
            editRosterSessions = editRosterSessions.filter(function (s) { return s.id !== sessionId; });
            renderEditRosterSessions();
        });

        $('#edit-roster-cancel-btn').on('click', function () {
            editRosterModal.close();
        });

        // Diffs the local working state against what the roster looked like
        // when the modal opened, and only sends the calls needed to make the
        // server match - existing, untouched members are never touched, so
        // their original add-order (see get_member_session_ids()) survives.
        $('#edit-roster-save-btn').on('click', async function () {
            var name = $('#edit-roster-name-input').val();
            var newIds = editRosterSessions.map(function (s) { return s.id; });
            var toRemove = editRosterState.originalSessionIds.filter(function (id) { return newIds.indexOf(id) === -1; });
            var toAdd = newIds.filter(function (id) { return editRosterState.originalSessionIds.indexOf(id) === -1; });

            var $btn = $(this);
            $btn.prop('disabled', true);
            try {
                if (name !== editRosterState.originalName) {
                    await USCTDP_Admin.ajax_renameRoster(editRosterState.rosterGroupId, editRosterState.primarySessionId, name);
                }
                for (var i = 0; i < toRemove.length; i++) {
                    await USCTDP_Admin.ajax_removeSessionFromRoster(editRosterState.rosterGroupId, toRemove[i]);
                }
                for (var i = 0; i < toAdd.length; i++) {
                    await USCTDP_Admin.ajax_addSessionToRoster(editRosterState.rosterGroupId, editRosterState.primarySessionId, toAdd[i]);
                }
                editRosterModal.close();
                sessionsRosterTable.ajax.reload(null, false);
            } catch (error) {
                window.Swal.fire({
                    title: 'Error',
                    text: error.message || 'Failed to save roster changes.',
                    icon: 'error'
                });
            } finally {
                $btn.prop('disabled', false);
            }
        });

        /* ---------------------------------------------------------------
         * Create Roster modal
         * ------------------------------------------------------------- */

        const createRosterModal = document.getElementById('create-roster-modal');
        // Purely client-side until "Create" is clicked - nothing is created
        // on the server as sessions are added here, unlike the Edit modal's
        // identical-looking add-session flow (which is editing a group that
        // already exists).
        var createRosterSessions = [];

        $('#create-roster-session-select').select2(
            USCTDP_Admin.select2Options({
                placeholder: 'Search for a session to add...',
                allowClear: true,
                target: 'session',
                dropdownParent: $('#create-roster-modal'),
                filter: function () {
                    return { active: 1 };
                }
            }));

        function renderCreateRosterSessions() {
            var $list = $('#create-roster-sessions-list');
            $list.empty();
            if (createRosterSessions.length === 0) {
                $list.append($('<div class="roster-session-item-empty"></div>').text('No sessions added yet.'));
                return;
            }
            createRosterSessions.forEach(function (session) {
                var $item = $('<div class="roster-session-item flex-row gap-10"></div>');
                $item.append($('<span></span>').text(session.title));
                var $removeBtn = $('<button type="button" class="usctdp-remove-btn remove-create-roster-session-btn">&times;</button>');
                $removeBtn.attr('data-session-id', session.id);
                $item.append($removeBtn);
                $list.append($item);
            });
        }

        $('#create-roster-btn').on('click', function () {
            createRosterSessions = [];
            renderCreateRosterSessions();
            $('#create-roster-name-input').val('');
            $('#create-roster-session-select').val(null).trigger('change');
            createRosterModal.showModal();
        });

        $('#create-roster-cancel-btn').on('click', function () {
            createRosterModal.close();
        });

        $('#create-roster-add-session-btn').on('click', function () {
            var selected = $('#create-roster-session-select').select2('data');
            if (!selected || selected.length === 0) {
                return;
            }
            var picked = selected[0];
            var alreadyAdded = createRosterSessions.some(function (s) { return s.id === picked.id; });
            if (!alreadyAdded) {
                createRosterSessions.push({ id: picked.id, title: picked.text });
                renderCreateRosterSessions();
            }
            $('#create-roster-session-select').val(null).trigger('change');
        });

        $('#create-roster-sessions-list').on('click', '.remove-create-roster-session-btn', function () {
            var sessionId = $(this).data('session-id');
            createRosterSessions = createRosterSessions.filter(function (s) { return s.id !== sessionId; });
            renderCreateRosterSessions();
        });

        $('#create-roster-submit-btn').on('click', function () {
            var name = $('#create-roster-name-input').val().trim();
            if (!name) {
                window.Swal.fire({
                    title: 'Error',
                    text: 'Enter a name for the roster.',
                    icon: 'error'
                });
                return;
            }
            if (createRosterSessions.length === 0) {
                window.Swal.fire({
                    title: 'Error',
                    text: 'Add at least one session to start the roster with.',
                    icon: 'error'
                });
                return;
            }
            var sessionIds = createRosterSessions.map(function (s) { return s.id; });
            var $btn = $(this);
            $btn.prop('disabled', true);
            USCTDP_Admin.ajax_createRoster(sessionIds, name)
                .then(function (response) {
                    createRosterModal.close();
                    // Newly-created roster group, opened straight into the
                    // Edit modal so the same add/rename flows can round it
                    // out (add more sessions, rename it) without a second,
                    // largely-duplicate creation UI.
                    sessionsRosterTable.ajax.reload(function () {
                        var match = null;
                        sessionsRosterTable.rows().every(function () {
                            var rowData = this.data();
                            if (rowData.roster_group_id === response.roster_group_id) {
                                match = rowData;
                            }
                        });
                        if (match) {
                            openEditRosterModal(match);
                        }
                    }, false);
                })
                .catch(function (error) {
                    window.Swal.fire({
                        title: 'Error',
                        text: error.message || 'Failed to create roster.',
                        icon: 'error'
                    });
                })
                .finally(function () {
                    $btn.prop('disabled', false);
                });
        });

        /* ---------------------------------------------------------------
         * Delete Roster
         * ------------------------------------------------------------- */

        $('#session-rosters-table').on('click', 'button.delete-roster-group', function () {
            var $tr = $(this).closest('tr');
            var rowData = sessionsRosterTable.row($tr).data();
            var sessionCount = rowData.sessions ? rowData.sessions.length : 0;
            var warning = sessionCount > 1
                ? 'This roster groups ' + sessionCount + ' sessions together. Deleting it splits them back into their own individual rosters - the sessions themselves are not affected.'
                : 'This will delete the roster grouping. The session itself is not affected.';
            window.Swal.fire({
                title: 'Delete this roster?',
                html: warning,
                showDenyButton: true,
                confirmButtonText: 'Yes, Delete',
                denyButtonText: 'Cancel'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }
                USCTDP_Admin.ajax_deleteRosterGroup(rowData.roster_group_id)
                    .then(function () {
                        sessionsRosterTable.ajax.reload(null, false);
                    })
                    .catch(function (error) {
                        window.Swal.fire({
                            title: 'Error',
                            text: error.message || 'Failed to delete roster.',
                            icon: 'error'
                        });
                    });
            });
        });
    });
})(jQuery);
