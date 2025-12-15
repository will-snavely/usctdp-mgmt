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
                        post_type: 'usctdp-session',
                        action: usctdp_mgmt_admin.select2_search_action,
                        security: usctdp_mgmt_admin.select2_search_nonce
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
                        action: usctdp_mgmt_admin.select2_search_action,
                        security: usctdp_mgmt_admin.select2_search_nonce,
                        'filter[session][value]': $('#session-selector').val(),
                        'filter[session][compare]': '=',
                        'filter[session][type]': 'NUMERIC'
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        $('#class-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#roster-section').hide();
            } else {
                var registerUrl = 'admin.php?page=usctdp-admin-register&class_id=' + selectedValue;
                $('#roster-section').show();
                $('#register-student-button').attr('href', registerUrl);
            }
            table.ajax.reload();
            $('#roster-print-success').hide();
            $('#roster-print-error').hide();
        });

        function toggleLoading(isLoading) {
            if (isLoading) {
                $('#button-text').text('Working...');
                $('#print-roster-button').addClass('is-loading');
                $('#session-selector').attr('disabled', true);
                $('#class-selector').attr('disabled', true);

            } else {
                $('#button-text').text('Print Roster');
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
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.gen_roster_action,
                    class_id: selectedValue,
                    security: usctdp_mgmt_admin.gen_roster_nonce,
                },
                success: function (response) {
                    const url = 'https://docs.google.com/document/d/' + response.data.doc_id;
                    $('#roster-link').attr('href', url);
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
            searching: true,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var classFilterValue = $('#class-selector').val();
                    d.action = usctdp_mgmt_admin.datatable_search_action;
                    d.security = usctdp_mgmt_admin.datatable_search_nonce;
                    d.post_type = 'usctdp-registration';
                    d['filter[class][value]'] = classFilterValue;
                    d['filter[class][compare]'] = '=';
                    d['filter[class][type]'] = 'NUMERIC';
                }
            },
            columns: [
                {
                    data: 'student',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return data.post_title;
                        }
                        return data;
                    }
                },
                {
                    data: 'class',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return data.post_title;
                        }
                        return data;
                    }
                }
            ]
        });

        $('#roster-print-loading').hide();
        $('#roster-print-success').hide();
        $('#roster-print-error').hide();

        if (usctdp_mgmt_admin.preloaded_session_name) {
            const newOption = new Option(
                usctdp_mgmt_admin.preloaded_session_name,
                usctdp_mgmt_admin.preloaded_session_id,
                true,
                true
            );
            $('#session-selector').append(newOption)
            $('#session-selector').val(usctdp_mgmt_admin.preloaded_session_id);
            $('#session-selector').trigger('change');
        }

        if (usctdp_mgmt_admin.preloaded_class_name) {
            const newOption = new Option(
                usctdp_mgmt_admin.preloaded_class_name,
                usctdp_mgmt_admin.preloaded_class_id,
                true,
                true
            );
            $('#class-selector').append(newOption);
            $('#class-selector').val(usctdp_mgmt_admin.preloaded_class_id);
            $('#class-selector').trigger('change');
        }
    });
})(jQuery);