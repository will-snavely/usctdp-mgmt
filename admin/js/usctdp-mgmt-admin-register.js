(function ($) {
    "use strict";

    $(document).ready(function () {
        $('#session-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#class-selector-wrapper').hide();
                $('#class-selector').prop('disabled', true);
            } else {
                $('#class-selector-wrapper').show();
                $('#class-selector').prop('disabled', false);
            }
            $('#class-selector').val(null);
            $('#class-selector').trigger('change');
        });

        $('#class-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#roster-table-wrapper').hide();
            } else {
                $('#roster-table-wrapper').show();
            }
            table.ajax.reload();
        });

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

    $('#student-selector').select2({
        placeholder: "Search for a student...",
        allowClear: true,
        ajax: {
            url: usctdp_mgmt_admin.ajax_url,
            data: function (params) {
                return {
                    q: params.term,
                    post_type: 'usctdp-student',
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

    // Initialization   
    $('#class-selector').prop('disabled', true);
    $('#class-selector-wrapper').hide();
    $('#roster-table-wrapper').hide();

})(jQuery);

