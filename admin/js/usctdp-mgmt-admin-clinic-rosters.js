(function ($) {
    "use strict";

    $(document).ready(function () {
        $('#session-selector').select2({
            placeholder: "Search for a session...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        action: usctdp_mgmt_admin.select2_session_search_action,
                        security: usctdp_mgmt_admin.select2_session_search_nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        $('#session-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#activity-selection-section').hide();
            } else {
                $('#activity-selection-section').show();
            }
            $('#activity-selector').val(null);
            $('#activity-selector').trigger('change');
            $('#roster-print-success').hide();
            $('#roster-print-error').hide();
        });

        $('#activity-selector').select2({
            placeholder: "Search for an activity...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        action: usctdp_mgmt_admin.select2_activity_search_action,
                        security: usctdp_mgmt_admin.select2_activity_search_nonce,
                        session_id: $('#session-selector').val()
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        function toggleLoading(isLoading) {
            if (isLoading) {
                $('#print-roster-button .button-text').text('Working...');
                $('#print-roster-button').addClass('is-loading');
                $('#session-selector').attr('disabled', true);
                $('#activity-selector').attr('disabled', true);

            } else {
                $('#print-roster-button .button-text').text('Print Roster');
                $('#print-roster-button').removeClass('is-loading');
                $('#session-selector').attr('disabled', false);
                $('#activity-selector').attr('disabled', false);
            }
        }

        $('#print-roster-button').on('click', function () {
            const selectedActivityId = $('#activity-selector').val();
            if (selectedActivityId === '') {
                return;
            }
            $('#roster-print-success').hide();
            $('#roster-print-error').hide();
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
                    $('#roster-print-success').show();
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    $('#roster-print-error').show();
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
                { data: 'registration_starting_level' },
                {
                    data: 'student',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var cell = '<div class="roster-actions">'
                            cell += '<div class="action-item">'
                            cell += '<a href="#" class="button button-small">Remove Student</a> ';
                            cell += '</div>';
                            cell += '</div>';
                            return cell;
                        }
                        return '';
                    }
                }
            ]
        });

        $('#activity-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#roster-section').hide();
            } else {
                var registerUrl = 'admin.php?page=usctdp-admin-register&activity_id=' + selectedValue;
                $('#roster-section').show();
                $('#register-student-button').attr('href', registerUrl);
                table.ajax.reload();
            }

            $('#roster-print-success').hide();
            $('#roster-print-error').hide();
        });

        $('#roster-print-loading').hide();
        $('#roster-print-success').hide();
        $('#roster-print-error').hide();

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
