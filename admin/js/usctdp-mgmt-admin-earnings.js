(function ($) {
    "use strict";
    $(document).ready(function () {

        function setTile(id, text) {
            $(id).text(text || '$0.00');
        }

        function currentDateFilters(d) {
            var dateFromValue = $('#date-from-filter').val();
            if (dateFromValue) {
                d.date_from = dateFromValue;
            }
            var dateToValue = $('#date-to-filter').val();
            if (dateToValue) {
                d.date_to = dateToValue;
            }
        }

        function renderUnassigned(unassigned) {
            var $row = $('#earnings-unassigned-row');
            if (!unassigned || (parseFloat(unassigned.gross_revenue) === 0 && parseFloat(unassigned.receivable) === 0)) {
                $row.addClass('hidden');
                return;
            }
            $('#unassigned-gross-revenue').text(unassigned.gross_revenue_display);
            $('#unassigned-receivable').text(unassigned.receivable_display);
            $('#unassigned-collected').text(unassigned.collected_display);
            $row.removeClass('hidden');
        }

        var earningsTable = $('#earnings-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: true,
            paging: true,
            language: {
                search: '',
                searchPlaceholder: 'Search sessions…',
            },

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.earnings_rollup_action;
                    d.security = usctdp_mgmt_admin.earnings_rollup_nonce;
                    currentDateFilters(d);
                },
                dataSrc: function (json) {
                    var totals = json.totals || {};
                    setTile('#tile-gross-revenue', totals.gross_revenue_display);
                    setTile('#tile-paypal-fees', totals.paypal_fees_display);
                    setTile('#tile-net-revenue', totals.net_revenue_display);
                    setTile('#tile-receivable', totals.receivable_display);
                    renderUnassigned(json.unassigned);
                    return json.data || [];
                }
            },
            autoWidth: false,
            columnDefs: [
                { width: "36px", targets: 0 },
                { width: "100px", targets: 2 },
                { width: "110px", targets: [3, 4, 5] },
            ],
            columns: [
                {
                    data: null,
                    defaultContent: '<span class="details-toggle">▸</span>',
                    className: 'details-control',
                    orderable: false,
                },
                {
                    data: 'session_title',
                    defaultContent: '',
                    className: 'details-control',
                },
                {
                    // Just the start date on narrow screens/tables - the full
                    // range was crowding out the amount columns, and the
                    // start date alone is enough to tell sessions apart.
                    data: 'start_date',
                    defaultContent: '',
                },
                {
                    data: 'gross_revenue_display',
                    defaultContent: '',
                },
                {
                    data: 'receivable_display',
                    defaultContent: '',
                },
                {
                    data: 'collected_display',
                    defaultContent: '',
                }
            ],
            "initComplete": function () {
                $('#earnings-table').removeClass('hidden');
            }
        });

        function sessionDetailTable(products) {
            if (!products || products.length === 0) {
                return '<div class="session-detail"><p class="empty-note">No product-level earnings in this range.</p></div>';
            }
            var rows = products.map(function (p) {
                return '<tr>'
                    + '<td>' + $('<div>').text(p.product_title).html() + '</td>'
                    + '<td>' + p.gross_revenue_display + '</td>'
                    + '<td>' + p.receivable_display + '</td>'
                    + '<td>' + p.collected_display + '</td>'
                    + '</tr>';
            }).join('');
            return '<div class="session-detail">'
                + '<div class="session-detail-scroll">'
                + '<table class="usctdp-mini-table session-detail-table">'
                + '<thead><tr><th>Product</th><th>Gross</th><th>Receivable</th><th>Collected</th></tr></thead>'
                + '<tbody>' + rows + '</tbody>'
                + '</table></div></div>';
        }

        $('#earnings-table tbody').on('click', 'td.details-control', function () {
            var $tr = $(this).closest('tr');
            var row = earningsTable.row($tr);

            if (row.child.isShown()) {
                row.child.hide();
                $tr.removeClass('shown');
                $tr.find('.details-toggle').text('▸');
                return;
            }

            var sessionId = row.data().session_id;
            row.child('<div class="session-detail-loading">Loading…</div>').show();
            $tr.addClass('shown');
            $tr.find('.details-toggle').text('▾');

            var data = {
                action: usctdp_mgmt_admin.earnings_session_detail_action,
                security: usctdp_mgmt_admin.earnings_session_detail_nonce,
                session_id: sessionId,
            };
            currentDateFilters(data);

            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: data,
                success: function (response) {
                    if (!row.child.isShown()) {
                        return;
                    }
                    if (!response || !response.success) {
                        row.child('<div class="session-detail"><p class="empty-note">Failed to load product earnings.</p></div>');
                        return;
                    }
                    row.child(sessionDetailTable(response.data.products));
                },
                error: function () {
                    if (row.child.isShown()) {
                        row.child('<div class="session-detail"><p class="empty-note">Failed to load product earnings.</p></div>');
                    }
                }
            });
        });

        $('#date-from-filter').on('change', function () {
            $('#date-to-filter').attr('min', $(this).val());
        });
        $('#date-to-filter').on('change', function () {
            $('#date-from-filter').attr('max', $(this).val());
        });

        $('.table-filter').on('change', function () {
            earningsTable.ajax.reload();
        });
    });
})(jQuery);
