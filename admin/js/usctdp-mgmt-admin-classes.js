(function ($) {
    "use strict";
    $(document).ready(function () {
        // DataTables Initialization
        var table = $('#usctdp-upcoming-classes-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: true,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var sessionFilterValue = $('#session-filter').val();
                    console.log("sessionFilterValue", sessionFilterValue);
                    d.action = usctdp_mgmt_admin.class_action;
                    d.security = usctdp_mgmt_admin.class_nonce;
                    d.session_filter = sessionFilterValue;
                }
            },
            columns: [
                { data: 'name' },
                { data: 'session' },
                { data: 'capacity' },
                {
                    data: 'instructors',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var cell = '<div class="instructors-wrapper">';
                            data.forEach(function (instructor) {
                                cell += '<span class="badge">' + instructor + '</span>';
                            });
                            cell += '</div>';
                            return cell;
                        }
                        return data;
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var editUrl = 'post.php?post=' + data + '&action=edit';
                            var rosterUrl = 'admin.php?page=usctdp-admin-rosters&class_id=' + data;
                            var registerUrl = 'admin.php?page=usctdp-admin-register&class_id=' + data;
                            var cell = '<div class="class-actions">'
                            cell += '<div class="action-item">'
                            cell += '<a href="' + editUrl + '" class="button button-small">Edit Details</a> ';
                            cell += '</div>';
                            cell += '<div class="action-item">'
                            cell += '<a href="' + rosterUrl + '" class="button button-small">Roster</a> ';
                            cell += '</div>';
                            cell += '<div class="action-item">'
                            cell += '<a href="' + registerUrl + '" class="button button-small">Register</a> ';
                            cell += '</div>';
                            cell += '</div>';
                            return cell;
                        }
                        return '';
                    }
                }
            ]
        });

        $('#session-filter').select2({
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

        var $table_controls = $('#usctdp-upcoming-classes-table_wrapper');
        var $first_row = $table_controls.find("div.dt-layout-row").first();
        var filter_row = "<div id='table-filter-row' class='dt-layout-row'></div>"
        $first_row.after(filter_row);

        $('#session-filter-section').appendTo('#table-filter-row')
        $('#session-filter').on('change', function () {
            table.ajax.reload();
        });
    });

})(jQuery);

