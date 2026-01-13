(function ($) {
    "use strict";
    $(document).ready(function () {
        function toggleLoading(isLoading, $button) {
            if (isLoading) {
                $button.find('.button-text').text('Working...');
                $button.addClass('is-loading');
            } else {
                $button.find('.button-text').text('Refresh');
                $button.removeClass('is-loading');
            }
        }

        $('#clinic-rosters-table').DataTable({
            paging: false,
            "initComplete": function () {
                $('#clinic-rosters-table').removeClass('hidden');
            }
        });

        $('#session-rosters-table').DataTable({
            paging: false,
            "initComplete": function () {
                $('#session-rosters-table').removeClass('hidden');
            }
        });

        $('#session-rosters-table').on('click', '.refresh-session-roster', function () {
            var id = $(this).data('session-id');
            var $row = $(this).closest('tr');
            $row.find('a').addClass('disabled');
            toggleLoading(true, $row.find('button'));

            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.gen_roster_action,
                    security: usctdp_mgmt_admin.gen_roster_nonce,
                    session_id: id,
                },
                success: function (response) {
                    $row.find('a').removeClass('disabled');
                    toggleLoading(false, $row.find('button'));
                    $row.find('a').attr('href', response.data.doc_url);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    $row.find('a').removeClass('disabled');
                    toggleLoading(false, $row.find('button'));
                }
            });
        })

        $('#clinic-rosters-table').on('click', '.refresh-clinic-roster', function () {
            var id = $(this).data('clinic-id');
            var $row = $(this).closest('tr');
            $row.find('a').addClass('disabled');
            toggleLoading(true, $row.find('button'));

            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.gen_roster_action,
                    security: usctdp_mgmt_admin.gen_roster_nonce,
                    session_id: id,
                },
                success: function (response) {
                    $row.find('a').removeClass('disabled');
                    toggleLoading(false, $row.find('button'));
                    $row.find('a').attr('href', response.doc_url);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    $row.find('a').removeClass('disabled');
                    toggleLoading(false, $row.find('button'));
                }
            });
        })

        $('#families-select2').select2({
            placeholder: "Search for a family...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-family',
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

        $('#active-sessions-select2').select2({
            placeholder: "Search for a session...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-session',
                        tag: 'inactive',
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
                            $button.text('Deactivate')
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
                        activeSessionsTable.ajax.reload();
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown);
                    }
                });
            });
            $('#active-sessions-select2').val(null).trigger('change');
        });

        $('#active-sessions-table').on('click', 'button.remove-active-session-btn', function () {
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
                    activeSessionsTable.ajax.reload();
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                }
            });
        });


        $('#families-select2').on('change', function () {
            var dataArray = $('#families-select2').select2('data');
            if (!dataArray || dataArray.length === 0) {
                $('#manage-family-btn').prop('disabled', true);

            } else {
                $('#manage-family-btn').prop('disabled', false);
            }
        });

        $('#manage-family-btn').on('click', function () {
            var dataArray = $('#families-select2').select2('data');
            if (!dataArray || dataArray.length === 0) return;
            var id = dataArray[0].id;
            window.location.href = usctdp_mgmt_admin.family_url + '&family_id=' + id;
        });
    });
})(jQuery);

