(function ($) {
    "use strict";
    $(document).ready(function () {
        // DataTables Initialization
        var $daysOfWeek = {
            1: 'Monday',
            2: 'Tuesday',
            3: 'Wednesday',
            4: 'Thursday',
            5: 'Friday',
            6: 'Saturday',
            7: 'Sunday'
        };

        function displayTime(dateObj) {
            const options = {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            };
            return new Intl.DateTimeFormat('en-US', options).format(dateObj);
        }

        var table = $('#usctdp-upcoming-classes-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.class_datatable_action;
                    d.security = usctdp_mgmt_admin.class_datatable_nonce;
                    var sessionFilterValue = $('#session-filter').val();
                    if (sessionFilterValue) {
                        d.session_id = sessionFilterValue;
                    }
                    var clinicFilterValue = $('#clinic-filter').val();
                    if (clinicFilterValue) {
                        d.clinic_id = clinicFilterValue;
                    }
                }
            },
            initComplete: function () {
                $('#usctdp-upcoming-classes-table').removeClass('hidden');
            },
            columns: [
                { data: 'clinic_name' },
                { data: 'session_name' },
                {
                    data: 'class_day_of_week',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return $daysOfWeek[data];
                        }
                        return data;
                    }
                },
                {
                    data: 'class_start_time',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            const [hours, minutes, seconds] = data.split(':').map(Number);
                            const dateObj = new Date();
                            dateObj.setHours(hours, minutes, seconds);
                            return displayTime(dateObj);
                        }
                        return data;
                    }
                },
                { data: 'class_capacity' },
                {
                    data: 'instructors',
                    defaultContent: '',
                },
                {
                    data: 'class_id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var rosterUrl = 'admin.php?page=usctdp-admin-rosters&class_id=' + data;
                            var registerUrl = 'admin.php?page=usctdp-admin-register&class_id=' + data;
                            var cell = '<div class="class-actions">'
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
                        action: usctdp_mgmt_admin.select2_session_search_action,
                        security: usctdp_mgmt_admin.select2_session_search_nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        $('#clinic-filter').select2({
            placeholder: "Search for a clinic...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-clinic',
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
        $('#session-filter, #clinic-filter').on('change', function () {
            table.ajax.reload();
        });
    });

})(jQuery);

