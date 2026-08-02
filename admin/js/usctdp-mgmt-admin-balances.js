(function ($) {
    "use strict";
    $(document).ready(function () {
        var selectedFamilyId = null;
        var selectedFamilyName = null;
        var paymentHistoryModal = new USCTDP_Admin.PaymentHistoryModal('payment-history-modal-container');

        $('#balances-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.datatable_balances_action;
                    d.security = usctdp_mgmt_admin.datatable_balances_nonce;
                }
            },
            autoWidth: false,
            columnDefs: [
                { width: "30%", targets: 0 },
                { width: "20%", targets: 1 },
                { width: "50%", targets: 2 },
            ],
            columns: [
                {
                    data: 'family_name',
                    defaultContent: '',
                },
                {
                    data: 'total_balance',
                    defaultContent: '',
                },
                {
                    data: 'family_id',
                    defaultContent: '',
                    render: function (data, type, row) {
                        var familyId = data;
                        var historyUrl = 'admin.php?page=usctdp-admin-history&family_id=' + familyId;
                        var cell = '<div class="family-actions">'
                        cell += '<div class="action-item">'
                        cell += '<button class="button button-small" data-family-name="' + row.family_name + '" data-family-id="' + data + '">Select</button>';
                        cell += '</div>';
                        cell += '<div class="action-item">'
                        cell += '<a href="' + historyUrl + '" class="button button-small">History</a> ';
                        cell += '</div>';
                        cell += '</div>';
                        return cell;
                    }
                }
            ],
            "initComplete": function () {
                $('#balances-table').removeClass('hidden');
            }
        });

        $('#balances-table-detail').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.datatable_balances_detail_action;
                    d.security = usctdp_mgmt_admin.datatable_balances_detail_nonce;
                    d.family_id = selectedFamilyId ? selectedFamilyId : '';
                }
            },
            autoWidth: false,
            columnDefs: [
                { width: "20%", targets: 0 },
                { width: "45%", targets: 1 },
                { width: "20%", targets: 2 },
                { width: "15%", targets: 3 },
            ],
            columns: [
                {
                    data: 'student_name',
                    defaultContent: '',
                },
                {
                    data: 'item',
                    defaultContent: '',
                },
                {
                    data: 'balance',
                    defaultContent: '',
                },
                {
                    data: 'purchase_id',
                    defaultContent: '',
                    render: function (data, type, row) {
                        return '<button type="button" class="button button-small payment-history-btn" '
                            + 'data-purchase-id="' + row.purchase_id + '" '
                            + 'data-account="' + row.purchase_type + '_fees" '
                            + 'data-family-id="' + row.family_id + '">History</button>';
                    }
                }
            ]
        });

        $('#balances-table-detail').on('click', '.payment-history-btn', function () {
            var $btn = $(this);
            paymentHistoryModal.show($btn.data('purchase-id'), $btn.data('account'), $btn.data('family-id'));
        });

        $('#balances-table').on('click', 'button', function () {
            var $row = $(this).closest('tr');
            $('#balances-table tbody tr').removeClass('selected-account');
            $row.addClass('selected-account');
            selectedFamilyId = $(this).data('family-id');
            selectedFamilyName = $(this).data('family-name');
            $('#balances-table-detail').DataTable().ajax.reload();
            $('#balances-table-detail-container').removeClass('hidden');
            $('#detail-title').text(selectedFamilyName + ' Balances');
        });

        $('#balances-table').on('draw.dt', function () {
            if (selectedFamilyId) {
                var $button = $('#balances-table button[data-family-id="' + selectedFamilyId + '"]');
                if ($button.length > 0) {
                    var $row = $button.closest('tr');
                    $('#balances-table tbody tr').removeClass('selected-account');
                    $row.addClass('selected-account');
                }
            }
        });
    });
})(jQuery);
