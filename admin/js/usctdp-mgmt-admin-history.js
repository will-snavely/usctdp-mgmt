(function ($) {
    "use strict";

    $(document).ready(function () {
        class PurchaseCard {
            constructor(data, idx, isNew = false) {
                this.data = data;
                this.idx = idx;
                this.isNew = isNew;
            }

            render() {
                const createdDate = new Date(this.data.purchase_created_at).toLocaleString()
                return `
                    <div class="purchase-card edit-disabled ${this._card_classes()}" data-idx="${this.idx}">
                        <div class="flex-row gap-10 align-center">
                            <div class="checkbox-wrap">
                                <input type="checkbox" class="row-check" value="${this.data.registrationId || this.data.purchaseId}">
                            </div>
                            
                            ${this._renderStudentInfo()}

                            <div class="border-left">
                                <div class="flex-row gap-10 align-center">
                                    ${this._renderBadges()} 
                                    ${this._renderHeaderActions()}
                                </div>
                            </div>

                            <div class="border-left">
                                <div class="created-date flex-row gap-5 align-center">
                                    <label class="upper-heavy">Created At</label>
                                    <span class="created-date-value">${createdDate}</span>
                                </div>
                            </div>
                        </div>

                        <div class="card-middle-content">
                            ${this._renderMiddleSection()}
                        </div>

                        <div class="flex-row gap-10 w-100">
                            ${this._renderFinancialSection()}
                            ${this._renderNotesSection()}
                        </div>
                    </div>`;
            }

            _renderBadges() {
                return `
                    ${this._renderTypeBadge()} 
                    ${this._renderNewBadge()}
                    ${this._renderAdditionalBadges()}`;
            }

            _renderNewBadge() {
                if (this.isNew) {
                    return `<div class="new-purchase-badge"><span class="new-purchase">New!</span></div>`;
                }
                return '';
            }

            _renderStudentInfo() {
                return `
                    <div class="student-name-wrap">
                        <span class="student-name">${this.data.student_first} ${this.data.student_last}</span>
                    </div>
                    <div class="student-age-wrap">
                        <span class="student-age">Age: ${this.data.student_age}</span>
                    </div>`;
            }

            _renderAmountBadge(label, value, classes = []) {
                return `
                    <div class="flex-col gap-5 align-center">
                        <label class="upper-heavy">${label}</label>
                        <span class="badge ${classes.join(' ')}">${value}</span>
                    </div>`;
            }

            _renderFinancialSection() {
                const adjustments = USCTDP_Admin.safeParseFloat(this.data.total_adjustments);
                const fees = USCTDP_Admin.safeParseFloat(this.data.total_fees);
                const payments = USCTDP_Admin.safeParseFloat(this.data.total_payments);
                const refunds = USCTDP_Admin.safeParseFloat(this.data.total_refunds);
                const houseCredits = USCTDP_Admin.safeParseFloat(this.data.total_house_credits);

                const netFees = fees - adjustments;
                const netPayments = payments - (refunds + houseCredits);
                const owed = netFees - netPayments;

                const format = (val) => USCTDP_Admin.formatUsd(val);

                return `
                    <div class="flex-col gap-10 align-end">
                        <div class="payment-info">
                            ${this._renderAmountBadge('Net Fees', format(netFees), ['red-bg'])}
                            ${this._renderAmountBadge('Net Paid', format(netPayments), ['green-bg'])}
                            ${this._renderAmountBadge('Owed', format(owed), ['red-bg'])}
                            ${this._renderAmountBadge('Refunds', format(refunds), ['blue-bg'])}
                            ${this._renderAmountBadge('House Cr.', format(houseCredits), ['blue-bg'])}
                        </div>
                        <div class="flex-row gap-10">
                            <button id="payment-history-${this.idx}" class="button payment-history">Payment History</button>
                            <select id="payment-action-${this.idx}" class="payment-action-select">
                                <option value=""></option>
                                <option value="post-payment">Post Payment</option>
                                <option value="post-refund">Post Refund/Adjustment</option>
                            </select>
                            <button id="ledger-action-${this.idx}" class="button ledger-action" disabled>Go</button>
                        </div>
                    </div>`;
            }

            _renderNotesSection() {
                return `
                    <div class="notes-wrap flex-col gap-5">
                        <div class="flex-row gap-10 align-end">
                            <label class="upper-heavy">Notes</label>
                            <button id="save-notes-${this.idx}" class="button button-small save-notes-btn" disabled>Save</button>
                        </div>
                        <textarea rows=3 id="notes-input-${this.idx}" class="notes-input">${this.data.purchase_notes || ''}</textarea>
                    </div>`;
            }

            _renderTypeBadge() { return ''; }
            _renderAdditionalBadges() { return ''; }
            _renderHeaderActions() { return ''; }
            _renderMiddleSection() { return ''; }
            _card_classes() { return ''; }
        }

        class RegistrationCard extends PurchaseCard {
            _card_classes() {
                if (this.data.registration_status === 'void') {
                    return 'void-registration';
                }
                return '';
            }

            _renderTypeBadge() {
                return `<span class="purchase-badge blue-bg upper-heavy">Registration</span>`;
            }

            _renderAdditionalBadges() {
                if (this.data.registration_status === 'void') {
                    return `<span class="purchase-badge red-bg upper-heavy">Void</span>`;
                }
                return '';
            }

            _renderHeaderActions() {
                if (this.data.registration_status === 'void') {
                    return `
                        <button id="restore-registration-${this.idx}" class="button button-small restore-registration-btn" data-state="edit">
                            Restore
                        </button>`;
                }
                return `
                    <button id="edit-registration-${this.idx}" class="button button-small edit-registration-btn" data-state="edit">
                        Modify
                    </button>
                    <button id="save-registration-${this.idx}" class="button button-small save-registration-btn hidden" data-state="edit">
                        Save
                    </button>
                    <button id="void-registration-${this.idx}" class="button button-small void-registration-btn" data-state="edit">
                        Void
                    </button>`;
            }

            _renderMiddleSection() {
                const sessionSelectId = `session-selector-${this.idx}`;
                const activitySelectId = `activity-selector-${this.idx}`;
                return `
                    <div class="registration-fields flex-row gap-10">
                        <div class="session-selector-wrap flex-col gap-5">
                            <label class="upper-heavy">Session</label>
                            <div id="session-selector-wrap-${this.idx}">
                                <select id="${sessionSelectId}" class="session-select" data-orig-value="${this.data.session_id}"
                                    data-orig-text="${this.data.session_name}" data-activity-selector-id="${activitySelectId}" disabled>
                                    <option value="${this.data.session_id}" selected>${this.data.session_name}</option>
                                </select>
                            </div>
                        </div>
                        <div class="activity-selector-wrap flex-col gap-5">
                            <label class="upper-heavy">Activity</label>
                            <div id="activity-selector-wrap-${this.idx}">
                                <select id="${activitySelectId}" class="activity-select" data-orig-value="${this.data.activity_id}"
                                    data-orig-text="${this.data.activity_name}" data-session-selector-id="${sessionSelectId}" disabled>
                                    <option value="${this.data.activity_id}" selected>${this.data.activity_name}</option>
                                </select>
                            </div>
                        </div>
                        <div class="level-wrap flex-col gap-5">
                            <label class="upper-heavy">Level</label>
                            <input id="level-input-${this.idx}" class="level-input" value="${this.data.registration_student_level}" readonly>
                        </div>
                    </div>`;
            }
        }

        class MerchandiseCard extends PurchaseCard {
            _renderTypeBadge() {
                return `<span class="purchase-badge green-bg upper-heavy">Merchandise</span>`;
            }

            _renderMiddleSection() {
                return `
                    <div class="product-wrap" style="padding: 10px 0;">
                        <span class="product-name"><strong>Product:</strong> ${this.data.product_name}</span>
                    </div>`;
            }
        }

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

        function refreshFamilyBalance() {
            const family_id = $('#family-selector').val();
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.get_family_balance_action,
                    security: usctdp_mgmt_admin.get_family_balance_nonce,
                    family_id: family_id
                },
                success: function (response) {
                    $('#family-total-balance').text(USCTDP_Admin.formatUsd(response.data.balance));
                    $('#family-total-balance').toggleClass('red-bg', response.data.balance > 0);
                    $('#family-total-balance').toggleClass('green-bg', response.data.balance <= 0);
                    $('#family-total-house-credit').text(USCTDP_Admin.formatUsd(response.data.house_credit));
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

        async function handlePriceChange(rowData, oldPrice, newPrice) {
            const updatePrice = await window.Swal.fire({
                title: "Price Change",
                html: `
                    The selected activity has a different price:
                    <ul>
                        <li>Original Price: ${USCTDP_Admin.formatUsd(oldPrice)}</li>
                        <li>New Price: ${USCTDP_Admin.formatUsd(newPrice)}</li>
                    </ul>
                    Would you like to apply this adjustment to the registration price?
                `,
                showDenyButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `No`
            });

            if (updatePrice.isConfirmed) {
                const absoluteDelta = Math.abs(newPrice - oldPrice);
                const ledgerEntries = USCTDP_Admin.createAdjustmentLedger({
                    family_id: rowData.family_id,
                    student_id: rowData.student_id,
                    purchase_id: rowData.purchase_id,
                    amount: absoluteDelta,
                    reason: "Registration Change",
                    purchase_type: rowData.purchase_type,
                    direction: newPrice < oldPrice ? "decrease" : "increase"
                });
                await USCTDP_Admin.ajax_submitLedgerEntries(ledgerEntries);
                Swal.fire("Saved!", "Price adjustment applied.", "success");
            } else {
                Swal.fire("Skipped!", "Adjustment not applied.", "info");
            }
        }

        async function updateRegistration(rowData, fields) {
            const saveResponse = await saveRegistrationFields(rowData.registration_id, fields);
            if (!saveResponse.success) {
                throw Error("Failed to update registration.");
            }

            const priceChange = saveResponse.data.price_change;
            if (priceChange && priceChange.delta != 0) {
                const oldPrice = parseFloat(priceChange.old_price);
                const newPrice = parseFloat(priceChange.new_price);
                await handlePriceChange(rowData, oldPrice, newPrice);
            }
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

                    var statusFilterValue = $('#status-filter').val();
                    if (statusFilterValue) {
                        d.status = statusFilterValue;
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
                                    return new RegistrationCard(row, meta.row, false).render();
                                } else if (row.purchase_type == 'merchandise') {
                                    return new MerchandiseCard(row, meta.row, false).render();
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
                    $('.table-filter').on('change', function () {
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
            methodSelect.val('').trigger('change');
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

        methodSelect.on('change', (e) => {
            const val = e.target.value;
            if (val === 'check') {
                $('#check-number-field-wrapper').removeClass('hidden');
                $('#refund-check-number').prop('required', true);
            } else {
                $('#check-number-field-wrapper').addClass('hidden');
                $('#refund-check-number').prop('required', false);
            }
        });

        function openPostRefundModal(row) {
            refundFields.addClass('hidden');
            methodWrapper.addClass('hidden');
            methodSelect.prop('required', false);
            modeDesc.text("Select an action to continue.");
            $('#refund-form input').val('');
            $('#refund-form select').val('');
            $('#refund-form').data("purchaseId", row.purchase_id);
            $('#refund-form').data("purchaseType", row.purchase_type);
            $('#refund-form').data("studentId", row.student_id);
            $('#refund-form').data("familyId", row.family_id);
            postRefundModal.showModal();
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
            const checkNumber = $('#refund-check-number').val();
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
                    family_id: familyId,
                    check_number: checkNumber
                });
            } else if (action === "standard") {
                entries = USCTDP_Admin.createRefundLedger({
                    amount: amount,
                    method: method,
                    reason: reason,
                    purchase_id: purchaseId,
                    purchase_type: purchaseType,
                    student_id: studentId,
                    family_id: familyId,
                    check_number: checkNumber
                });
            }

            USCTDP_Admin.ajax_submitLedgerEntries(entries)
                .then(() => {
                    postRefundModal.close();
                    historyTable.ajax.reload();
                    refreshFamilyBalance();
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
            $row.find('.save-notes-btn').prop('disabled', false);
        });

        $('#history-table tbody').on('click', '.save-notes-btn', function () {
            const $row = $(this).closest('tr');
            $row.find('.save-notes-btn').prop('disabled', true);
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

        $('#status-filter').select2({
            placeholder: "Filter by status...",
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

        $('#history-table tbody').on('click', 'button.edit-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            const $editButton = $(this);
            const saveButton = $row.find(".save-registration-btn");

            saveButton.removeClass("hidden");
            $editButton.addClass("hidden");
            $row.find('.purchase-card .registration-fields').addClass('editing');
            $row.find(".ledger-action").prop('disabled', true);
            $row.find('select').prop('disabled', false);
            $row.find('input').prop('readonly', false);
        });

        $('#history-table tbody').on('click', 'button.save-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            const $saveButton = $(this);
            const $editButton = $row.find(".edit-registration-btn");
            var rowData = historyTable.row($row).data();
            const activityId = $row.find('.activity-select').first().val();
            const studentLevel = $row.find('.level-input').first().val();

            if (!activityId) {
                Swal.fire({
                    icon: "error",
                    title: "Activity Required",
                    text: "Please select an activity before saving!",
                });
                return;
            }

            $row.find('.purchase-card .registration-fields').removeClass('editing');
            $row.find('select').prop('disabled', true);
            $row.find('input').prop('readonly', true);
            $row.find('textarea').prop('readonly', true);
            $saveButton.prop('disabled', true);

            var update = {
                activity_id: activityId,
                student_level: studentLevel
            }

            updateRegistration(rowData, update)
                .catch((error) => {
                    Swal.fire({
                        icon: "error",
                        title: "Error!",
                        text: "A server error occured. Please inform a developer. Details: " + error,
                    });
                })
                .finally(() => {
                    $saveButton.prop('disabled', false);
                    $saveButton.addClass('hidden');
                    $editButton.removeClass('hidden');
                    refreshFamilyBalance();
                    historyTable.ajax.reload();
                });
        });


        $('#history-table tbody').on('click', 'button.void-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            var rowData = historyTable.row($row).data();
            const studentName = `${rowData.student_first} ${rowData.student_last}`;

            var update = {
                status: 'void'
            };

            window.Swal.fire({
                title: "Confirm Void Registration",
                html: `
                    Are you sure you want to void this registration? This will
                    remove student <b> ${studentName}</b> from the roster for:
                    <b> ${rowData.activity_name}</b>.
                `,
                showDenyButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `No`
            }).then((result) => {
                if (result.isConfirmed) {
                    saveRegistrationFields(rowData.registration_id, update)
                        .catch((error) => {
                            Swal.fire({
                                icon: "error",
                                title: "Error!",
                                text: "A server error occured. Please inform a developer. Details: " + error,
                            });
                        })
                        .finally(() => {
                            refreshFamilyBalance();
                            historyTable.ajax.reload();
                        });
                }
            });
        });

        $('#history-table tbody').on('click', 'button.restore-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            var rowData = historyTable.row($row).data();
            const studentName = `${rowData.student_first} ${rowData.student_last}`;

            var update = {
                status: 'active'
            };

            window.Swal.fire({
                title: "Confirm Restore Registration",
                html: `
                    Are you sure you want to restore this registration? This will
                    add student <b> ${studentName}</b> back to the roster for:
                    <b> ${rowData.activity_name}</b>.
                `,
                showDenyButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `No`
            }).then((result) => {
                if (result.isConfirmed) {
                    saveRegistrationFields(rowData.registration_id, update)
                        .catch((error) => {
                            Swal.fire({
                                icon: "error",
                                title: "Error!",
                                text: "A server error occured. Please inform a developer. Details: " + error,
                            });
                        })
                        .finally(() => {
                            refreshFamilyBalance();
                            historyTable.ajax.reload();
                        });
                }
            });
        });

        $(`#${paymentTableId}`).on('payment:complete', function () {
            postPaymentModal.close();
            historyTable.ajax.reload();
            refreshFamilyBalance();
        });

        $('#history-table tbody').on('click', 'button.payment-history', function (e) {
            const $row = $(this).closest('tr');
            var rowData = historyTable.row($row).data();
            const purchaseId = rowData.purchase_id;
            const account = rowData.purchase_type + "_fees";
            openPaymentHistoryModal(purchaseId, account);
        });

        function load_registration_history(title) {
            historyTable.ajax.reload();
            refreshFamilyBalance();
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
                var title = $('#family-selector').find('option:selected').text();
                load_registration_history(title);
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
                $('#context-selectors').addClass('hidden');
                const newOption = new Option(preloadedStudent.student_name, preloadedStudent.student_id, true, true);
                $('#student-filter').append(newOption).trigger('change');
            }
            selectHandler.applyData(preloadedData);
        }
    });
})(jQuery);
