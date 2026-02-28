(function ($) {
    "use strict";
    $(document).ready(function () {
        var $daysOfWeek = {
            1: 'Monday',
            2: 'Tuesday',
            3: 'Wednesday',
            4: 'Thursday',
            5: 'Friday',
            6: 'Saturday',
            7: 'Sunday'
        };

        var table = $('#clinics-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.clinic_datatable_action;
                    d.security = usctdp_mgmt_admin.clinic_datatable_nonce;
                    var sessionFilterValue = $('#session-filter').val();
                    if (sessionFilterValue) {
                        d.session_id = sessionFilterValue;
                    }
                    var clinicFilterValue = $('#clinic-filter').val();
                    if (clinicFilterValue) {
                        d.product_id = clinicFilterValue;
                    }
                }
            },
            initComplete: function () {
                $('#clinics-table').removeClass('hidden');
            },
            autoWidth: false,
            columnDefs: [
                { width: "30%", targets: 0 }, // Clinic
                { width: "20%", targets: 1 }, // Session
                { width: "10%", targets: 2 }, // Day
                { width: "10%", targets: 3 }, // Time
                { width: "5%", targets: 4 }, // Capacity
                { width: "15%", targets: 6 }  // Actions
            ],
            columns: [
                { data: 'clinic_name' },
                { data: 'session_name' },
                {
                    data: 'clinic_day_of_week',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return $daysOfWeek[data];
                        }
                        return data;
                    }
                },
                {
                    data: 'clinic_start_time',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            const [hours, minutes, seconds] = data.split(':').map(Number);
                            const dateObj = new Date();
                            dateObj.setHours(hours, minutes, seconds);
                            return USCTDP_Admin.displayTime(dateObj);
                        }
                        return data;
                    }
                },
                { data: 'clinic_capacity' },
                {
                    data: 'instructors',
                    defaultContent: '',
                },
                {
                    data: 'clinic_id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var rosterUrl = 'admin.php?page=usctdp-admin-clinic-rosters&activity_id=' + data;
                            var registerUrl = 'admin.php?page=usctdp-admin-register&activity_id=' + data;
                            return `
                            <div class="clinic-actions">
                                <div class="action-item">
                                    <a href="${rosterUrl}" class="button button-small">Roster</a>
                                </div>
                                <div class="action-item">
                                    <a href="${registerUrl}" class="button button-small">Register</a>
                                </div>
                            </div>`
                        }
                        return '';
                    }
                }
            ]
        });

        $('#session-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a session...",
                allowClear: true,
                target: 'session'
            }));

        $('#clinic-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a clinic...",
                allowClear: true,
                target: 'product',
                filter: function() {
                    return {
                        'type': 1 // 1 == Clinic
                    }
                }
            }));

        var $table_controls = $('#clinics-table_wrapper');
        var $first_row = $table_controls.find("div.dt-layout-row").first();
        var filter_row = "<div id='table-filter-row' class='dt-layout-row'></div>"
        $first_row.after(filter_row);
        $('#table-filters').appendTo('#table-filter-row');
        $('#session-filter, #clinic-filter').on('change', function () {
            table.ajax.reload();
        });
    });
})(jQuery);
