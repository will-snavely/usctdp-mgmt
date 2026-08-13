(function ($) {
    "use strict";
    $(document).ready(function () {

        function setTile(id, text) {
            $(id).text(text || '$0.00');
        }

        function renderSessionRow(row) {
            var $tr = $('<tr></tr>');
            if (!row.session_id) {
                $tr.addClass('earnings-other-row');
            }
            var dates = '';
            if (row.start_date && row.end_date) {
                dates = row.start_date + ' – ' + row.end_date;
            }
            $tr.append($('<td></td>').text(row.session_title));
            $tr.append($('<td></td>').text(dates));
            $tr.append($('<td></td>').text(row.gross_revenue_display));
            $tr.append($('<td></td>').text(row.receivable_display));
            $tr.append($('<td></td>').text(row.collected_display));
            return $tr;
        }

        function loadEarnings() {
            var $tbody = $('#earnings-table-body');

            var data = {
                action: usctdp_mgmt_admin.earnings_rollup_action,
                security: usctdp_mgmt_admin.earnings_rollup_nonce,
            };
            var dateFromValue = $('#date-from-filter').val();
            if (dateFromValue) {
                data.date_from = dateFromValue;
            }
            var dateToValue = $('#date-to-filter').val();
            if (dateToValue) {
                data.date_to = dateToValue;
            }

            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: data,
                success: function (response) {
                    if (!response || !response.success) {
                        $tbody.empty();
                        $tbody.append('<tr class="empty-row"><td colspan="5">Failed to load earnings.</td></tr>');
                        return;
                    }

                    var totals = response.data.totals || {};
                    setTile('#tile-gross-revenue', totals.gross_revenue_display);
                    setTile('#tile-paypal-fees', totals.paypal_fees_display);
                    setTile('#tile-net-revenue', totals.net_revenue_display);
                    setTile('#tile-receivable', totals.receivable_display);

                    $tbody.empty();
                    var sessions = response.data.sessions || [];
                    if (sessions.length === 0) {
                        $tbody.append('<tr class="empty-row"><td colspan="5">No earnings in this range.</td></tr>');
                        return;
                    }
                    sessions.forEach(function (row) {
                        $tbody.append(renderSessionRow(row));
                    });
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    $tbody.empty();
                    $tbody.append('<tr class="empty-row"><td colspan="5">Failed to load earnings.</td></tr>');
                }
            });
        }

        // Same min/max cross-linking as the Purchase History date filters
        // (usctdp-mgmt-admin-history.js) - keeps "To" from being set before
        // "From" and vice versa.
        $('#date-from-filter').on('change', function () {
            $('#date-to-filter').attr('min', $(this).val());
        });
        $('#date-to-filter').on('change', function () {
            $('#date-from-filter').attr('max', $(this).val());
        });

        $('.table-filter').on('change', function () {
            loadEarnings();
        });

        loadEarnings();
    });
})(jQuery);
