ful(function ($) {
    "use strict";

    $(document).ready(function () {
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

        var table = $('#roster-table').DataTable({
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
                    data: 'student_family_id',
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

        const selectorConfig = {
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

        const selectHandler = new USCTDP_Admin.CascasdingSelect('context-selectors', selectorConfig);

        $('#context-selectors').on('cascade:change', function (e) {
            const { selectorId, value, state } = e.detail;
            $('.print-status').addClass('hidden');
            $('#roster-section').addClass('hidden');
            if (selectorId == 'activity-selector') {
                if (value) {
                    var registerUrl = 'admin.php?page=usctdp-admin-register&activity_id=' + value;
                    $('#roster-section').removeClass('hidden');
                    $('#register-student-button').attr('href', registerUrl);
                    table.ajax.reload();
                    $('#roster-section').removeClass('hidden');
                }
            }
        });

        var preloadedData = {};
        if (usctdp_mgmt_admin.preload && usctdp_mgmt_admin.preload.activity_id) {
            const preloadedActivity = Object.values(usctdp_mgmt_admin.preload.activity_id)[0]
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
