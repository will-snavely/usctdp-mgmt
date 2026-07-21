(function ($) {
    "use strict";

    $(document).ready(function () {
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
            serverSide: true,
            ordering: false,
            searching: true,
            paging: true,
            info: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.session_rosters_datatable_action;
                    d.security = usctdp_mgmt_admin.session_rosters_datatable_nonce;
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
                            return $cell.get(0);
                        }
                        return '';
                    },
                    defaultContent: '',
                }
            ],
            autoWidth: false,
            columnDefs: [
                { width: "85%", targets: 0 },
                { width: "15%", targets: 1 },
            ],
            "initComplete": function () {
                $('#session-rosters-table').removeClass('hidden');
            }
        });

        $('#session-rosters-table').on('click', 'button.view-session-roster', function () {
            var $btn = $(this);
            var $tr = $btn.closest('tr');
            var rowData = sessionsRosterTable.row($tr).data();
            var sessionId = rowData.id;
            $btn.prop('disabled', true);
            $btn.html('<span class="spinner is-active"></span> Generating...');
            USCTDP_Admin.ajax_generateSessionRoster(sessionId)
                .then((response) => {
                    window.open(response.doc_url, '_blank');
                })
                .catch((error) => {
                    window.Swal.fire({
                        title: "Error",
                        text: "Failed to generate roster. Inform a developer.",
                        icon: "error"
                    });
                })
                .finally(() => {
                    $btn.prop('disabled', false);
                    $btn.html("View Roster");
                });
        });
    });
})(jQuery);