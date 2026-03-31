(function ($) {
    "use strict";

    $(document).ready(function () {
        var preloadedData = {};
        var newPurchases = null;
        const paymentHistoryModal = document.querySelector('#payment-history-modal');
        const postPaymentModal = document.querySelector('#post-payment-modal');
        const postRefundModal = document.querySelector('#post-refund-modal');
        const paymentSettings = {
            checkoutButton: false,
            allowPayLater: false,
            paymentMode: "update",
            redirectOnComplete: false,
        };

        const paymentTableId = "registration-payment-table";
        const paymentTable =
            new USCTDP_Admin.RegistrationPaymentTable(paymentTableId, paymentSettings);

        function refreshFamilyBalance(family_id, student_id) {
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.get_family_balance_action,
                    security: usctdp_mgmt_admin.get_family_balance_nonce,
                    family_id: family_id,
                    student_id: student_id
                },
                success: function (response) {
                    $('#family-total-balance').text(USCTDP_Admin.formatUsd(response.data.balance));
                    if (response.data.balance >= 0) {
                        $('#family-total-balance').addClass('balance-red');
                        $('#family-total-balance').removeClass('balance-green');
                    } else {
                        $('#family-total-balance').addClass('balance-green');
                        $('#family-total-balance').removeClass('balance-red');
                    }
                }
            });
        }

        function initSessionSelector($selectElem) {
            $selectElem.select2(
                USCTDP_Admin.select2Options({
                    placeholder: "Search for a session...",
                    allowClear: true,
                    target: 'session'
                })
            );
        }

        function initActivitySelector($selectElem, sessionSelectId) {
            $selectElem.select2(
                USCTDP_Admin.select2Options({
                    placeholder: "Search for an activity...",
                    allowClear: true,
                    target: 'activity',
                    filter: function () {
                        return {
                            session_id: $('#' + sessionSelectId).val()
                        }
                    }
                })
            );
        }

        function initPaymentActionSelect($selectElem) {
            $selectElem.select2({
                placeholder: "Select a payment action...",
                allowClear: true,
            });
        }

        async function saveRegistrationFields(id, fields) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.update_registration_action,
                    security: usctdp_mgmt_admin.update_registration_nonce,
                    registration_id: id,
                    ...fields
                }
            });
            return response;
        }

        async function savePurchaseFields(id, fields) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.update_purchase_action,
                    security: usctdp_mgmt_admin.update_purchase_nonce,
                    purchase_id: id,
                    ...fields
                }
            });
            return response;
        }

        function renderFinancialSection(idx, debit, credit) {
            const total = debit - credit;
            const debitDisplay = USCTDP_Admin.formatUsd(debit);
            const creditDisplay = USCTDP_Admin.formatUsd(credit);
            const totalDisplay = USCTDP_Admin.formatUsd(total);
            const totalClass = total > 0 ? "balance-red" : "balance-green";
            return `
                <div class="flex-col gap-10 align-end">
                    <div class="payment-info">
                        <div class="debit-wrap activity-field align-center">
                            <label>Debit</label>
                            <span id="debit-input-${idx}" class="debit-amt amt-badge balance-red">
                                ${debitDisplay}
                            </span>
                        </div>
                        <div class="credit-wrap activity-field align-center">
                            <label>Credit</label>
                            <span id="credit-input-${idx}" class="credit-amt amt-badge balance-green">
                                ${creditDisplay}
                            </span>
                        </div>
                        <div class="balance-wrap activity-field align-center">
                            <label>Balance</label>
                            <span id="balance-amt-${idx}" class="balance-amt amt-badge ${totalClass}">
                                ${totalDisplay}
                            </span>
                        </div>
                        <div class="payment-history-button">
                            <button id="payment-history-${idx}" class="button payment-history">
                                Payment History
                            </button>
                        </div>
                    </div>
                    <div class="flex-row gap-10">
                        <select id="payment-action-${idx}" class="payment-action-select">
                            <option value=""></option>
                            <option value="post-payment">Post Payment</option>
                            <option value="post-refund">Post Refund</option>
                            <option value="issue-credit">Issue House Credit</option>
                        </select>
                        <button id="ledger-action-${idx}" class="button ledger-action" disabled>
                            Go
                        </button>
                    </div>
                </div>`;
        }

        function renderNotesSection(notes, idx) {
            return `
                <div class="notes-wrap activity-field">
                    <div class="flex-row gap-10 align-end">
                        <label>Notes</label>
                        <button id="save-notes-${idx}" class="button button-small save-notes" disabled>Save</button>
                    </div>
                    <textarea rows=3 id="notes-input-${idx}" class="notes-input">${notes}</textarea>
                </div>`;
        }

        function renderStudentInfo(studentFirst, studentLast, studentAge) {
            return `
                <div class="student-name-wrap">
                    <span class="student-name">${studentFirst} ${studentLast}</span>
                </div>
                <div class="student-age-wrap">
                    <span class="student-age">Age: ${studentAge}</span>
                </div>`;
        }

        function renderRegistrationRow(data, idx) {
            const {
                studentFirst, studentLast, studentAge,
                sessionName, sessionId,
                activityName, activityId,
                registrationId,
                level, debit, credit, notes
            } = data;
            const sessionSelectId = `session-selector-${idx}`;
            const activitySelectId = `activity-selector-${idx}`;

            var newPurchaseBadge = '';
            if (newPurchases) {
                const regId = parseInt(registrationId);
                newPurchaseBadge = newPurchases.has(regId) ? `
                    <div class="new-purchase-badge">
                        <span class="new-purchase">New!</span>
                    </div>` : '';
            }
            return `
              <div class="purchase-card edit-disabled">
                <div class="flex-row gap-10 align-baseline">
                    <div class="checkbox-wrap">
                        <input type="checkbox" class="row-check" value="${registrationId}">
                    </div>
                    ${renderStudentInfo(studentFirst, studentLast, studentAge)}
                    <div class="purchase-actions flex-row gap-10 align-center">
                        <span class="registration-badge">Registration</span>
                        ${newPurchaseBadge}
                        <button id="edit-activity-${idx}" class="button button-small edit-activity" data-state="edit">
                            Modify
                        </button>
                    </div>
                </div>

                <div class="flex-row gap-10 w-100">
                    <div class="session-selector-wrap activity-field">
                        <label>Session</label>
                        <div id="session-selector-wrap-${idx}">
                            <select id="${sessionSelectId}" class="session-select" data-orig-value="${sessionId}"
                                data-orig-text="${sessionName}" data-activity-selector-id="${activitySelectId}" disabled>
                                <option value="${sessionId}" selected>${sessionName}</option>
                            </select>
                        </div>
                    </div>
                    <div class="activity-selector-wrap activity-field">
                        <label>Activity</label>
                        <div id="activity-selector-wrap-${idx}">
                            <select id="${activitySelectId}" class="activity-select" data-orig-value="${activityId}"
                                data-orig-text="${activityName}" data-session-selector-id="${sessionSelectId}" disabled>
                                <option value="${activityId}" selected>${activityName}</option>
                            </select>
                        </div>
                    </div>
                    <div class="level-wrap activity-field">
                        <label>Level</label>
                        <input id="level-input-${idx}" class="level-input" value="${level}" readonly>
                    </div>
                </div>
                <div class="flex-row gap-10 w-100">
                    ${renderFinancialSection(idx, debit, credit)}
                    ${renderNotesSection(notes, idx)}
                </div>
            </div>`;
        }

        function renderMerchandiseRow(data, idx) {
            const {
                studentFirst, studentLast, studentAge,
                debit, credit, notes,
                purchaseId, productName
            } = data;
            const newBadge = '';

            return `
              <div class="purchase-card edit-disabled">
                <div class="flex-row gap-10 align-baseline">
                    <div class="checkbox-wrap">
                        <input type="checkbox" class="row-check" value="${purchaseId}">
                    </div>
                    <div class="student-name-wrap">
                        <span class="student-name">${studentFirst} ${studentLast}</span>
                    </div>
                    <div class="student-age-wrap">
                        <span class="student-age">Age: ${studentAge}</span>
                    </div>
                    <div class="purchase-actions flex-row gap-10 align-center">
                        <span class="merchandise-badge">Merchandise</span>
                        <div class="product-wrap">
                            <span class="product-name">${productName}</span>
                        </div>
                        <div class="new-purchase-badge">    
                            ${newBadge}
                        </div>
                    </div>
                </div>


                <div class="flex-row gap-10 w-100">
                    ${renderFinancialSection(idx, debit, credit)}
                    ${renderNotesSection(notes, idx)}
                </div>
            </div>`;
        }


        var paymentHistoryTable = $('#payment-history-table').DataTable({
            processing: true,
            serverSide: false,
            ordering: false,
            paging: false,
            searching: false,
            info: false,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.ledger_events_datatable_action;
                    d.security = usctdp_mgmt_admin.ledger_events_datatable_nonce;
                    d.purchase_id = $('#payment-history-modal').data("purchaseId");
                    d.account = $('#payment-history-modal').data("account");
                    d.length = -1;
                },
                dataSrc: function (json) {
                    var runningBalance = 0;
                    for (var i = 0; i < json.data.length; i++) {
                        var charge = parseFloat(json.data[i].charge_amount) || 0;
                        var payment = parseFloat(json.data[i].payment_amount) || 0;
                        runningBalance += (charge - payment);
                        json.data[i].calculated_balance = runningBalance;
                    }
                    return json.data;
                },
                beforeSend: function (jqXHR, settings) {
                    var pId = $('#payment-history-modal').data("purchaseId");
                    if (!pId) {
                        return false; // This cancels the Ajax request
                    }
                },
            },
            columns: [
                { data: 'event_date' },
                { data: 'event_description' },
                {
                    data: 'charge_amount',
                    render: function (data, type, row, meta) {
                        return USCTDP_Admin.formatUsd(data);
                    },
                    className: 'num-col text-red'
                },
                {
                    data: 'payment_amount',
                    render: function (data, type, row, meta) {
                        return USCTDP_Admin.formatUsd(data);
                    },
                    className: 'num-col text-green'
                },
                {
                    data: 'calculated_balance',
                    className: 'num-col balance-col',
                    render: function (data) {
                        return USCTDP_Admin.formatUsd(data);
                    }
                }
            ],
            // Update the Summary Bar after the data loads
            drawCallback: function () {
                var api = this.api();
                var totalBalance = 0;

                api.rows().every(function () {
                    var d = this.data();
                    totalBalance += (parseFloat(d.charge_amount) - parseFloat(d.payment_amount));
                });

                $('#ledger-total-balance').text(USCTDP_Admin.formatUsd(totalBalance));

                if (totalBalance <= 0) {
                    $('#ledger-status-text').text('PAID').css('color', '#00a32a');
                } else {
                    $('#ledger-status-text').text('BALANCE DUE').css('color', '#d63638');
                }
            }
        });

        var historyTable = $('#history-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            paging: true,
            searching: false,
            info: true,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var familyId = $('#family-selector').val();
                    d.action = usctdp_mgmt_admin.purchase_history_datatable_action;
                    d.security = usctdp_mgmt_admin.purchase_history_datatable_nonce;
                    d.family_id = familyId;

                    if (preloadedData.student) {
                        d.student_id = preloadedData.student.student_id;
                    } else {
                        var studentFilterValue = $('#student-filter').val();
                        if (studentFilterValue) {
                            d.student_id = studentFilterValue;
                        }
                    }

                    var sessionFilterValue = $('#session-filter').val();
                    if (sessionFilterValue) {
                        d.session_id = sessionFilterValue;
                    }

                    var typeFilterValue = $('#type-filter').val();
                    if (typeFilterValue) {
                        d.type = typeFilterValue;
                    }

                    if ($('#owes-filter').is(':checked')) {
                        d.owes = 1;
                    } else {
                        d.owes = 0;
                    }
                }
            },
            columns: [
                {
                    data: 'id',
                    render: function (data, type, row, meta) {
                        if (type === 'display') {
                            try {
                                if (row.purchase_type === 'registration') {
                                    const activityData = {
                                        studentFirst: row.student_first,
                                        studentLast: row.student_last,
                                        studentAge: row.student_age,
                                        sessionName: row.session_name,
                                        sessionId: row.session_id,
                                        activityName: row.activity_name,
                                        activityId: row.activity_id,
                                        registrationId: row.registration_id,
                                        level: row.registration_student_level,
                                        debit: row.total_debit,
                                        credit: row.total_credit,
                                        notes: row.purchase_notes
                                    };
                                    return renderRegistrationRow(activityData, meta.row);
                                } else if (row.purchase_type == 'merchandise') {
                                    const merchandiseData = {
                                        studentFirst: row.student_first,
                                        studentLast: row.student_last,
                                        studentAge: row.student_age,
                                        productName: row.product_title,
                                        productId: row.product_id,
                                        debit: row.total_debit,
                                        credit: row.total_credit,
                                        notes: row.purchase_notes
                                    };
                                    return renderMerchandiseRow(merchandiseData, meta.row);
                                }
                            } catch (error) {
                                console.error(error);
                                return '';
                            }
                        }
                        return '';
                    }
                }
            ],
            initComplete: function () {
                if ($("#table-filter-row").length === 0) {
                    var $table_controls = $('#history-table_wrapper');
                    var $first_row = $table_controls.find("div.dt-layout-row").first();
                    var filter_row = "<div id='table-filter-row' class='dt-layout-row'></div>";
                    $first_row.after(filter_row);
                    $('#table-filters').appendTo('#table-filter-row');
                    $('#session-filter, #student-filter').on('change', function () {
                        historyTable.ajax.reload();
                    });
                    $("#type-filter").on('change', function () {
                        historyTable.ajax.reload();
                    });
                    $("#owes-filter").on('change', function () {
                        historyTable.ajax.reload();
                    });
                }
            },

            preDrawCallback: function (settings) {
                var api = this.api();
                $(api.table().body()).find('select').each(function () {
                    if ($(this).hasClass("select2-hidden-accessible")) {
                        $(this).select2('destroy');
                    }
                });
            },

            drawCallback: function (settings) {
                var api = this.api();
                $('#cb-select-all').prop('checked', false);
                $(api.table().body()).find('.session-select').each(function () {
                    initSessionSelector($(this));
                });
                $(api.table().body()).find('.activity-select').each(function () {
                    initActivitySelector($(this), $(this).data('session-selector-id'));
                });
                $(api.table().body()).find('.payment-action-select').each(function () {
                    initPaymentActionSelect($(this));
                });

                updateBulkUI();
            }
        });

        function updateBulkUI() {
            const count = $('.row-check:checked').length;
            const $btn = $('#apply-bulk-btn');
            const $countText = $('#selected-count');
            const $selector = $('#bulk-action-selector');
            if (count > 0) {
                $countText.text(count);
                $('#selection-status').removeClass("hidden");
                if ($selector.val()) {
                    $btn.prop('disabled', false);
                } else {
                    $btn.prop('disabled', true);
                }
            } else {
                $btn.prop('disabled', true);
                $('#selection-status').addClass("hidden");
            }
        }

        function openPostPaymentModal(registrations) {
            paymentTable.clear();
            let count = 0;
            for (const reg of registrations) {
                if (parseFloat(reg.total_credit) < parseFloat(reg.total_debit)) {
                    paymentTable.addExistingRegistration(reg);
                    count++;
                }
            }
            if (count > 0) {
                postPaymentModal.showModal();
            } else {
                alert("The selected registration(s) are already paid in full!");
            }
        }

        function openPaymentHistoryModal(purchaseId, account) {
            $('#payment-history-modal').data("purchaseId", purchaseId);
            $('#payment-history-modal').data("account", account);
            paymentHistoryTable.ajax.reload();
            paymentHistoryModal.showModal();
        }

        function openPostRefundModal(row) {
            if (row.total_credit > 0) {
                $('#refund-form').data("registrationId", row.registration_id);
                postRefundModal.showModal();
            } else {
                alert("The selected registration has no credit to refund!");
            }
        }

        $('#refund-form').on('submit', function (e) {
            const form = $('#refund-form')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            e.preventDefault();
            const formData = new FormData(form);
        });

        $('#close-refund-modal').on('click', () => {
            postRefundModal.close();
        });

        $('#bulk-action-selector').on('change', function () {
            updateBulkUI();
        });

        // Select All Click
        $('#cb-select-all').on('click', function () {
            var isChecked = $(this).prop('checked');
            $('#history-table tbody .row-check').prop('checked', isChecked);
            $('#history-table tbody tr .registration-card').toggleClass('selected', isChecked);
            updateBulkUI();
        });

        // Individual Row Click
        $('#history-table tbody').on('change', '.row-check', function () {
            $(this).closest('.registration-card').toggleClass('selected', this.checked);
            if (!this.checked) {
                $('#cb-select-all').prop('checked', false);
            }
            var totalOnPage = $('#history-table tbody .row-check').length;
            var totalChecked = $('#history-table tbody .row-check:checked').length;
            if (totalOnPage === totalChecked) {
                $('#cb-select-all').prop('checked', true);
            }
            updateBulkUI();
        });


        $('#history-table tbody').on('input', '.notes-input', function () {
            const $row = $(this).closest('tr');
            $row.find('.notes-wrap').addClass('is-dirty');
            $row.find('.save-notes').prop('disabled', false);
        });

        $('#history-table tbody').on('click', '.save-notes', function () {
            const $row = $(this).closest('tr');
            $row.find('.save-notes').prop('disabled', true);
            var rowData = historyTable.row($row).data();
            var update = {
                notes: $row.find('.notes-input').first().val()
            }
            savePurchaseFields(rowData.purchase_id, update)
                .then(() => {
                    $row.find('.notes-wrap').removeClass('is-dirty');
                })
                .catch((error) => {
                    alert("Saving notes failed! " + error);
                });
        });

        $('#bulk-action-selector').select2({
            placeholder: "Select a bulk action...",
            allowClear: true,
            minimumResultsForSearch: Infinity
        });

        $('#apply-bulk-btn').on('click', function () {
            const action = $('#bulk-action-selector').val();
            const registrations = $('.row-check:checked').map(function () {
                const $row = $(this).closest("tr");
                return historyTable.row($row).data();
            }).get();

            if (action === 'post-payments') {
                openPostPaymentModal(registrations);
            }
        });

        $('#type-filter').select2({
            placeholder: "Filter by type...",
            allowClear: true
        });

        $('#student-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Filter by student...",
                allowClear: true,
                target: 'student',
                filter: function () {
                    return {
                        family_id: $('#family-selector').val()
                    }
                }
            })
        );

        $('#session-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Filter by session...",
                allowClear: true,
                target: 'session'
            })
        );

        $('#close-payment-modal').on('click', () => {
            postPaymentModal.close();
        });

        $('#close-payment-history-modal').on('click', () => {
            paymentHistoryModal.close();
        });

        $('#history-table tbody').on('change', '.payment-action-select', function () {
            const $row = $(this).closest('tr');
            const $select = $(this);
            const action = $select.val();
            if (action) {
                $row.find('.ledger-action').prop('disabled', false);
            } else {
                $row.find('.ledger-action').prop('disabled', true);
            }
        });

        $('#history-table tbody').on('click', '.ledger-action', function () {
            const $row = $(this).closest('tr');
            const rowData = historyTable.row($row).data();
            const $select = $row.find('.payment-action-select');
            const action = $select.val();
            if (action === 'post-payment') {
                openPostPaymentModal([rowData]);
            } else if (action === 'post-refund') {
                openPostRefundModal(rowData);
            }
        });

        $('#history-table tbody').on('change', '.session-select', function () {
            const activitySelectId = $(this).data('activity-selector-id');
            $('#' + activitySelectId).val(null).trigger("change");
        });

        $('#history-table tbody').on('click', 'button.edit-activity', function (e) {
            const $row = $(this).closest('tr');
            const $button = $(this);
            const state = $button.data("state");
            var rowData = historyTable.row($row).data();
            const familyId = $("#family-selector").val();
            var studentId = null;
            if (preloadedData['student-selector']) {
                studentId = preloadedData['student-selector']["id"];
            }

            if (state == "edit") {
                $button.text("Save");
                $button.data("state", "save");
                $button.addClass('save-btn');
                $row.find('.purchase-card').addClass('editing');
                $row.find(".ledger-action").prop('disabled', true);
                $row.find('select').prop('disabled', false);
                $row.find('input').prop('readonly', false);
                $row.find('textarea').prop('readonly', false);
            } else {
                $button.text("Edit");
                $button.data("state", "edit");
                $button.removeClass('save-btn');
                $row.find('.purchase-card').removeClass('editing');
                $row.find('select').prop('disabled', true);
                $row.find('input').prop('readonly', true);
                $row.find('textarea').prop('readonly', true);
                $button.prop('disabled', true);

                var update = {
                    activity_id: $row.find('.activity-select').first().val(),
                    student_level: $row.find('.level-input').first().val(),
                    debit: $row.find('.debit-input').first().val(),
                    credit: $row.find('.credit-input').first().val(),
                    notes: $row.find('.notes-input').first().val()
                }

                saveRegistrationFields(rowData.registration_id, update)
                    .catch((error) => {
                        alert("Update failed! " + error);
                    })
                    .finally(() => {
                        $button.text("Processing..");
                        $button.data("state", "edit");
                        refreshFamilyBalance(familyId, studentId);
                        historyTable.ajax.reload();
                    });
            }
        });

        $(`#${paymentTableId}`).on('payment:complete', function () {
            postPaymentModal.close();
            historyTable.ajax.reload();
            var studentId = null;
            if (preloadedData['student-selector']) {
                studentId = preloadedData['student-selector']["id"];
            }
            refreshFamilyBalance($('#family-selector').val(), studentId);
        });

        $('#history-table tbody').on('click', 'button.payment-history', function (e) {
            const $row = $(this).closest('tr');
            var rowData = historyTable.row($row).data();
            const purchaseId = rowData.purchase_id;
            const account = rowData.purchase_type + "_fees";
            openPaymentHistoryModal(purchaseId, account);
        });

        function load_registration_history(title, family_id, student_id) {
            historyTable.ajax.reload();
            refreshFamilyBalance(family_id, student_id);
            $('#family-name').text(title);
            $('#history-container').removeClass('hidden');
        }

        const selectorConfig = {
            'family-selector': {
                name: 'family_id',
                label: 'Family',
                target: 'family',
                next: null,
                isRoot: true
            },
        };

        const selectHandler = new USCTDP_Admin.CascasdingSelect('context-selectors', selectorConfig);
        $('#context-selectors').on('cascade:change', function (e) {
            const { selectorId, value, state } = e.detail;
            if (value) {
                $('#session-filter').val(null).trigger('change');
                var studentId = null;
                var title = $('#family-selector').find('option:selected').text();
                if (preloadedData['student-selector']) {
                    studentId = preloadedData['student-selector']["id"];
                    title = preloadedData['student-selector']["text"];
                } else {
                    $('#student-filter').val(null).trigger('change');
                }
                load_registration_history(title, value, studentId);
            } else {
                $('#session-filter').val(null).trigger('change');
                $('#student-filter').val(null).trigger('change');
                $('#history-container').addClass("hidden");
            }
        });

        if (usctdp_mgmt_admin.new_purchases) {
            newPurchases = new Set(usctdp_mgmt_admin.new_purchases)
        }

        if (usctdp_mgmt_admin.preload) {
            if (usctdp_mgmt_admin.preload.family_id) {
                const preloadedFamily = Object.values(usctdp_mgmt_admin.preload.family_id)[0]
                preloadedData['family-selector'] = {
                    id: preloadedFamily.id,
                    text: preloadedFamily.title,
                    disable: true
                }
                $('#context-selectors').addClass('hidden');
            }

            if (usctdp_mgmt_admin.preload.student_id) {
                const preloadedStudent = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                preloadedData['family-selector'] = {
                    id: preloadedStudent.family_id,
                    text: preloadedStudent.family_name,
                    disable: true
                }
                preloadedData['student-selector'] = {
                    id: preloadedStudent.student_id,
                    text: preloadedStudent.student_name
                }
                $('#student-filter').prop('disabled', true);
                $('#student-filter-section').addClass('hidden');
                $('#context-selectors').addClass('hidden');
            }
            selectHandler.applyData(preloadedData);
        }
    });
})(jQuery);
