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
            manageDiscounts: false,
            redirectOnComplete: false,
        };
        const paymentTableId = "registration-payment-table";
        const paymentTable = new USCTDP_Admin.RegistrationPaymentTable(paymentTableId, paymentSettings);

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
                    if (response.data.balance > 0) {
                        $('#family-total-balance').addClass('red-bg');
                        $('#family-total-balance').removeClass('green-bg');
                    } else {
                        $('#family-total-balance').addClass('green-bg');
                        $('#family-total-balance').removeClass('red-bg');
                    }

                    $('#family-total-house-credit').text(USCTDP_Admin.formatUsd(response.data.house_credit));
                    if (response.data.house_credit > 0) {
                        $('#family-total-house-credit').addClass('green-bg');
                        $('#family-total-house-credit').removeClass('red-bg');
                    } else {
                        $('#family-total-house-credit').addClass('red-bg');
                        $('#family-total-house-credit').removeClass('green-bg');
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

        function renderAmountBadge(label, value, classes = []) {
            return `
                <div class="flex-col gap-5 align-center">
                    <label class="upper-heavy">${label}</label>
                    <span class="badge ${classes.join(' ')}">${value}</span>
                </div>
            `;
        }

        function renderFinancialSection(idx, fees, adjustments, payments, refunds, houseCredits) {
            const netFees = fees - adjustments;
            const netFeesDisplay = USCTDP_Admin.formatUsd(netFees);
            const netPayments = payments - (refunds + houseCredits);
            const netPaymentsDisplay = USCTDP_Admin.formatUsd(netPayments);
            const owed = netFees - netPayments;
            const owedDisplay = USCTDP_Admin.formatUsd(owed);
            const refundsDisplay = USCTDP_Admin.formatUsd(refunds);
            const houseCreditsDisplay = USCTDP_Admin.formatUsd(houseCredits);
            return `
                <div class="flex-col gap-10 align-end">
                    <div class="payment-info">
                        ${renderAmountBadge('Net Fees', netFeesDisplay, ['red-bg'])}
                        ${renderAmountBadge('Net Paid', netPaymentsDisplay, ['green-bg'])}
                        ${renderAmountBadge('Owed', owedDisplay, ['red-bg'])}
                        ${renderAmountBadge('Refunds', refundsDisplay, ['blue-bg'])}
                        ${renderAmountBadge('House Cr.', houseCreditsDisplay, ['blue-bg'])}
                    </div>
                    <div class="flex-row gap-10">
                        <div class="payment-history-button">
                            <button id="payment-history-${idx}" class="button payment-history">
                                Payment History
                            </button>
                        </div>
                        <select id="payment-action-${idx}" class="payment-action-select">
                            <option value=""></option>
                            <option value="post-payment">Post Payment</option>
                            <option value="post-refund">Post Refund/Adjustment</option>
                        </select>
                        <button id="ledger-action-${idx}" class="button ledger-action" disabled>
                            Go
                        </button>
                    </div>
                </div>`;
        }

        function renderNotesSection(notes, idx) {
            return `
                <div class="notes-wrap flex-col gap-5">
                    <div class="flex-row gap-10 align-end">
                        <label class="upper-heavy">Notes</label>
                        <button id="save-notes-${idx}" class="button button-small save-notes-btn" disabled>Save</button>
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
                sessionName, sessionId, activityName, activityId,
                purchaseId, registrationId, level, createdDate, notes,
                fees, adjustments, payments, refunds, houseCredits
            } = data;
            const sessionSelectId = `session-selector-${idx}`;
            const activitySelectId = `activity-selector-${idx}`;

            var newPurchaseBadge = '';
            if (newPurchases) {
                newPurchaseBadge = newPurchases.has(parseInt(purchaseId)) ? `
                    <div class="new-purchase-badge">
                        <span class="new-purchase">New!</span>
                    </div>` : '';
            }
            return `
              <div class="purchase-card edit-disabled">
                <div class="flex-row gap-10 align-center">
                    <div class="checkbox-wrap">
                        <input type="checkbox" class="row-check" value="${registrationId}">
                    </div>
                    ${renderStudentInfo(studentFirst, studentLast, studentAge)}
                    <div class="border-left">
                        <div class="purchase-actions flex-row gap-10 align-center">
                            <span class="purchase-badge blue-bg upper-heavy">Registration</span>
                            ${newPurchaseBadge}
                            <button id="edit-activity-${idx}" class="button button-small edit-activity" data-state="edit">
                                Modify
                            </button>
                        </div>  
                    </div>
                    <div class="border-left">
                        <div class="created-date flex-row gap-5 align-center">
                            <label class="upper-heavy">Created At</label>
                            <span id="created-date-${idx}" class="created-date-value">${createdDate}</span>
                        </div>
                    </div>
                </div>

                <div class="registration-fields flex-row gap-10">
                    <div class="session-selector-wrap flex-col gap-5">
                        <label class="upper-heavy">Session</label>
                        <div id="session-selector-wrap-${idx}">
                            <select id="${sessionSelectId}" class="session-select" data-orig-value="${sessionId}"
                                data-orig-text="${sessionName}" data-activity-selector-id="${activitySelectId}" disabled>
                                <option value="${sessionId}" selected>${sessionName}</option>
                            </select>
                        </div>
                    </div>
                    <div class="activity-selector-wrap flex-col gap-5">
                        <label class="upper-heavy">Activity</label>
                        <div id="activity-selector-wrap-${idx}">
                            <select id="${activitySelectId}" class="activity-select" data-orig-value="${activityId}"
                                data-orig-text="${activityName}" data-session-selector-id="${sessionSelectId}" disabled>
                                <option value="${activityId}" selected>${activityName}</option>
                            </select>
                        </div>
                    </div>
                    <div class="level-wrap flex-col gap-5">
                        <label class="upper-heavy">Level</label>
                        <input id="level-input-${idx}" class="level-input" value="${level}" readonly>
                    </div>
                </div>
                <div class="flex-row gap-10 w-100">
                    ${renderFinancialSection(idx, fees, adjustments, payments, refunds, houseCredits)}
                    ${renderNotesSection(notes, idx)}
                </div>
            </div>`;
        }

        function renderMerchandiseRow(data, idx) {
            const {
                studentFirst, studentLast, studentAge, createdDate, notes,
                fees, adjustments, payments, refunds, houseCredits,
                purchaseId, productName
            } = data;
            var newPurchaseBadge = '';
            if (newPurchases) {
                const regId = parseInt(purchaseId);
                newPurchaseBadge = newPurchases.has(regId) ? `
                    <div class="new-purchase-badge">
                        <span class="new-purchase">New!</span>
                    </div>` : '';
            }

            return `
              <div class="purchase-card edit-disabled">
                <div class="flex-row gap-10 align-baseline">
                    <div class="checkbox-wrap">
                        <input type="checkbox" class="row-check" value="${purchaseId}">
                    </div>
                    ${renderStudentInfo(studentFirst, studentLast, studentAge)}
                    <div class="border-left">
                        <div class="purchase-actions flex-row gap-5 align-center">
                            <span class="purchase-badge green-bg upper-heavy">Merchandise</span>
                            <div class="product-wrap">
                                <span class="product-name">${productName}</span>
                            </div>
                            ${newPurchaseBadge}
                        </div>
                    </div>
                    <div class="border-left">
                        <div class="created-date flex-row gap-5 align-center">
                            <label class="upper-heavy">Created At</label>
                            <span id="created-date-${idx}" class="created-date-value">${createdDate}</span>
                        </div>
                    </div>
                </div>

                <div class="flex-row gap-10 w-100">
                    ${renderFinancialSection(idx, fees, adjustments, payments, refunds, houseCredits)}
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
                        var charge = USCTDP_Admin.safeParseFloat(json.data[i].charge_amount);
                        var payment = USCTDP_Admin.safeParseFloat(json.data[i].payment_amount);
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
                { data: 'entry_type' },
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
                    totalBalance += (USCTDP_Admin.safeParseFloat(d.charge_amount) - USCTDP_Admin.safeParseFloat(d.payment_amount));
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
                                        createdDate: new Date(row.purchase_created_at).toLocaleString(),
                                        studentFirst: row.student_first,
                                        studentLast: row.student_last,
                                        studentAge: row.student_age,
                                        sessionName: row.session_name,
                                        sessionId: row.session_id,
                                        activityName: row.activity_name,
                                        activityId: row.activity_id,
                                        registrationId: row.registration_id,
                                        level: row.registration_student_level,
                                        fees: USCTDP_Admin.safeParseFloat(row.total_fees),
                                        adjustments: USCTDP_Admin.safeParseFloat(row.total_adjustments),
                                        payments: USCTDP_Admin.safeParseFloat(row.total_payments),
                                        refunds: USCTDP_Admin.safeParseFloat(row.total_refunds),
                                        houseCredits: USCTDP_Admin.safeParseFloat(row.total_house_credits),
                                        notes: row.purchase_notes
                                    };
                                    return renderRegistrationRow(activityData, meta.row);
                                } else if (row.purchase_type == 'merchandise') {
                                    const merchandiseData = {
                                        createdDate: new Date(row.purchase_created_at).toLocaleString(),
                                        studentFirst: row.student_first,
                                        studentLast: row.student_last,
                                        studentAge: row.student_age,
                                        productName: row.product_name,
                                        productId: row.product_id,
                                        fees: USCTDP_Admin.safeParseFloat(row.total_fees),
                                        adjustments: USCTDP_Admin.safeParseFloat(row.total_adjustments),
                                        payments: USCTDP_Admin.safeParseFloat(row.total_payments),
                                        refunds: USCTDP_Admin.safeParseFloat(row.total_refunds),
                                        houseCredits: USCTDP_Admin.safeParseFloat(row.total_house_credits),
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

        function openPaymentHistoryModal(purchaseId, account) {
            $('#payment-history-modal').data("purchaseId", purchaseId);
            $('#payment-history-modal').data("account", account);
            paymentHistoryTable.ajax.reload();
            paymentHistoryModal.showModal();
        }

        function openPostPaymentModal(purchases) {
            paymentTable.clear();
            let count = 0;
            for (const purchase of purchases) {
                const payments = USCTDP_Admin.safeParseFloat(purchase.total_payments);
                const fees = USCTDP_Admin.safeParseFloat(purchase.total_fees);
                const adjustments = USCTDP_Admin.safeParseFloat(purchase.total_adjustments);
                const net_fee = fees - adjustments;
                if (net_fee > payments) {
                    if (purchase.purchase_type === 'registration') {
                        paymentTable.addExistingRegistration(purchase);
                        count++;
                    } else if (purchase.purchase_type === 'merchandise') {
                        paymentTable.addExistingMerchandise(purchase);
                        count++;
                    }
                }
            }
            if (count > 0) {
                postPaymentModal.showModal();
            } else {
                alert("The selected registration(s) are already paid in full!");
            }
        }

        const refundMode = $('#refund-mode');
        const methodWrapper = $('#method-field-wrapper');
        const modeDesc = $('#mode-description');
        const methodSelect = $('#refund-method');
        const refundFields = $('#refund-fields');
        const directionWrapper = $('#direction-field-wrapper');
        const directionSelect = $('#refund-direction');

        refundMode.on('change', (e) => {
            const val = e.target.value;
            if (val === 'adjust_only') {
                directionWrapper.removeClass('hidden');
                refundFields.removeClass('hidden');
                methodWrapper.addClass('hidden');
                methodSelect.prop('required', false);
                directionSelect.prop('required', true);
                modeDesc.text("Adjusts the price, but does not record any transfer of funds.");
            } else if (val === 'payout_only') {
                directionWrapper.addClass('hidden');
                refundFields.removeClass('hidden');
                methodWrapper.removeClass('hidden');
                methodSelect.prop('required', true);
                directionSelect.prop('required', false);
                modeDesc.text("Records the transfer of funds for an already adjusted price.");
            } else if (val === 'standard') {
                directionWrapper.addClass('hidden');
                refundFields.removeClass('hidden');
                methodWrapper.removeClass('hidden');
                methodSelect.prop('required', true);
                directionSelect.prop('required', false);
                modeDesc.text("Adjusts the price and records the transfer of funds.");
            } else {
                directionWrapper.addClass('hidden');
                refundFields.addClass('hidden');
                methodWrapper.addClass('hidden');
                methodSelect.prop('required', false);
                directionSelect.prop('required', false);
                modeDesc.text("Select an action to continue.");
            }
        });

        function openPostRefundModal(row) {
            const allRefunds = USCTDP_Admin.safeParseFloat(row.total_refunds) + USCTDP_Admin.safeParseFloat(row.total_house_credits);
            const payments = USCTDP_Admin.safeParseFloat(row.total_payments);
            const adjustments = USCTDP_Admin.safeParseFloat(row.total_adjustments);
            const fees = USCTDP_Admin.safeParseFloat(row.total_fees);
            const netFees = fees - adjustments;
            const netPayments = payments - allRefunds;
            const owed = netFees - netPayments;
            if (netPayments > 0) {
                refundFields.addClass('hidden');
                methodWrapper.addClass('hidden');
                methodSelect.prop('required', false);
                modeDesc.text("Select an action to continue.");
                $('#refund-form input').val('');
                $('#refund-form select').val('');
                $('#refund-adjust-price').prop('checked', false);
                $('#refund-form').data("purchaseId", row.purchase_id);
                $('#refund-form').data("purchaseType", row.purchase_type);
                $('#refund-form').data("studentId", row.student_id);
                $('#refund-form').data("familyId", row.family_id);
                if (owed < 0) {
                    $('#refund-adjust-price').prop('checked', true);
                }
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
            const action = refundMode.val();
            console.log(action);
            const amount = $('#refund-amount').val();
            const method = $('#refund-method').val();
            const reason = $('#refund-reason').val();
            const direction = $('#refund-direction').val();
            const purchaseId = $('#refund-form').data("purchaseId");
            const purchaseType = $('#refund-form').data("purchaseType");
            const studentId = $('#refund-form').data("studentId");
            const familyId = $('#refund-form').data("familyId");
            let entries = [];
            if (action === "adjust_only") {
                entries = USCTDP_Admin.createAdjustmentLedger({
                    amount: amount,
                    reason: reason,
                    purchase_id: purchaseId,
                    purchase_type: purchaseType,
                    student_id: studentId,
                    family_id: familyId,
                    direction: direction
                });
            } else if (action === "payout_only") {
                entries = USCTDP_Admin.createPayoutLedger({
                    amount: amount,
                    method: method,
                    reason: reason,
                    purchase_id: purchaseId,
                    purchase_type: purchaseType,
                    student_id: studentId,
                    family_id: familyId
                });
            } else if (action === "standard") {
                entries = USCTDP_Admin.createRefundLedger({
                    amount: amount,
                    method: method,
                    reason: reason,
                    purchase_id: purchaseId,
                    purchase_type: purchaseType,
                    student_id: studentId,
                    family_id: familyId
                });
            }

            USCTDP_Admin.ajax_submitLedgerEntries(entries)
                .then(() => {
                    var studentId = null;
                    if (preloadedData['student-selector']) {
                        studentId = preloadedData['student-selector']["id"];
                    }
                    postRefundModal.close();
                    historyTable.ajax.reload();
                    refreshFamilyBalance(familyId, studentId);
                })
                .catch((error) => {
                    alert("Failed to post refund: " + error.message);
                });
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
            $('#history-table tbody tr .purchase-card').toggleClass('selected', isChecked);
            updateBulkUI();
        });

        // Individual Row Click
        $('#history-table tbody').on('change', '.row-check', function () {
            $(this).closest('.purchase-card').toggleClass('selected', this.checked);
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
            const purchases = $('.row-check:checked').map(function () {
                const $row = $(this).closest("tr");
                return historyTable.row($row).data();
            }).get();

            if (action === 'post-payments') {
                openPostPaymentModal(purchases);
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
                $button.addClass('save-registration-btn');
                $row.find('.purchase-card .registration-fields').addClass('editing');
                $row.find(".ledger-action").prop('disabled', true);
                $row.find('select').prop('disabled', false);
                $row.find('input').prop('readonly', false);
                $row.find('textarea').prop('readonly', false);
            } else {
                const activityId = $row.find('.activity-select').first().val();
                const studentLevel = $row.find('.level-input').first().val();

                if (!activityId) {
                    alert("Please select an activity before saving.");
                    return;
                }

                $button.text("Edit");
                $button.data("state", "edit");
                $button.removeClass('save-registration-btn');
                $row.find('.purchase-card .registration-fields').removeClass('editing');
                $row.find('select').prop('disabled', true);
                $row.find('input').prop('readonly', true);
                $row.find('textarea').prop('readonly', true);
                $button.prop('disabled', true);

                var update = {
                    activity_id: activityId,
                    student_level: studentLevel
                }

                saveRegistrationFields(rowData.registration_id, update)
                    .then(response => {
                        console.log(response);
                        if (response.success) {
                            if (response.data.price_change && response.data.price_change.delta != 0) {
                                const oldPrice = parseFloat(response.data.price_change.old_price);
                                const oldPriceDisp = oldPrice.toFixed(2);
                                const newPrice = parseFloat(response.data.price_change.new_price);
                                const newPriceDisp = newPrice.toFixed(2);
                                const delta = parseFloat(response.data.price_change.delta);
                                const deltaDisp = delta.toFixed(2);
                                window.Swal.fire({
                                    title: "Price Change",
                                    html: `
                                    The selected activity has a different price:
                                    <ul>
                                        <li>Original Price: ${oldPriceDisp}</li>
                                        <li>New Price: ${newPriceDisp}</li>
                                    </ul>
                                    Would you like to apply this adjustment to the registration price?
                                    `,
                                    showDenyButton: true,
                                    confirmButtonText: "Yes",
                                    denyButtonText: `No`
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        const ledgerEntries = USCTDP_Admin.createAdjustmentLedger({
                                            family_id: familyId,
                                            student_id: studentId,
                                            purchase_id: rowData.purchase_id,
                                            amount: Math.abs(delta),
                                            reason: "Registration Change",
                                            purchase_type: rowData.purchase_type,
                                            direction: newPrice < oldPrice ? "decrease" : "increase"
                                        });
                                        USCTDP_Admin.ajax_submitLedgerEntries(ledgerEntries)
                                            .then(response => {
                                                Swal.fire("Saved!", "Price adjustment applied.", "success");
                                                historyTable.ajax.reload();
                                                refreshFamilyBalance($('#family-selector').val(), null);
                                            })
                                            .catch(error => {
                                                Swal.fire(
                                                    "Error!",
                                                    "Price adjustment could not be applied. Inform a developer.",
                                                    "error"
                                                );
                                            });
                                    } else if (result.isDenied) {
                                        Swal.fire("Skipped!", "Adjustment not applied.", "info");
                                    }
                                });
                            }
                        }
                    })
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
