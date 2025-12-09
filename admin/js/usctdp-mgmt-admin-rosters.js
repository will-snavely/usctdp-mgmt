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
                        action: usctdp_mgmt_admin.search_action,
                        security: usctdp_mgmt_admin.search_nonce
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
                        filter_parent_session: $('#session-selector').val(),
                        action: usctdp_mgmt_admin.search_action,
                        security: usctdp_mgmt_admin.search_nonce
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
                    d.action = usctdp_mgmt_admin.datatable_action;
                    d.security = usctdp_mgmt_admin.datatable_nonce;
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

