(function ($) {
    "use strict";

    $(document).ready(function () {

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
                // Generic Drive view URL, not the Docs-editor-specific one -
                // rosters upload as real PDFs now (see
                // upload_document_to_drive() in class-usctdp-mgmt-docgen.php).
                var docUrl = 'https://drive.google.com/file/d/' + driveId + '/view';
                $('#roster-existing-link').attr('href', docUrl);
                $('#roster-generated-at').text(USCTDP_Admin.formatGeneratedAt(generatedAt) || '');
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
                    // Resolved server-side to this activity's reservation
                    // group - roster generation now always writes there
                    // (see ajax_gen_roster()), so the link lookup has to
                    // match or a merged activity would keep showing "Not
                    // yet generated" even after its combined roster exists.
                    activity_id: activityId,
                }
            }).done(function (response) {
                if (response.success) {
                    updateRosterLinkInfo(response.data.drive_id, response.data.generated_at);
                }
            }).fail(function () {
                updateRosterLinkInfo(null, null);
            });
        }

        // Avatar+name template for the OPEN dropdown's option list only. The
        // 40px avatar reads fine there, but templateSelection (the CLOSED
        // select box) is a fixed-height single line that clips anything
        // taller than text - so that one deliberately stays plain text
        // below, rather than reusing this. $('<div>').text(...).html() is
        // select2's own escaping idiom (see its default templateResult).
        function formatStaffResult(item) {
            if (!item.id) {
                return item.text;
            }
            var safeText = $('<div>').text(item.text).html();
            var avatar = item.image_url
                ? '<img class="instructor-avatar" src="' + item.image_url + '" alt="">'
                : '<span class="instructor-avatar instructor-avatar-placeholder"></span>';
            return $('<span class="flex-row gap-5 align-center">' + avatar + '<span>' + safeText + '</span></span>');
        }

        $('#activity-add-instructor-select').select2(
            USCTDP_Admin.select2Options({
                placeholder: 'Search for an instructor to add...',
                allowClear: true,
                target: 'staff',
                filter: function () {
                    return { exclude_activity_id: $('#activity-selector').val() || 0 };
                },
                templateResult: formatStaffResult
            }));

        // Initialized once here (not per modal-open) like every other
        // select2 on this page - the filter reads loadedActivityDetails/
        // groupModalMembers live at search time, so it always reflects
        // whichever activity's group is currently open in the modal
        // without needing to be re-initialized. Restricted to the same
        // session as the activity being managed - "shares court space
        // with" only makes sense within one session's schedule, and
        // groupModalMembers (not loadedActivityDetails.sharedWith) is used
        // for the exclude list so a clinic already staged as an add (but
        // not yet Saved) isn't offered again - see syncGroupModalFields()
        // below for where that array is (re)populated.
        $('#group-add-member-select').select2(
            USCTDP_Admin.select2Options({
                placeholder: 'Search for a clinic to add...',
                allowClear: true,
                target: 'activity',
                dropdownParent: $('#manage-reservation-group-modal'),
                filter: function () {
                    var excludeIds = [$('#activity-selector').val() || 0];
                    groupModalMembers.forEach(function (a) {
                        excludeIds.push(a.id);
                    });
                    return {
                        exclude_activity_ids: excludeIds.join(','),
                        session_id: loadedActivityDetails.sessionId
                    };
                }
            }));

        function renderActivityInstructors(instructors) {
            var $list = $('#activity-instructors-list');
            $list.empty();
            if (!instructors || instructors.length === 0) {
                $list.append($('<div class="instructor-item-empty"></div>').text('No instructors assigned.'));
                return;
            }
            instructors.forEach(function (instructor) {
                var $item = $('<div class="instructor-item flex-row gap-10 align-center"></div>');
                if (instructor.image_url) {
                    $item.append($('<img class="instructor-avatar">').attr('src', instructor.image_url).attr('alt', ''));
                } else {
                    $item.append($('<span class="instructor-avatar instructor-avatar-placeholder"></span>'));
                }
                $item.append($('<span class="instructor-name"></span>').text(instructor.name));
                var $removeBtn = $('<button type="button" class="usctdp-remove-btn remove-instructor-btn">&times;</button>');
                $removeBtn.attr('data-staff-id', instructor.id);
                $item.append($removeBtn);
                $list.append($item);
            });
        }

        // sharedWith is [{id, title}] (see get_shared_activities() /
        // ajax_get_activity_details()) - only .title is needed here.
        function renderSharedActivitiesNote(sharedWith) {
            var isShared = Array.isArray(sharedWith) && sharedWith.length > 0;
            $('#roster-shared-activities-note').toggleClass('hidden', !isShared);
            if (isShared) {
                $('#roster-shared-activities-list').text(sharedWith.map(function (a) { return a.title; }).join(', '));
            }
        }

        // Last-loaded values for the currently-selected activity - the
        // source of truth "Cancel" reverts to and dirty-checking compares
        // against, so neither one needs its own round-trip to the server.
        var loadedActivityDetails = { level: '', type: null, sessionId: null, schedule: null, capacity: null, groupId: null, groupName: null, sharedWith: [] };

        // Tournament activities have no usctdp_clinic row (their schedule is
        // a JSON blob on usctdp_tournament instead - see
        // ajax_get_activity_details()), so the day/time editor only applies
        // to, and only shows up for, clinic-type activities.
        function renderActivitySchedule(type, schedule) {
            var isClinic = type === 'clinic' && !!schedule;
            $('#activity-schedule-wrap').toggleClass('hidden', !isClinic);
            if (isClinic) {
                $('#activity-day-select').val(String(schedule.day_of_week));
                $('#activity-start-time-input').val(schedule.start_time);
                $('#activity-end-time-input').val(schedule.end_time);
            } else {
                $('#activity-day-select').val('1');
                $('#activity-start-time-input').val('');
                $('#activity-end-time-input').val('');
            }
        }

        function enterActivityDetailsEditMode() {
            $('#activity-detail-fields').addClass('editing');
            $('#activity-level-input').prop('readonly', false);
            $('#activity-day-select, #activity-start-time-input, #activity-end-time-input').prop('disabled', false);
            $('#edit-activity-details-btn').addClass('hidden');
            $('#save-activity-details-btn, #cancel-activity-details-btn').removeClass('hidden');
        }

        function exitActivityDetailsEditMode() {
            $('#activity-detail-fields').removeClass('editing');
            $('#activity-level-input').prop('readonly', true);
            $('#activity-day-select, #activity-start-time-input, #activity-end-time-input').prop('disabled', true);
            $('#edit-activity-details-btn').removeClass('hidden');
            $('#save-activity-details-btn, #cancel-activity-details-btn').addClass('hidden');
            $('.detail-field').removeClass('is-dirty');
        }

        // Marks each field that no longer matches what was last loaded from
        // the server, so it's obvious at a glance what a Save is about to
        // change (see .detail-field.is-dirty in usctdp-mgmt-admin-activities.css).
        function updateActivityDetailsDirtyState() {
            $('#activity-level-wrap').toggleClass(
                'is-dirty',
                $('#activity-level-input').val() !== (loadedActivityDetails.level || '')
            );

            var schedule = loadedActivityDetails.schedule;
            if (loadedActivityDetails.type === 'clinic' && schedule) {
                $('#activity-day-select').closest('.detail-field')
                    .toggleClass('is-dirty', $('#activity-day-select').val() !== String(schedule.day_of_week));
                $('#activity-start-time-input').closest('.detail-field')
                    .toggleClass('is-dirty', $('#activity-start-time-input').val() !== schedule.start_time);
                $('#activity-end-time-input').closest('.detail-field')
                    .toggleClass('is-dirty', $('#activity-end-time-input').val() !== schedule.end_time);
            }
        }

        // A clinic activity's title encodes its day/time (see
        // Usctdp_Mgmt_Clinic_Table::create_title()), so saving a schedule
        // change makes the "Day" selector's current label stale - it was
        // rendered from whatever title was current when the option was
        // created (page load, or the last time this ran) and has no way to
        // notice on its own that it's out of date. Updates the underlying
        // <option>'s text/data and asks select2 to re-render the closed
        // selection from it, via the same 'change.select2' - only (not
        // plain 'change', which would re-fire the cascade and reset
        // Product/Day) - trick CascasdingSelect.resetAndHide() already
        // uses.
        function updateActivitySelectorLabel(activityId, newTitle) {
            var $select = $('#activity-selector');
            var $option = $select.find('option[value="' + activityId + '"]');
            if ($option.length === 0) {
                return;
            }
            $option.text(newTitle);
            $option.data('data', $.extend({}, $option.data('data'), { text: newTitle }));
            $select.trigger('change.select2');
        }

        function loadActivityDetails(activityId) {
            exitActivityDetailsEditMode();
            if (!activityId) {
                loadedActivityDetails = { level: '', type: null, schedule: null, capacity: null, groupId: null, groupName: null, sharedWith: [] };
                $('#activity-level-input').val('');
                renderActivityInstructors([]);
                renderSharedActivitiesNote([]);
                renderActivitySchedule(null, null);
                return;
            }
            USCTDP_Admin.ajax_getActivityDetails(activityId)
                .then(function (data) {
                    loadedActivityDetails = {
                        level: data.level || '',
                        type: data.type,
                        sessionId: data.session_id,
                        schedule: data.schedule,
                        capacity: data.capacity,
                        groupId: data.group_id,
                        groupName: data.group_name,
                        sharedWith: data.shared_with
                    };
                    $('#activity-level-input').val(loadedActivityDetails.level);
                    renderActivityInstructors(data.instructors);
                    renderSharedActivitiesNote(data.shared_with);
                    renderActivitySchedule(data.type, data.schedule);
                    // Keeps an already-open modal's radio/capacity/name/
                    // members in sync after a save elsewhere reloads
                    // details (e.g. Level/schedule Save) - see
                    // syncGroupModalFields() below.
                    if (groupModal.open) {
                        syncGroupModalFields();
                    }
                })
                .catch(function () {
                    loadedActivityDetails = { level: '', type: null, schedule: null, capacity: null, groupId: null, groupName: null, sharedWith: [] };
                    $('#activity-level-input').val('');
                    renderActivityInstructors([]);
                    renderSharedActivitiesNote([]);
                    renderActivitySchedule(null, null);
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
                { width: "13%", targets: 0 },
                { width: "13%", targets: 1 },
                { width: "8%", targets: 2 },
                { width: "8%", targets: 3 },
                { width: "23%", targets: 4 },
                { width: "35%", targets: 5 },
            ],
            columns: [
                { data: 'student_first' },
                { data: 'student_last' },
                { data: 'student_age' },
                { data: 'registration_student_level' },
                { data: 'activity_name' },
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
                // active:1 excludes only 'archived' sessions from this
                // typeahead, not 'scheduled' ones (see search_sessions() in
                // Usctdp_Mgmt_Session_Query) - an archived session's
                // activities are still directly reachable via a preloaded
                // deep link (see preloadedData below, which injects the
                // option straight into select2 without going through this
                // filter), just not offered as a fresh search result.
                filter: function () {
                    return { active: 1 };
                },
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
                    $('#listings-section').removeClass('hidden');
                    rosterTable.ajax.reload();
                    waitlistTable.ajax.reload();
                    refreshRosterLinkInfo(value);
                    loadActivityDetails(value);
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

        $('#activity-detail-fields').on('input change', 'input, select', updateActivityDetailsDirtyState);

        $('#edit-activity-details-btn').on('click', function () {
            const activityId = $('#activity-selector').val();
            if (!activityId) {
                return;
            }
            enterActivityDetailsEditMode();
        });

        $('#cancel-activity-details-btn').on('click', function () {
            $('#activity-level-input').val(loadedActivityDetails.level);
            renderActivitySchedule(loadedActivityDetails.type, loadedActivityDetails.schedule);
            exitActivityDetailsEditMode();
        });

        $('#save-activity-details-btn').on('click', function () {
            const activityId = $('#activity-selector').val();
            if (!activityId) {
                return;
            }

            const isClinic = loadedActivityDetails.type === 'clinic';
            const dayOfWeek = $('#activity-day-select').val();
            const startTime = $('#activity-start-time-input').val();
            const endTime = $('#activity-end-time-input').val();

            if (isClinic) {
                if (!startTime || !endTime) {
                    Swal.fire({
                        title: 'Missing Times',
                        text: 'Please provide both a start and end time.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                if (startTime >= endTime) {
                    Swal.fire({
                        title: 'Invalid Times',
                        text: 'End time must be after start time.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
            }

            const saves = [
                USCTDP_Admin.ajax_updateActivity(activityId, { level: $('#activity-level-input').val() })
            ];
            if (isClinic) {
                saves.push(USCTDP_Admin.ajax_updateClinicSchedule(activityId, {
                    day_of_week: dayOfWeek,
                    start_time: startTime,
                    end_time: endTime
                }));
            }

            $('#save-activity-details-btn').prop('disabled', true);
            Promise.all(saves)
                .then(function (results) {
                    Swal.fire({
                        title: 'Success',
                        text: 'Activity details updated.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                    if (isClinic) {
                        // ajax_updateClinicSchedule() is always the second
                        // save pushed above when isClinic is true.
                        updateActivitySelectorLabel(activityId, results[1].title);
                    }
                    loadActivityDetails(activityId);
                })
                .catch(function () {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to update activity details. Please inform a developer.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                })
                .finally(function () {
                    $('#save-activity-details-btn').prop('disabled', false);
                });
        });

        $('#activity-add-instructor-btn').on('click', function () {
            const activityId = $('#activity-selector').val();
            const selected = $('#activity-add-instructor-select').select2('data');
            if (!activityId || !selected || selected.length === 0) {
                return;
            }
            const staffId = selected[0].id;
            USCTDP_Admin.ajax_addActivityInstructor(activityId, staffId)
                .then(function () {
                    $('#activity-add-instructor-select').val(null).trigger('change');
                    loadActivityDetails(activityId);
                })
                .catch(function () {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to add instructor. Please inform a developer.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        });

        $('#activity-instructors-list').on('click', '.remove-instructor-btn', function () {
            const activityId = $('#activity-selector').val();
            const staffId = $(this).data('staff-id');
            if (!activityId) {
                return;
            }
            USCTDP_Admin.ajax_removeActivityInstructor(activityId, staffId)
                .then(function () {
                    loadActivityDetails(activityId);
                })
                .catch(function () {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to remove instructor. Please inform a developer.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
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

        /* ---------------------------------------------------------------
         * Manage Reservation Group modal
         *
         * The radio at the top is purely a view toggle - picking "Shared"
         * doesn't touch the server on its own, it just reveals the name/
         * members fields underneath. The only actions that ever write are:
         * Add/Remove (immediate, each its own call - same pattern as the
         * instructor list above) and Save (capacity, plus name when
         * Shared - or, if Standalone is newly selected on a group that
         * currently has other members, a detach into a fresh dedicated
         * group instead of a plain update - see the Save handler below).
         * ------------------------------------------------------------- */

        const groupModal = document.getElementById('manage-reservation-group-modal');

        function isGroupModalShared() {
            return $('#group-mode-shared').is(':checked');
        }

        function updateGroupModalMode() {
            $('#group-shared-fields').toggleClass('hidden', !isGroupModalShared());
        }

        // sharedWith is [{id, title}] (see get_shared_activities()) - each
        // row's remove button carries the sibling's own id, since removing
        // it means splitting THAT activity out, not the one currently
        // selected on the page. Renders from groupModalMembers (the local,
        // not-yet-saved staging list - see syncGroupModalFields() and the
        // Add/Remove handlers below), not loadedActivityDetails.sharedWith
        // directly.
        function renderGroupModalMembers(members) {
            var $list = $('#group-modal-members-list');
            $list.empty();
            if (!members || members.length === 0) {
                $list.append($('<div class="instructor-item-empty"></div>').text('Not sharing court space with anything else.'));
                return;
            }
            members.forEach(function (activity) {
                var $item = $('<div class="instructor-item flex-row gap-10 align-center"></div>');
                $item.append($('<span></span>').text(activity.title));
                var $removeBtn = $('<button type="button" class="usctdp-remove-btn remove-group-member-btn">&times;</button>');
                $removeBtn.attr('data-activity-id', activity.id);
                $item.append($removeBtn);
                $list.append($item);
            });
        }

        // Local working copy of the group's members, edited freely by
        // Add/Remove below - nothing hits the server until Save, which
        // diffs this against loadedActivityDetails.sharedWith (what was
        // actually loaded) to figure out what changed. Same
        // stage-then-diff-on-save pattern as the Rosters page's Edit
        // Roster modal (editRosterSessions in usctdp-mgmt-admin-rosters.js).
        var groupModalMembers = [];

        function isGroupModalShared() {
            return $('#group-mode-shared').is(':checked');
        }

        function updateGroupModalMode() {
            $('#group-shared-fields').toggleClass('hidden', !isGroupModalShared());
        }

        // Re-populates every modal field (including resetting
        // groupModalMembers to a fresh copy of what's actually saved) from
        // the current loadedActivityDetails - called both when the modal
        // opens and after Save succeeds and reloads it.
        function syncGroupModalFields() {
            var isShared = (loadedActivityDetails.sharedWith || []).length > 0;
            $('#group-mode-standalone, #group-mode-shared').prop('checked', false);
            $(isShared ? '#group-mode-shared' : '#group-mode-standalone').prop('checked', true);
            updateGroupModalMode();

            $('#group-modal-subtitle').text('Group #' + loadedActivityDetails.groupId);
            $('#group-capacity-input').val(loadedActivityDetails.capacity ?? '');
            $('#group-name-input').val(loadedActivityDetails.groupName || '');
            $('#group-add-member-select').val(null).trigger('change');
            groupModalMembers = (loadedActivityDetails.sharedWith || []).map(function (a) {
                return { id: a.id, title: a.title };
            });
            renderGroupModalMembers(groupModalMembers);
        }

        $('#manage-group-btn').on('click', function () {
            const activityId = $('#activity-selector').val();
            if (!activityId) {
                return;
            }
            syncGroupModalFields();
            groupModal.showModal();
        });

        $('#group-mode-standalone, #group-mode-shared').on('change', updateGroupModalMode);

        $('#group-modal-close-btn').on('click', function () {
            groupModal.close();
        });

        $('#group-add-member-btn').on('click', function () {
            const selected = $('#group-add-member-select').select2('data');
            if (!selected || selected.length === 0) {
                return;
            }
            const picked = selected[0];
            // select2 option ids are strings; loadedActivityDetails.sharedWith's
            // are ints (see get_shared_activities()) - normalized here so
            // the id comparisons in the Save diff below aren't comparing a
            // string against a number.
            const pickedId = parseInt(picked.id, 10);
            const alreadyStaged = groupModalMembers.some(function (a) { return a.id === pickedId; });
            if (!alreadyStaged) {
                groupModalMembers.push({ id: pickedId, title: picked.text });
                renderGroupModalMembers(groupModalMembers);
            }
            $('#group-add-member-select').val(null).trigger('change');
        });

        $('#group-modal-members-list').on('click', '.remove-group-member-btn', function () {
            const memberId = $(this).data('activity-id');
            groupModalMembers = groupModalMembers.filter(function (a) { return a.id !== memberId; });
            renderGroupModalMembers(groupModalMembers);
        });

        $('#group-save-btn').on('click', function () {
            const activityId = $('#activity-selector').val();
            if (!activityId) {
                return;
            }
            const capacityRaw = $('#group-capacity-input').val();
            const capacity = parseInt(capacityRaw, 10);
            if (capacityRaw === '' || isNaN(capacity) || capacity < 0) {
                Swal.fire({
                    title: 'Invalid Capacity',
                    text: 'Capacity must be a non-negative whole number.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            const wasShared = (loadedActivityDetails.sharedWith || []).length > 0;
            const nowShared = isGroupModalShared();
            const $btn = $(this);

            if (wasShared && !nowShared) {
                // Switching to Standalone on a group that currently has
                // other members detaches THIS activity into a fresh
                // dedicated group - the siblings stay behind, still
                // sharing whatever's left of the old one. Independent of
                // any staged Add/Remove edits, which are discarded along
                // with the rest of the (now-abandoned) Shared view.
                Swal.fire({
                    title: 'Make this clinic standalone?',
                    html: 'It will move into its own dedicated registration pool, separate from the clinics it currently shares with.',
                    showDenyButton: true,
                    confirmButtonText: 'Yes, Make Standalone',
                    denyButtonText: 'Cancel'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }
                    $btn.prop('disabled', true);
                    USCTDP_Admin.ajax_createActivityGroup(activityId, capacity, null)
                        .then(function () {
                            groupModal.close();
                            Swal.fire({ title: 'Success', text: 'This clinic is now standalone.', icon: 'success', confirmButtonText: 'OK' });
                            loadActivityDetails(activityId);
                        })
                        .catch(function (error) {
                            Swal.fire({
                                title: 'Error',
                                text: error.message || 'Failed to make this clinic standalone. Please inform a developer.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        })
                        .finally(function () {
                            $btn.prop('disabled', false);
                        });
                });
                return;
            }

            // Diffs the staged member list against what was actually
            // loaded, so Save only touches whichever clinics actually
            // changed - existing, untouched members are never re-sent.
            const originalMembers = loadedActivityDetails.sharedWith || [];
            const originalIds = originalMembers.map(function (a) { return a.id; });
            const currentIds = nowShared ? groupModalMembers.map(function (a) { return a.id; }) : [];
            const toAdd = nowShared ? groupModalMembers.filter(function (a) { return originalIds.indexOf(a.id) === -1; }) : [];
            const toRemove = nowShared ? originalMembers.filter(function (a) { return currentIds.indexOf(a.id) === -1; }) : [];

            function applySave() {
                $btn.prop('disabled', true);
                const ops = [];
                // The added clinic adopts THIS group's capacity/name as-is
                // (a direct repoint, not a merge of two pools) - see
                // Usctdp_Mgmt_Reservation_Group_Query::move_activity_to_group().
                toAdd.forEach(function (a) {
                    ops.push(USCTDP_Admin.ajax_moveActivityToGroup(a.id, loadedActivityDetails.groupId));
                });
                toRemove.forEach(function (a) {
                    ops.push(USCTDP_Admin.ajax_createActivityGroup(a.id, capacity, null));
                });
                ops.push(USCTDP_Admin.ajax_saveActivityGroupDetails(activityId, capacity, nowShared ? $('#group-name-input').val() : undefined));
                Promise.all(ops)
                    .then(function () {
                        groupModal.close();
                        Swal.fire({ title: 'Success', text: 'Group details saved.', icon: 'success', confirmButtonText: 'OK' });
                        loadActivityDetails(activityId);
                    })
                    .catch(function (error) {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Failed to save group details. Please inform a developer.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    })
                    .finally(function () {
                        $btn.prop('disabled', false);
                    });
            }

            if (toRemove.length > 0) {
                // Removing a member reaches out and detaches a DIFFERENT
                // activity than the one selected on the page - worth a
                // confirm since it's not the obviously-expected side
                // effect of clicking Save on this activity's own modal.
                const removedNames = toRemove.map(function (a) { return a.title; }).join(', ');
                Swal.fire({
                    title: 'Save these changes?',
                    html: removedNames + ' will be split into ' + (toRemove.length > 1 ? 'their own' : 'its own') +
                        ' dedicated registration pool' + (toRemove.length > 1 ? 's' : '') + ' (capacity ' + capacity + ').',
                    showDenyButton: true,
                    confirmButtonText: 'Yes, Save',
                    denyButtonText: 'Cancel'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        applySave();
                    }
                });
                return;
            }

            applySave();
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
