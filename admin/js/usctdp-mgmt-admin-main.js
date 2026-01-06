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

        var upcomingSessionsTable = $('#upcoming-sessions-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: false,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.datatable_search_action;
                    d.security = usctdp_mgmt_admin.datatable_search_nonce;
                    d.post_type = 'usctdp-session';
                    d['tag'] = 'active';
                }
            },
            columns: [
                {
                    data: 'title',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.title) {
                            return data.title;
                        }
                        return data;
                    },
                    defaultContent: '',
                },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var $cell = $('<div></div>')
                            $cell.addClass('session-actions')
                            var $actionItem = $('<div></div>')
                            $actionItem.addClass('action-item')
                            var $button = $('<button></button>')
                            $button.addClass('button button-small remove-active-session-btn')
                            $button.attr('data-id', data)
                            $button.text('Remove')
                            $actionItem.append($button)
                            $cell.append($actionItem)
                            return $cell.get(0);
                        }
                        return '';
                    },
                    defaultContent: '',
                }
            ]
        });

        $('#add-active-session-btn').on('click', function () {
            var dataArray = $('#active-sessions-select2').select2('data');
            if (!dataArray || dataArray.length === 0) return;

            dataArray.forEach(function (data) {
                $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: usctdp_mgmt_admin.toggle_tag_action,
                        security: usctdp_mgmt_admin.toggle_tag_nonce,
                        post_id: data.id,
                        tag: 'active',
                        toggle: 'on',
                    },
                    success: function (response) {
                        upcomingSessionsTable.ajax.reload();
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown);
                    }
                });
            });
            $('#active-sessions-select2').val(null).trigger('change');
        });

        $('#upcoming-sessions-table').on('click', 'button.remove-active-session-btn', function () {
            var id = $(this).attr('data-id');
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.toggle_tag_action,
                    security: usctdp_mgmt_admin.toggle_tag_nonce,
                    post_id: id,
                    tag: 'active',
                    toggle: 'off',
                },
                success: function (response) {
                    upcomingSessionsTable.ajax.reload();
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                }
            });
        });
    });
})(jQuery);

