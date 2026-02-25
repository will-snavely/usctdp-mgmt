(function ($) {
    "use strict";

    $(document).ready(function () {
        $('#session-selector').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a session...",
                allowClear: true,
                action: usctdp_mgmt_admin.select2_session_search_action,
                nonce: usctdp_mgmt_admin.select2_session_search_nonce,
            }));

        $('#session-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#activity-selection-section').addClass('hidden');
            } else {
                $('#activity-selection-section').removeClass('hidden');
            }
            $('#activity-selector').val(null);
            $('#activity-selector').trigger('change');
            $('.print-status').addClass('hidden');
        });

        $('#activity-selector').select2(USCTDP_Admin.select2Options({
            placeholder: "Search for an activity...",
            allowClear: true,
            action: usctdp_mgmt_admin.select2_activity_search_action,
            nonce: usctdp_mgmt_admin.select2_activity_search_nonce,
            filter: function () {
                return {
                    session_id: $('#session-selector').val()
                };
            }
        }));

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

        $('#activity-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#roster-section').addClass('hidden');
            } else {
                var registerUrl = 'admin.php?page=usctdp-admin-register&activity_id=' + selectedValue;
                $('#roster-section').removeClass('hidden');
                $('#register-student-button').attr('href', registerUrl);
                table.ajax.reload();
            }
            $('.print-status').addClass('hidden');
        });

        if (usctdp_mgmt_admin.preload && usctdp_mgmt_admin.preload.activity_id) {
            const preloadedActivity = Object.values(usctdp_mgmt_admin.preload.activity_id)[0]
            const sessionOption = new Option(
                preloadedActivity.session_name,
                preloadedActivity.session_id,
                true,
                true
            );
            $('#session-selector').append(sessionOption)
            $('#session-selector').val(preloadedActivity.session_id);
            $('#session-selector').trigger('change');

            const activityOption = new Option(
                preloadedActivity.activity_name,
                preloadedActivity.activity_id,
                true,
                true
            );
            $('#activity-selector').append(activityOption);
            $('#activity-selector').val(preloadedActivity.activity_id);
            $('#activity-selector').trigger('change');
        }
    });
})(jQuery);
