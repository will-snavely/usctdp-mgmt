(function ($) {
    "use strict";

    $(document).ready(function () {

        /* ---------------------------------------------------------------
         * Tab switching
         * ------------------------------------------------------------- */

        function activateTab(tabId) {
            $('.roster-tab-panel').addClass('hidden');
            $('#' + tabId).removeClass('hidden');
            $('#rosters-tab-nav .nav-tab').removeClass('nav-tab-active');
            $('#rosters-tab-nav .nav-tab[data-tab="' + tabId + '"]').addClass('nav-tab-active');
        }

        $('#rosters-tab-nav .nav-tab').on('click', function (e) {
            e.preventDefault();
            activateTab($(this).data('tab'));
        });

        var hasActivityPreload = !!(usctdp_mgmt_admin.preload && usctdp_mgmt_admin.preload.activity_id);
        activateTab(hasActivityPreload ? 'activities-tab' : 'sessions-tab');

        /* ---------------------------------------------------------------
         * Shared helpers
         * ------------------------------------------------------------- */

        function formatGeneratedAt(value) {
            if (!value) {
                return null;
            }
            return new Date(value).toLocaleString();
        }

        function renderRosterLink(driveId, generatedAt) {
            if (!driveId) {
                return '<span class="roster-link-none">Not yet generated</span>';
            }
            var docUrl = 'https://drive.google.com/file/d/' + driveId + '/edit';
            var generatedStr = formatGeneratedAt(generatedAt);
            return '<div class="roster-link-cell">' +
                '<a href="' + docUrl + '" target="_blank" rel="noopener noreferrer">View in Drive</a>' +
                (generatedStr ? '<div class="roster-generated-at">Generated: ' + generatedStr + '</div>' : '') +
                '</div>';
        }

        /* ---------------------------------------------------------------
         * Sessions tab
         * ------------------------------------------------------------- */

        function addAction($cell, text, className, data) {
            var $actionItem = $('<div></div>')
            $actionItem.addClass('action-item')
            var $button = $('<button></button>')
            $button.addClass(className)
            $button.addClass("button button-small")
            $button.text(text)
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
                { data: 'title' },
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
                            addAction($cell, 'Regenerate & Open', 'print-session-roster', row)
                            return $cell.get(0);
                        }
                        return '';
                    },
                    defaultContent: '',
                }
            ],
            autoWidth: false,
            columnDefs: [
                { width: "45%", targets: 0 },
                { width: "35%", targets: 1 },
                { width: "20%", targets: 2 },
            ],
            "initComplete": function () {
                $('#session-rosters-table').removeClass('hidden');
            }
        });

        function generateSessionRosterRow($btn, sessionId, openWhenDone) {
            var $spinner = $('<span class="spinner is-active"></span>');
            $btn.prop('disabled', true);
            $btn.text('Working...');
            $btn.after($spinner);
            return USCTDP_Admin.ajax_generateSessionRoster(sessionId)
                .then(function (response) {
                    var row = sessionsRosterTable.row('#session-row-' + sessionId);
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
                    $btn.text('Regenerate & Open');
                    $spinner.remove();
                });
        }

        $('#session-rosters-table').on('click', 'button.print-session-roster', function () {
            var $btn = $(this);
            var $tr = $btn.closest('tr');
            var rowData = sessionsRosterTable.row($tr).data();
            generateSessionRosterRow($btn, rowData.id, true);
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
                        action: usctdp_mgmt_admin.session_rosters_action,
                        security: usctdp_mgmt_admin.session_rosters_nonce,
                    }
                });
                var sessions = (response && response.data) ? response.data : [];

                for (var i = 0; i < sessions.length; i++) {
                    var session = sessions[i];
                    $status.text('Regenerating ' + (i + 1) + ' of ' + sessions.length + '… (' + session.title + ')');
                    var $row = $('#session-row-' + session.id).find('button.print-session-roster');
                    try {
                        if ($row.length) {
                            await generateSessionRosterRow($row, session.id, false);
                        } else {
                            await USCTDP_Admin.ajax_generateSessionRoster(session.id);
                        }
                    } catch (err) {
                        console.error('Failed to regenerate roster for session ' + session.id, err);
                    }
                }

                $status.text('Done! Regenerated ' + sessions.length + ' roster(s).');
            } catch (error) {
                console.error('Regenerate All Rosters failed:', error);
                $status.text('An error occurred fetching active sessions.');
            } finally {
                sessionsRosterTable.ajax.reload(null, false);
                $btn.prop('disabled', false);
                setTimeout(function () {
                    $status.text('');
                }, 8000);
            }
        });

        /* ---------------------------------------------------------------
         * Activities tab
         * ------------------------------------------------------------- */

        const waitlistStudentModal = document.getElementById('waitlist-student-modal');

        function toggleLoading(isLoading) {
            if (isLoading) {
                $('#print-roster-button .button-text').text('Working...');
                $('#print-roster-button').addClass('is-loading');
                $('.selector').attr('disabled', true);
            } else {
                $('#print-roster-button .button-text').text('Regenerate & Open');
                $('#print-roster-button').removeClass('is-loading');
                $('.selector').attr('disabled', false);
            }
        }

        function updateRosterLinkInfo(driveId, generatedAt) {
            if (driveId) {
                var docUrl = 'https://drive.google.com/file/d/' + driveId + '/edit';
                $('#roster-existing-link').attr('href', docUrl);
                $('#roster-generated-at').text(formatGeneratedAt(generatedAt) || '');
                $('#roster-link-generated').removeClass('hidden');
                $('#roster-link-none').addClass('hidden');
            } else {
                $('#roster-link-generated').addClass('hidden');
                $('#roster-link-none').removeClass('hidden');
            }
        }

        function refreshRosterLinkInfo(activityId) {
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.roster_link_action,
                    security: usctdp_mgmt_admin.roster_link_nonce,
                    entity_id: activityId,
                }
            }).done(function (response) {
                if (response.success) {
                    updateRosterLinkInfo(response.data.drive_id, response.data.generated_at);
                }
            }).fail(function () {
                updateRosterLinkInfo(null, null);
            });
        }

        $('#print-roster-button').on('click', function () {
            const selectedActivityId = $('#activity-selector').val();
            if (selectedActivityId === '') {
                return;
            }
            $('.print-status').addClass('hidden');
            toggleLoading(true);
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.gen_roster_action,
                    activity_id: selectedActivityId,
                    security: usctdp_mgmt_admin.gen_roster_nonce,
                },
                success: function (response) {
                    window.open(response.data.doc_url, '_blank');
                    updateRosterLinkInfo(response.data.doc_id, response.data.generated_at);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    $('#roster-print-error').removeClass('hidden');
                },
                complete: function () {
                    toggleLoading(false);
                }
            });
        });

        var rosterTable = $('#roster-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var activityFilterValue = $('#activity-selector').val();
                    d.action = usctdp_mgmt_admin.registrations_datatable_action;
                    d.security = usctdp_mgmt_admin.registrations_datatable_nonce;
                    d.activity_id = activityFilterValue;
                    d.status = 'active';
                }
            },
            autoWidth: false,
            columnDefs: [
                { width: "15%", targets: 0 },
                { width: "15%", targets: 1 },
                { width: "10%", targets: 2 },
                { width: "10%", targets: 3 },
                { width: "50%", targets: 4 },
            ],
            columns: [
                { data: 'student_first' },
                { data: 'student_last' },
                { data: 'student_age' },
                { data: 'registration_student_level' },
                {
                    data: 'family_id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var familyUrl = 'admin.php?page=usctdp-admin-families&family_id=' + data;
                            return `
                            <div class="flex-row gap-5">
                                <div class="action-item">
                                    <a href="${familyUrl}" class="button button-small">View Family</a>
                                </div>
                                <div class="action-item">
                                    <button class="button button-small remove-roster-btn">Remove</button>
                                </div>
                            </div>`;
                        }
                        return '';
                    }
                }
            ]
        });

        var waitlistTable = $('#waitlist-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var activityFilterValue = $('#activity-selector').val();
                    d.action = usctdp_mgmt_admin.waitlist_datatable_action;
                    d.security = usctdp_mgmt_admin.waitlist_datatable_nonce;
                    d.activity_id = activityFilterValue;
                }
            },
            autoWidth: false,
            columnDefs: [
                { width: "15%", targets: 0 },
                { width: "15%", targets: 1 },
                { width: "30%", targets: 2 },
                { width: "40%", targets: 3 },
            ],
            columns: [
                { data: 'student_first' },
                { data: 'student_last' },
                {
                    data: 'waitlist_created_at',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            const createdDate = new Date(data).toLocaleString();
                            return createdDate;
                        }
                        return data;
                    }
                },
                {
                    data: 'activity_id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var activity_id = data;
                            var student_id = row.student_id;
                            var registerUrl = `admin.php?page=usctdp-admin-register&activity_id=${activity_id}&student_id=${student_id}`;
                            return `
                            <div class="flex-row gap-5">
                                <div class="action-item">
                                    <a href="${registerUrl}" class="button button-small">Register</a>
                                </div>
                                <div class="action-item">
                                    <button class="button button-small remove-waitlist-btn">Remove</button>
                                </div>
                            </div>`;
                        }
                        return '';
                    }
                }
            ]
        });

        const waitlistSelectors = {
            'family-selector': {
                name: 'family_id',
                label: 'Family',
                target: 'family',
                next: 'student-selector',
                dropdownParent: $('#waitlist-student-modal'),
                isRoot: true,
                required: true
            },
            'student-selector': {
                name: 'student_id',
                label: 'Student',
                target: 'student',
                next: null,
                required: true,
                dropdownParent: $('#waitlist-student-modal'),
                filter: function () {
                    return {
                        family_id: $('#family-selector').val()
                    };
                }
            }
        };

        const clinicSelectors = {
            'session-selector': {
                name: 'session_id',
                label: 'Session',
                target: 'session',
                // Tournament sessions have exactly one activity, so there's
                // nothing left to pick - skip straight past Product/Day.
                next: function (value, $el) {
                    if (!value) {
                        return 'product-selector';
                    }
                    var sessionData = $el.select2('data')[0];
                    var isTournament = sessionData &&
                        USCTDP_Admin.TOURNAMENT_SESSION_CATEGORIES.indexOf(sessionData.category) !== -1;
                    return isTournament ? null : 'product-selector';
                },
                branches: ['product-selector', 'activity-selector'],
                autoSelectChild: {
                    id: 'activity-selector',
                    resolve: function (value, $el) {
                        return USCTDP_Admin.resolveTournamentActivity(value, $el.select2('data')[0]);
                    }
                },
                isRoot: true
            },
            'product-selector': {
                name: 'product_id',
                label: 'Clinic',
                target: 'product',
                next: 'activity-selector',
                filter: function () {
                    return {
                        session_id: $('#session-selector').val(),
                    };
                }
            },
            'activity-selector': {
                name: 'activity_id',
                label: 'Day',
                target: 'activity',
                next: null,
                filter: function () {
                    return {
                        session_id: $('#session-selector').val(),
                        product_id: $('#product-selector').val(),
                    };
                }
            }
        };

        const selectHandler = new USCTDP_Admin.CascasdingSelect('context-selectors', clinicSelectors);
        const waitlistSelectHandler = new USCTDP_Admin.CascasdingSelect('waitlist-selectors', waitlistSelectors);

        $('#context-selectors').on('cascade:change', function (e) {
            const { selectorId, value, state } = e.detail;
            $('.print-status').addClass('hidden');
            $('#listings-section').addClass('hidden');
            if (selectorId == 'activity-selector') {
                if (value) {
                    var registerUrl = 'admin.php?page=usctdp-admin-register&activity_id=' + value;
                    $('#listings-section').removeClass('hidden');
                    $('#register-student-button').attr('href', registerUrl);
                    rosterTable.ajax.reload();
                    waitlistTable.ajax.reload();
                    refreshRosterLinkInfo(value);
                    // Both tables were initialized while this section was
                    // hidden, so DataTables locked in collapsed column
                    // widths at init time - recompute now that it's visible.
                    rosterTable.columns.adjust();
                    waitlistTable.columns.adjust();
                }
            }
        });

        $("#waitlist-student-btn").on("click", function () {
            waitlistSelectHandler.reset();
            waitlistStudentModal.showModal();
        });

        $("#add-waitlist-btn").on("click", function (e) {
            const form = $('#waitlist-student-form')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            e.preventDefault();

            const studentId = $('#student-selector').val();
            const activityId = $('#activity-selector').val();
            USCTDP_Admin.ajax_addWaitlistStudent(studentId, activityId)
                .then(function () {
                    waitlistStudentModal.close();
                    waitlistTable.ajax.reload();
                    Swal.fire({
                        title: 'Success',
                        text: 'Student added to waitlist.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                })
                .catch(function (error) {
                    waitlistStudentModal.close();
                    waitlistTable.ajax.reload();
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to add student to waitlist. Inform a developer.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        });

        $("#cancel-waitlist-btn").on("click", function () {
            waitlistStudentModal.close();
        });

        $('#roster-table').on('click', '.remove-roster-btn', function (e) {
            const $row = $(this).closest('tr');
            const rowData = rosterTable.row($row).data();
            var registrationId = rowData.registration_id;
            var update = {
                status: 'void'
            };

            window.Swal.fire({
                title: "Confirm Roster Removal",
                html: `
                    Are you sure you want to remove
                    <b>${rowData.student_first} ${rowData.student_last}</b>
                    from the roster for
                    <b> ${rowData.activity_name}</b>?
                `,
                showDenyButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `No`
            }).then((result) => {
                if (result.isConfirmed) {
                    USCTDP_Admin.ajax_saveRegistrationFields(registrationId, update)
                        .then(function () {
                            Swal.fire({
                                icon: "success",
                                title: "Success!",
                                text: "Registration voided successfully.",
                            });
                            rosterTable.ajax.reload();
                        })
                        .catch((error) => {
                            Swal.fire({
                                icon: "error",
                                title: "Error!",
                                text: "A server error occured. Please inform a developer.",
                            });
                        });
                }
            });
        });

        $('#waitlist-table').on('click', '.remove-waitlist-btn', function (e) {
            const $row = $(this).closest('tr');
            const rowData = waitlistTable.row($row).data();
            const studentId = rowData.student_id;
            const activityId = rowData.activity_id;

            window.Swal.fire({
                title: "Confirm Waitlist Removal",
                html: `
                    Are you sure you want to remove
                    <b>${rowData.student_first} ${rowData.student_last}</b>
                    from the waitlist for
                    <b> ${rowData.activity_name}</b>?
                `,
                showDenyButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `No`
            }).then((result) => {
                if (result.isConfirmed) {
                    USCTDP_Admin.ajax_removeWaitlistStudent(studentId, activityId)
                        .then(function () {
                            waitlistTable.ajax.reload();
                            Swal.fire({
                                title: 'Success',
                                text: 'Student removed from waitlist.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            });
                        })
                        .catch(function (error) {
                            waitlistTable.ajax.reload();
                            Swal.fire({
                                title: 'Error',
                                text: 'Failed to remove student from waitlist. Please inform a developer.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        });

        var preloadedData = {};
        if (usctdp_mgmt_admin.preload && usctdp_mgmt_admin.preload.activity_id) {
            const preloadedActivity = Object.values(usctdp_mgmt_admin.preload.activity_id)[0];
            preloadedData['session-selector'] = {
                id: preloadedActivity.session_id,
                text: preloadedActivity.session_name,
                disable: false
            };
            preloadedData['product-selector'] = {
                id: preloadedActivity.product_id,
                text: preloadedActivity.product_name,
                disable: false
            };
            preloadedData['activity-selector'] = {
                id: preloadedActivity.activity_id,
                text: preloadedActivity.activity_name,
                disable: false
            };
        }
        selectHandler.applyData(preloadedData);
    });
})(jQuery);
