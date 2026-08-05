(function ($) {
    "use strict";

    $(document).ready(function () {
        $('#clinic-rosters-table').DataTable({
            paging: false,
            "initComplete": function () {
                $('#clinic-rosters-table').removeClass('hidden');
            }
        });

        $('#families-select2').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a family...",
                allowClear: true,
                target: 'family'
            }));

        function addAction($cell, text, className, data, disabled, disabledTitle) {
            var $actionItem = $('<div></div>')
            $actionItem.addClass('action-item')
            var $button = $('<button></button>')
            $button.addClass(className)
            $button.addClass("button button-small")
            $button.text(text)
            if (disabled) {
                $button.prop('disabled', true)
                if (disabledTitle) {
                    $button.attr('title', disabledTitle)
                }
            }
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
            rowId: function (row) {
                return 'roster-row-' + row.id;
            },

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.session_rosters_action;
                    d.security = usctdp_mgmt_admin.session_rosters_nonce;
                }
            },
            columns: [
                {
                    data: 'name',
                    render: function (data, type, row) {
                        if (type !== 'display') {
                            return row.name;
                        }
                        var sessionsList = '';
                        if (row.sessions && row.sessions.length > 1) {
                            var titles = row.sessions.map(function (s) { return s.title; }).join(', ');
                            sessionsList = '<div class="roster-session-list">' + titles + '</div>';
                        }
                        return '<div class="roster-name-cell">' + row.name + sessionsList + '</div>';
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var $cell = $('<div></div>')
                            $cell.addClass('session-actions')
                            addAction($cell, 'View', 'view-roster-link', { id: row.id, 'drive-id': row.drive_id || '' }, !row.drive_id, 'Not yet generated')
                            var isEmpty = !row.sessions || row.sessions.length === 0;
                            addAction($cell, 'Regenerate', 'regenerate-roster-btn', { id: row.id }, isEmpty, 'There are no sessions in this roster.')
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

        // Just opens whatever doc is already there - never hits the server.
        $('#session-rosters-table').on('click', 'button.view-roster-link', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }
            var driveId = $btn.attr('data-drive-id');
            if (!driveId) {
                return;
            }
            window.open('https://drive.google.com/file/d/' + driveId + '/edit', '_blank');
        });

        $('#session-rosters-table').on('click', 'button.regenerate-roster-btn', function () {
            var $btn = $(this);
            var $tr = $btn.closest('tr');
            var rowData = sessionsRosterTable.row($tr).data();
            var originalText = $btn.text();
            var $spinner = $('<span class="spinner is-active"></span>');

            $btn.prop('disabled', true);
            $btn.text('Working...');
            $btn.after($spinner);

            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.gen_roster_action,
                    security: usctdp_mgmt_admin.gen_roster_nonce,
                    session_id: rowData.id,
                },
                success: function (response) {
                    var row = sessionsRosterTable.row($tr);
                    if (row.node()) {
                        rowData.drive_id = response.data.doc_id;
                        rowData.generated_at = response.data.generated_at;
                        row.data(rowData).draw(false);
                    }
                    window.open(response.data.doc_url, '_blank');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    window.Swal.fire({
                        title: 'Error',
                        text: 'Failed to generate roster. Inform a developer.',
                        icon: 'error'
                    });
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $btn.text(originalText);
                    $spinner.remove();
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

        function loadRecentRegistrations() {
            var $tbody = $('#recent-registrations-table-body');
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.recent_registrations_action,
                    security: usctdp_mgmt_admin.recent_registrations_nonce,
                    limit: 8
                },
                success: function (response) {
                    $tbody.empty();
                    var rows = (response && response.data) || [];
                    if (rows.length === 0) {
                        $tbody.append('<tr class="empty-row"><td colspan="4">No recent registrations.</td></tr>');
                        return;
                    }
                    rows.forEach(function (row) {
                        var $tr = $('<tr></tr>');
                        $tr.append($('<td></td>').text(row.student_name));
                        $tr.append($('<td></td>').text(row.family_name));
                        $tr.append($('<td></td>').text(row.session_name));
                        $tr.append($('<td></td>').text(row.activity_name));
                        $tbody.append($tr);
                    });
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    $tbody.empty();
                    $tbody.append('<tr class="empty-row"><td colspan="4">Failed to load recent registrations.</td></tr>');
                }
            });
        }

        loadRecentRegistrations();
    });
})(jQuery);
