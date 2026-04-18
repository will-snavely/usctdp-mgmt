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
                $('#print-roster-button .button-text').text('Print Roster');
                $('#print-roster-button').removeClass('is-loading');
                $('.selector').attr('disabled', false);
            }
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
                    $('#roster-link').attr('href', response.data.doc_url);
                    $('#roster-print-success').removeClass('hidden');
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
                }
            },
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
                            <div class="roster-actions">
                                <div class="action-item">
                                    <a href="${familyUrl}" class="button button-small">View Family</a>
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
                next: 'activity-selector',
                isRoot: true
            },
            'activity-selector': {
                name: 'activity_id',
                label: 'Activity',
                target: 'activity',
                next: null,
                filter: function () {
                    return {
                        session_id: $('#session-selector').val()
                    };
                }
            }
        };

        const selectHandler = new USCTDP_Admin.CascasdingSelect('context-selectors', clinicSelectors);
        const waitlistSelectHandler = new USCTDP_Admin.CascasdingSelect('waitlist-selectors', waitlistSelectors);

        $('#context-selectors').on('cascade:change', function (e) {
            const { selectorId, value, state } = e.detail;
            $('.print-status').addClass('hidden');
            $('#roster-section').addClass('hidden');
            if (selectorId == 'activity-selector') {
                if (value) {
                    var registerUrl = 'admin.php?page=usctdp-admin-register&activity_id=' + value;
                    $('#roster-section').removeClass('hidden');
                    $('#register-student-button').attr('href', registerUrl);
                    rosterTable.ajax.reload();
                    waitlistTable.ajax.reload();
                    $('#roster-section').removeClass('hidden');
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

        $("#add-waitlist-btn").on("click", function (e) {
            e.preventDefault();
            const form = $('#waitlist-student-form')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
        });

        $('#waitlist-table').on('click', '.remove-waitlist-btn', function (e) {
            const $row = $(this).closest('tr');
            const rowData = waitlistTable.row($row).data();
            const studentId = rowData.student_id;
            const activityId = rowData.activity_id;
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
                        text: 'Failed to remove student from waitlist. Inform a developer.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        });

        var preloadedData = {};
        if (usctdp_mgmt_admin.preload && usctdp_mgmt_admin.preload.activity_id) {
            const preloadedActivity = Object.values(usctdp_mgmt_admin.preload.activity_id)[0];
            preloadedData['session-selector'] = {
                id: preloadedActivity.session_id,
                text: preloadedActivity.session_name,
                disable: true
            };
            preloadedData['activity-selector'] = {
                id: preloadedActivity.activity_id,
                text: preloadedActivity.activity_name,
                disable: true
            };
        }
        selectHandler.applyData(preloadedData);
    });
})(jQuery);
