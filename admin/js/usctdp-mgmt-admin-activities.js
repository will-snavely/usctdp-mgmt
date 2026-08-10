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
                var docUrl = 'https://docs.google.com/document/d/' + driveId + '/edit';
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

        function renderSharedActivitiesNote(sharedWith) {
            var isShared = Array.isArray(sharedWith) && sharedWith.length > 0;
            $('#roster-shared-activities-note').toggleClass('hidden', !isShared);
            if (isShared) {
                $('#roster-shared-activities-list').text(sharedWith.join(', '));
            }
        }

        function loadActivityDetails(activityId) {
            if (!activityId) {
                $('#activity-level-input').val('');
                renderActivityInstructors([]);
                renderSharedActivitiesNote([]);
                return;
            }
            USCTDP_Admin.ajax_getActivityDetails(activityId)
                .then(function (data) {
                    $('#activity-level-input').val(data.level || '');
                    renderActivityInstructors(data.instructors);
                    renderSharedActivitiesNote(data.shared_with);
                })
                .catch(function () {
                    $('#activity-level-input').val('');
                    renderActivityInstructors([]);
                    renderSharedActivitiesNote([]);
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

        $('#save-activity-level-btn').on('click', function () {
            const activityId = $('#activity-selector').val();
            if (!activityId) {
                return;
            }
            const level = $('#activity-level-input').val();
            USCTDP_Admin.ajax_updateActivity(activityId, { level: level })
                .then(function () {
                    Swal.fire({
                        title: 'Success',
                        text: 'Level updated.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                })
                .catch(function () {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to update level. Please inform a developer.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
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
