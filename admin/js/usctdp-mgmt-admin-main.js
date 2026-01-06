(function ($) {
    "use strict";
    $(document).ready(function () {
        $('#active-sessions-select2').select2({
            placeholder: "Search for a session...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-session',
                        action: usctdp_mgmt_admin.select2_search_action,
                        security: usctdp_mgmt_admin.select2_search_nonce,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        var activeSessionsTable = $('#active-sessions-table').DataTable({
            paging: false,
            searching: false,
            info: false,
            lengthChange: false
        });

        $('#add-active-session-btn').on('click', function () {
            var dataArray = $('#active-sessions-select2').select2('data');
            if (!dataArray || dataArray.length === 0) return;

            var $inputContainer = $('#hidden-inputs-container');
            dataArray.forEach(function (data) {
                if ($inputContainer.find('input[value="' + data.id + '"]').length > 0) {
                    return;
                }

                var $button = $('<button></button>')
                $button.attr('type', 'button')
                $button.attr('class', 'remove-active-session-btn button-link-delete')
                $button.text('Remove')

                activeSessionsTable.row.add([
                    data.text,
                    $button.get(0)
                ]).draw(false).node().setAttribute('data-id', data.id);

                var $inputElem = $('<input></input>')
                $inputElem.attr('type', 'hidden')
                $inputElem.attr('name', 'usctdp_mgmt_options[active_sessions][]')
                $inputElem.attr('value', data.id)
                $inputContainer.append($inputElem)
            });
            $('#active-sessions-select2').val(null).trigger('change');
        });

        $('#active-sessions-table').on('click', 'button.remove-active-session-btn', function () {
            var $row = $(this).closest('tr');
            var id = $row.data('id');
            var $inputContainer = $('#hidden-inputs-container');
            activeSessionsTable.row($row).remove().draw(false);
            $inputContainer.find('input[value="' + id + '"]').remove();
        });
    });
})(jQuery);

