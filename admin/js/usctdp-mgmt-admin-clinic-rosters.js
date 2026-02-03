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
                $('#class-selection-section').hide();
            } else {
                $('#class-selection-section').show();
            }
            $('#class-selector').val(null);
            $('#class-selector').trigger('change');
            $('#roster-print-success').hide();
            $('#roster-print-error').hide();
        });

        $('#class-selector').select2({
            placeholder: "Search for a class...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-class',
                        action: usctdp_mgmt_admin.select2_class_search_action,
                        security: usctdp_mgmt_admin.select2_class_search_nonce,
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
                $('#class-selector').attr('disabled', true);

            } else {
                $('#print-roster-button .button-text').text('Print Roster');
                $('#print-roster-button').removeClass('is-loading');
                $('#session-selector').attr('disabled', false);
                $('#class-selector').attr('disabled', false);
            }
        }

        $('#print-roster-button').on('click', function () {
            const selectedValue = $('#class-selector').val();
            if (selectedValue === '') {
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
                    class_id: selectedValue,
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
                    var classFilterValue = $('#class-selector').val();
                    d.action = usctdp_mgmt_admin.registrations_datatable_action;
                    d.security = usctdp_mgmt_admin.registrations_datatable_nonce;
                    d.class_id = classFilterValue;
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

        $('#class-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#roster-section').hide();
            } else {
                var registerUrl = 'admin.php?page=usctdp-admin-register&class_id=' + selectedValue;
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

        if (usctdp_mgmt_admin.preload && usctdp_mgmt_admin.preload.class_id) {
            const preloadedClass = Object.values(usctdp_mgmt_admin.preload.class_id)[0]
            const sessionOption = new Option(
                preloadedClass.session_name,
                preloadedClass.session_id,
                true,
                true
            );
            $('#session-selector').append(sessionOption)
            $('#session-selector').val(preloadedClass.session_id);
            $('#session-selector').trigger('change');

            const classOption = new Option(
                preloadedClass.class_name,
                preloadedClass.class_id,
                true,
                true
            );
            $('#class-selector').append(classOption);
            $('#class-selector').val(preloadedClass.class_id);
            $('#class-selector').trigger('change');
        }
    });
})(jQuery);
