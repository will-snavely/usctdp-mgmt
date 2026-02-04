(function ($) {
    "use strict";
    $(document).ready(function () {
        var selectedFamilyId = null;
        var selectedFamilyName = null;

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
                        var $button = $('<button class="button" data-family-name="' + row.family_name + '" data-family-id="' + data + '">Select</button>');
                        return $button.get(0);
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
            columns: [
                {
                    data: 'student_name',
                    defaultContent: '',
                },
                {
                    data: 'activity_name',
                    defaultContent: '',
                },
                {
                    data: 'session_name',
                    defaultContent: '',
                },
                {
                    data: 'balance',
                    defaultContent: '',
                }
            ]
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
