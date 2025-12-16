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
                    d.action = usctdp_mgmt_admin.datatable_search_action;
                    d.security = usctdp_mgmt_admin.datatable_search_nonce;
                    d.post_type = 'usctdp-class';
                    var sessionFilterValue = $('#session-filter').val();
                    if (sessionFilterValue) {
                        d['filter[session][value]'] = sessionFilterValue;
                        d['filter[session][compare]'] = '=';
                        d['filter[session][type]'] = 'NUMERIC';
                    }
                    var courseFilterValue = $('#course-filter').val();
                    if (courseFilterValue) {
                        d['filter[course][value]'] = courseFilterValue;
                        d['filter[course][compare]'] = '=';
                        d['filter[course][type]'] = 'NUMERIC';
                    }
                }
            },
            columns: [
                {
                    data: 'course',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return data.title;
                        }
                        return data;
                    }

                },
                {
                    data: 'session',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return data.title;
                        }
                        return data;
                    }
                },
                { data: 'capacity' },
                {
                    data: 'instructors',
                    defaultContent: '',
                    render: function (data, type, row) {
                        /*
                        if (type === 'display') {
                            var cell = '<div class="instructors-wrapper">';
                            data.forEach(function (instructor) {
                                cell += '<span class="badge">' + instructor + '</span>';
                            });
                            cell += '</div>';
                            return cell;
                        }*/
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

        $('#course-filter').select2({
            placeholder: "Search for a course...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-course',
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

        var $table_controls = $('#usctdp-upcoming-classes-table_wrapper');
        var $first_row = $table_controls.find("div.dt-layout-row").first();
        var filter_row = "<div id='table-filter-row' class='dt-layout-row'></div>"
        $first_row.after(filter_row);

        $('#table-filters').appendTo('#table-filter-row');
        $('#session-filter, #course-filter').on('change', function () {
            table.ajax.reload();
        });
    });

})(jQuery);

