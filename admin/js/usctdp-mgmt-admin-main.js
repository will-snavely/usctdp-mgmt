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

        $('#families-select2').select2({
            placeholder: "Search for a family...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        action: usctdp_mgmt_admin.select2_family_search_action,
                        security: usctdp_mgmt_admin.select2_family_search_nonce,
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
                        active: 0,
                        action: usctdp_mgmt_admin.select2_session_search_action,
                        security: usctdp_mgmt_admin.select2_session_search_nonce,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        function addAction($cell, text, className, data) {
            var $actionItem = $('<div></div>')
            $actionItem.addClass('action-item')
            var $button = $('<button></button>')
            $button.addClass(className)
            $button.addClass("button button-small")
            $button.text(text)
            for (var key in data) {
                $button.attr('data-' + key, data[key])
            }
            $actionItem.append($button)
            $cell.append($actionItem)
        }

        var sessionsRosterTable = $('#session-rosters-table').DataTable({
            processing: true,
            serverSide: false,
            ordering: false,
            searching: false,
            paging: false,
            info: false,
            scrollY: '500px',
            scrollCollapse: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.session_rosters_action;
                    d.security = usctdp_mgmt_admin.session_rosters_nonce;
                }
            },
            columns: [
                { data: 'title' },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var $cell = $('<div></div>')
                            $cell.addClass('session-actions')
                            addAction($cell, 'View Roster', 'view-session-roster', row)
                            addAction($cell, 'Hide Session', 'remove-active-session-btn', row)
                            return $cell.get(0);
                        }
                        return '';
                    },
                    defaultContent: '',
                }
            ],
            "initComplete": function () {
                $('#session-rosters-table').removeClass('hidden');
            }
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
                        action: usctdp_mgmt_admin.toggle_session_active_action,
                        security: usctdp_mgmt_admin.toggle_session_active_nonce,
                        session_id: data.id,
                        active: 1,
                    },
                    success: function (response) {
                        sessionsRosterTable.ajax.reload();
                        $('#active-sessions-select2').val(null).trigger('change');
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown);
                    }
                });
            });
            $('#active-sessions-select2').val(null).trigger('change');
        });

        $('#session-rosters-table').on('click', 'button.remove-active-session-btn', function () {
            var id = $(this).attr('data-id');
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.toggle_session_active_action,
                    security: usctdp_mgmt_admin.toggle_session_active_nonce,
                    session_id: id,
                    active: 0,
                },
                success: function (response) {
                    sessionsRosterTable.ajax.reload();
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                }
            });
        });

        $('#session-rosters-table').on('click', 'button.view-session-roster', function () {
            var drive_id = $(this).attr('data-drive_id');
            var drive_link = 'https://drive.google.com/file/d/' + drive_id + '/edit';
            window.open(drive_link, '_blank');
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

