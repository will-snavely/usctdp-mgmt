(function ($) {
    window.USCTDP_Admin = window.USCTDP_Admin || {};

    USCTDP_Admin.displayTime = function (dateObj) {
        const options = {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        return new Intl.DateTimeFormat('en-US', options).format(dateObj);
    }

    USCTDP_Admin.applyReplacements = function (input, replacements) {
        return replacements.reduce((currentString, [pattern, replacement]) => {
            return currentString.replace(pattern, replacement);
        }, input);
    }

    USCTDP_Admin.formatUsd = function (amount) {
        if (amount === null) {
            amount = 0;
        }
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    function select2Options(options) {
        const {
            placeholder = "Search...",
            allowClear = true,
            target = null,
            url = usctdp_mgmt_admin.ajax_url,
            action = usctdp_mgmt_admin.select2_search_action,
            nonce = usctdp_mgmt_admin.select2_search_nonce,
            minimumInputLength = 0,
            filter = () => ({}),
            pinnedOptions = [],
            ...extraOptions
        } = options;

        return {
            placeholder,
            allowClear,
            minimumInputLength,
            ajax: {
                url,
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        action: action,
                        security: nonce,
                        target: target,
                        ...filter()
                    };
                },
                processResults: function (data, params) {
                    const isSearching = params.term && params.term.length > 0;
                    const finalResults = isSearching ? data.items : pinnedOptions.concat(data.items);
                    return {
                        results: finalResults
                    };
                },
                cache: true
            },
            ...extraOptions
        };
    };

    USCTDP_Admin.select2Options = select2Options;

    USCTDP_Admin.CascasdingSelect = class {
        constructor(containerId, config) {
            this.container = $(`#${containerId}`);
            this.container.addClass("context-selector-group");
            this.config = config;
            this.state = {};
            this.init();
        }

        trigger(eventName, detail = {}) {
            const event = new CustomEvent(`cascade:${eventName}`, {
                detail: { ...detail, manager: this },
                bubbles: true
            });
            this.container[0].dispatchEvent(event);
        }

        init() {
            Object.entries(this.config).forEach(([id, settings]) => {
                this.renderSection(id, settings);
                this.initSelect2(id, settings);
            });

            this.container.on('change', '.context-selector', (e) => {
                this.handleChange($(e.currentTarget));
            });

            this.trigger('ready', { state: this.state });
        }

        renderSection(id, settings) {
            const isVisible = settings.isRoot ? '' : 'hidden';
            const html = `
                <div id="${id}-section" class="context-selector-section ${isVisible}">
                    <label for="${id}" class="context-selector-label">${settings.label}</label>
                    <div class="content-selector-wrap">
                        <select id="${id}" name="${settings.name}" class="context-selector" style="width:100%">
                        </select>
                    </div>
                </div>`;

            this.container.append(html);
        }

        initSelect2(id, settings) {
            const $el = $(`#${id}`);
            $el.select2(
                select2Options({
                    placeholder: `Select ${settings.label}...`,
                    allowClear: true,
                    target: settings.target,
                    filter: settings.filter,
                    pinnedOptions: settings.pinnedOptions
                })
            );
        }

        handleChange($el) {
            const id = $el.attr('id');
            const settings = this.config[id];
            const val = $el.val();
            const text = $el.find('option:selected').text();

            // Determine the "Next" selector based on logic or static ID
            const next = settings.next;
            var nextId = typeof next === "function" ? next(val, $el) : next;
            var branches = typeof next === "string" ? [next] : settings.branches;
            if (branches) {
                branches.forEach(branchId => this.resetAndHide(branchId));
            }

            if (nextId && val) {
                $(`#${nextId}-section`).removeClass('hidden');
            }

            this.updateState();
            this.trigger('change', {
                selectorId: id,
                value: val,
                text: text,
                nextId: nextId,
                complete: val && (!nextId || nextId.length === 0),
                state: this.state
            });
        }

        resetAndHide(id) {
            const $el = $(`#${id}`);
            const settings = this.config[id];

            if ($el.prop('disabled')) return;

            $el.val(null).trigger('change.select2');
            $(`#${id}-section`).addClass('hidden');

            const next = settings.next
            var branches = typeof next === "string" ? [next] : settings.branches
            if (branches) {
                branches.forEach(branchId => this.resetAndHide(branchId));
            }
        }

        applyData(data) {
            Object.entries(this.config).forEach(([id, settings]) => {
                const entry = data[id];
                if (entry) {
                    const $el = $(`#${id}`);
                    const newOption = new Option(entry.text, entry.id, true, true);
                    $el.append(newOption).trigger('change');
                    $el.prop('disabled', entry.disable ?? true);
                    $(`#${id}-section`).removeClass('hidden');
                }
            });
        }

        updateState() {
            this.state = {};
            this.container.find('.context-selector').each((i, el) => {
                if ($(el).val()) {
                    this.state[$(el).attr('name')] = $(el).val();
                }
            });
        }
    };

    USCTDP_Admin.RegistrationPaymentTable = class {
        constructor(containerId, settings) {
            this.container = $(`#${containerId}`);
            this.settings = settings ?? {};
            this.init();
        }

        trigger(eventName, detail = {}) {
            const event = new CustomEvent(`payment:${eventName}`, {
                detail: { ...detail, manager: this },
                bubbles: true
            });
            this.container[0].dispatchEvent(event);
        }

        init() {
            this.renderTable();
            this.container.on('change', '.price-input', (event) => {
                this.updatePaymentTotals();
            });

            this.container.on('click', '.checkout-btn', (event) => {
                event.preventDefault();
                this.container.find('.checkout-btn-wrap').addClass('hidden');
                this.container.find('.modify-btn-wrap').removeClass('hidden');
                this.container.find('.checkout-section').removeClass('hidden');
                this.trigger('checkout', {});
            });

            this.container.on('click', '.modify-btn', (event) => {
                event.preventDefault();
                this.container.find('.checkout-btn-wrap').removeClass('hidden');
                this.container.find('.modify-btn-wrap').addClass('hidden');
                this.container.find('.checkout-section').addClass('hidden');
                this.trigger('modify', {});
            });

            this.container.on('click', '.transfer-one', (event) => {
                const $row = $(event.currentTarget).closest('tr');
                const debit = $row.find('.debit-input').val();
                $row.find('.credit-input').val(debit);
                this.updatePaymentTotals();
            });

            this.container.on('click', '.transfer-all', (event) => {
                const $rows = $(event.currentTarget).closest('table').find('tbody tr');
                $rows.each((i, row) => {
                    const $row = $(row);
                    const debit = $row.find('.debit-input').val();
                    $row.find('.credit-input').val(debit);
                });
                this.updatePaymentTotals();
            });

            this.container.on('change', `#${this.getId('payment_method')}`, (event) => {
                const value = event.currentTarget.value;
                this.container.find(".payment-option").addClass('hidden');
                this.container.find('.submit-payment-wrap').toggleClass('hidden', value === "");

                if (value === 'check') {
                    this.container.find('.check-fields input').val('');
                    this.container.find('.check-fields').removeClass('hidden');
                } else if (value === 'pay_later') {
                    this.container.find('.pay-later-fields input').val('');
                    this.container.find('.pay-later-fields').removeClass('hidden');
                } else if (value === 'card') {
                    this.container.find('.card-fields input').val('');
                    this.container.find('.card-fields').removeClass('hidden');
                }
            });

            this.container.on('click', '.remove-btn', (e) => {
                e.preventDefault();
                const $row = $(e.currentTarget).closest('tr');
                $row.remove();
                this.updatePaymentTotals();

                const rowCount = this.container.find("tbody tr").length;
                this.trigger('removeItem', { remaining: rowCount });

                if (rowCount === 0) {
                    this.trigger('empty', {});
                }
            });

            $(`#${this.getId('submit-payment-form')}`).on('submit', (e) => {
                e.preventDefault();
                const { redirectOnComplete = true } = this.settings;
                const form = $(e.currentTarget);
                const $submitBtn = $('#' + this.getId('submit-payment-btn'));
                $submitBtn.prop('disabled', true).val('Processing...');
                const orderData = this.getOrderData();
                this.submitPayment(orderData)
                    .then((response) => {
                        if (orderData.payment_method == 'card' || redirectOnComplete) {
                            const regIds = Object.values(response.registrations);
                            const orderUrl = response.order ? response.order.order_url : '';
                            const paymentUrl = response.order ? response.order.payment_url : '';
                            const userId = response.order ? response.order.user_id : '';
                            $('#' + this.getId('submit_user_id')).val(userId);
                            $('#' + this.getId('submit_payment_url')).val(paymentUrl);
                            $('#' + this.getId('submit_order_url')).val(orderUrl);
                            $('#' + this.getId('submit_family_id')).val(orderData.family_id);
                            $('#' + this.getId('submit_payment_method')).val(orderData.payment_method);
                            $('#' + this.getId('submit_registrations')).val(JSON.stringify(regIds));
                            form[0].submit();
                        }
                    })
                    .catch((error) => {
                        console.error("Order creation failed:", error);
                        alert('There was an error. Please try again, or inform a developer.');
                    })
                    .finally(() => {
                        const submitBtnText = this.settings.submitButtonText ?? "Submit Payment";
                        $submitBtn.prop('disabled', false).val(submitBtnText);
                        this.trigger('complete', {});
                    });
            });
        }

        checkoutActivityName(name) {
            const replacements = [
                [/^Adult/, ""],
            ];
            return USCTDP_Admin.applyReplacements(name, replacements);
        }

        getOrderData() {
            const $rows = this.container.find('.payment-table tbody tr');
            const paymentMethod = $('#' + this.getId('payment_method')).val();

            let checkNumber = null;
            if (paymentMethod === 'check') {
                checkNumber = this.container.find("#" + this.getId('check_number')).val();
            }
            var familyId = null;
            var lineItems = [];
            let lineItemId = 0;

            let rawDebit = this.container.find('.debit-summary .total').data('value');
            let totalDebit = parseFloat(rawDebit);
            let rawCredit = this.container.find('.credit-summary .total').data('value');
            let totalCredit = parseFloat(rawCredit);

            $rows.each(function () {
                const $row = $(this);
                const type = $row.data('type');
                const currentFamilyId = $row.data('family_id');
                if (familyId === null) {
                    familyId = currentFamilyId;
                } else if (currentFamilyId !== familyId) {
                    throw new Error('All orders must be for the same family.');
                }

                lineItemId++;

                if (type === 'registration') {
                    const debit = parseFloat($row.find('.debit-input').val()).toFixed(2);
                    var credit = parseFloat($row.find('.credit-input').val()).toFixed(2);
                    if (paymentMethod === 'pay_later') {
                        credit = 0;
                    }
                    const registration = {
                        registration_id: $row.data('registration_id'),
                        family_id: $row.data('family_id'),
                        student_id: $row.data('student_id'),
                        session_id: $row.data('session_id'),
                        activity_id: $row.data('activity_id'),
                        product_id: $row.data('product_id'),
                        student_level: $row.data('student_level'),
                        notes: $row.data('notes'),
                        credit: parseFloat(credit),
                        debit: parseFloat(debit),
                        type: 'registration',
                        line_item_id: lineItemId,
                    };
                    lineItems.push(registration);
                } else if (type === "equipment") {
                    lineItems.push({
                        product_code: $row.data('product_code'),
                        student_id: $row.data('student_id'),
                        family_id: $row.data('family_id'),
                        credit: parseFloat(credit),
                        debit: parseFloat(debit),
                        type: 'equipment',
                        line_item_id: lineItemId,
                    });
                }
            });
            return {
                family_id: familyId,
                payment_method: paymentMethod,
                total_debit: totalDebit,
                total_credit: totalCredit,
                total_balance: totalDebit - totalCredit,
                check_number: checkNumber,
                line_items: lineItems,
            };
        }

        async createRegistrations(orderData) {
            try {
                const registrations = orderData.line_items.filter(item => item.type === 'registration');
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.commit_registrations_action,
                        security: usctdp_mgmt_admin.commit_registrations_nonce,
                        registration_data: registrations,
                    }
                });
                if (response.success) {
                    return response.data.ids;
                } else {
                    throw new Error(response.data || 'Server error');
                }
            } catch (error) {
                console.error('Registration Commit Failed:', error.statusText || error.message);
                throw error;
            }
        }

        async createWooCommerceOrder(orderData) {
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.create_woocommerce_order_action,
                        security: usctdp_mgmt_admin.create_woocommerce_order_nonce,
                        line_items: orderData.line_items,
                        payment_method: orderData.payment_method,
                        check_number: orderData.check_number
                    }
                });

                if (response.success) {
                    return response.data;
                } else {
                    throw new Error(response.data || 'Server error');
                }

            } catch (error) {
                console.error('Order Creation Failed:', error.statusText || error.message);
                throw error;
            }
        }

        async submitLedgerEntries(ledger) {
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.create_ledger_entries_action,
                        security: usctdp_mgmt_admin.create_ledger_entries_nonce,
                        entries: ledger,
                    }
                });
                if (response.success) {
                    return response.data;
                } else {
                    throw new Error(response.data || 'Server error');
                }

            } catch (error) {
                console.error('Ledger Entry Creation Failed:', error.statusText || error.message);
                throw error;
            }
        }

        buildLedgerEntries(args) {
            const { lineItem, orderId, eventId, event, paymentMethod, checkNumber, isNew } = args;
            var result = [];
            var ledgerBase = {
                family_id: lineItem.family_id,
                student_id: lineItem.student_id,
                registration_id: lineItem.registration_id ?? null,
                order_id: orderId,
                event_id: eventId,
                event: event
            }
            if (isNew) {
                result.push({
                    ...ledgerBase,
                    account: lineItem.type + "_fees",
                    credit: parseFloat(0).toFixed(2),
                    debit: parseFloat(lineItem.debit).toFixed(2)
                });

                result.push({
                    ...ledgerBase,
                    account: "revenue",
                    credit: parseFloat(lineItem.debit).toFixed(2),
                    debit: parseFloat(0).toFixed(2)
                });
            }

            if (lineItem.credit > 0) {
                result.push({
                    ...ledgerBase,
                    account: "payment_" + paymentMethod,
                    payment_method: paymentMethod,
                    reference_id: checkNumber ?? null,
                    credit: parseFloat(0).toFixed(2),
                    debit: parseFloat(lineItem.debit).toFixed(2)
                });

                result.push({
                    ...ledgerBase,
                    account: lineItem.type + "_fees",
                    payment_method: paymentMethod,
                    reference_id: checkNumber ?? null,
                    credit: parseFloat(lineItem.credit).toFixed(2),
                    debit: parseFloat(0).toFixed(2)
                });

            }
            return result;
        }

        async submitPayment(orderData) {
            const { paymentMode = "update" } = this.settings;
            try {
                var ids = [];
                const lineItems = orderData.line_items;

                if (paymentMode === "create") {
                    ids = await this.createRegistrations(orderData);
                    for (var i = 0; i < lineItems.length; i++) {
                        const line_item_id = lineItems[i].line_item_id;
                        if (line_item_id in ids) {
                            lineItems[i].registration_id = ids[line_item_id];
                        } else {
                            console.log("Line item id " + line_item_id + " not found in created registrations.");
                        }
                    }
                }

                var order = null;
                var eventId = null;
                if (orderData.payment_method != "pay_later") {
                    order = await this.createWooCommerceOrder(orderData);
                    eventId = "order_" + order.order_id;
                } else {
                    eventId = "order_pay_later";
                }

                var event = '';
                if (paymentMode === "create") {
                    const isPartialPayment = orderData.total_balance > 0;
                    const partialNote = isPartialPayment ? " (Partial)" : "";
                    if (orderData.payment_method === "check") {
                        event = "Purchase w/ Check #" + orderData.check_number + partialNote;
                    } else if (orderData.payment_method === "cash") {
                        event = "Purchase w/ Cash" + partialNote;
                    } else if (orderData.payment_method === "card") {
                        event = "Order Initiated, Card Details Pending" + partialNote;
                    } else {
                        event = "Order Initiated, Payment Pending";
                    }
                } else {
                    const isPartialPayment = orderData.total_balance > 0;
                    const partialNote = isPartialPayment ? " (Partial)" : "";
                    if (orderData.payment_method === "check") {
                        event = "Payment Made w/ Check #" + orderData.check_number + partialNote;
                    } else if (orderData.payment_method === "cash") {
                        event = "Payment Made w/ Cash" + partialNote;
                    } else if (orderData.payment_method === "card") {
                        event = "Payment Initiated, Card Details Pending" + partialNote;
                    }
                }

                var ledger = [];
                for (var i = 0; i < lineItems.length; i++) {
                    var entries = this.buildLedgerEntries({
                        lineItem: lineItems[i],
                        orderId: order ? order.order_id : null,
                        eventId: eventId,
                        paymentMethod: orderData.payment_method,
                        checkNumber: orderData.check_number,
                        isNew: paymentMode === "create",
                        event: event
                    });
                    ledger.push(...entries);
                }
                const ledgerEntries = await this.submitLedgerEntries(ledger);

                return {
                    order: order,
                    registrations: ids,
                    ledger_entries: ledgerEntries
                };
            } catch (error) {
                console.error('Submission failed:', error);
                throw error;
            }
        }

        clear() {
            this.container.find("table tbody").empty();
        }

        getId(base) {
            const { idPrefix = "__usctdp_payment_" } = this.settings;
            return idPrefix + base;
        }

        renderTable() {
            const {
                submitButtonText = "Submit Payment",
                checkoutButton = false,
                allowPayLater = false,
                postUrl = usctdp_mgmt_admin?.post_url,
                postAction = usctdp_mgmt_admin?.payment_checkout_action,
                nonceValue = usctdp_mgmt_admin?.payment_checkout_nonce,
                nonceId = usctdp_mgmt_admin?.payment_checkout_nonce_id,
            } = this.settings;
            const checkoutButtonHtml = checkoutButton
                ? `<div class="checkout-btn-wrap">
                       <button class="checkout-btn button button-primary">Checkout</button>
                   </div>`
                : '';
            const modifyButtonHtml = checkoutButton
                ? `<div class="modify-btn-wrap hidden">
                       <button class="modify-btn button button-primary">Modify Order</button>
                   </div>`
                : '';
            const payLaterHtml = allowPayLater
                ? '<option value="pay_later">Pay Later</option>'
                : '';

            const html = `
                <div class="payment-wrap">
                    <div class="payment-table-wrap">
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Session</th>
                                    <th>Item</th>
                                    <th>Balance</th>
                                    <th>
                                        <div class="transfer-column">
                                            <button class="transfer-btn transfer-all">
                                                <div class="transfer-arrows">
                                                    <span class="transfer-arrow dashicons dashicons-arrow-right-alt2"></span>
                                                    <span class="transfer-arrow dashicons dashicons-arrow-right-alt2"></span>
                                                    <span class="transfer-arrow dashicons dashicons-arrow-right-alt2"></span>
                                                </div>
                                            </button>
                                        </div>
                                    </th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="payment-footer">
                        <div class="payment-summaries">
                            <div class="order-summary debit-summary">
                                <span class="label">Amount</br>Owed</span>
                                <span class="total"></span>
                            </div>
                            <div class="order-summary credit-summary">
                                <span class="label">Total</br>Payment</span>
                                <span class="total"></span>
                            </div>
                            <div class="order-summary balance-summary">
                                <span class="label">Remaining</br>Balance</span>
                                <span class="total"></span>
                            </div>
                        </div>
                        ${checkoutButtonHtml}
                        ${modifyButtonHtml}
                    </div>
                    <div class="checkout-section ${checkoutButton ? 'hidden' : ''}">
                        <div class="payment-method checkout-field">
                            <label for="${this.getId('payment_method')}">Payment Method</label>
                            <select name="payment_method" id="${this.getId('payment_method')}" autocomplete="off">
                                <option value="">Select...</option>
                                <option value="card" disabled>Card</option>
                                <option value="check" disabled>Check</option>
                                <option value="cash" disabled>Cash</option>
                                ${payLaterHtml}
                            </select>
                            <div class="payment-method-note">
                                <span></span>
                            </div>
                        </div>
                        <div class="check-fields payment-option hidden">
                            <div class="check-number-field checkout-field">
                                <label for="${this.getId('check_number')}">Check Number</label>
                                <input type="text" name="check_number" id="${this.getId('check_number')}">
                            </div>
                            <div id="${this.getId('check-received-date')}" class="checkout-field">
                                <label for="${this.getId('check_received_date')}">Date Received</label>
                                <input type="date" name="check_received_date" id="${this.getId('check_received_date')}">
                            </div>
                        </div>
                        <div class="card-fields payment-option payment-note hidden">
                            <h3> Note </h3>
                            <p>
                                By selecting <b>Card</b>, you will be redirected to a payment gateway
                                after clicking the <b>${submitButtonText}</b> button, where you can enter
                                card details and complete the transaction.
                            </p>
                        </div>
                        <div class="submit-payment-wrap hidden"> 
                            <form id="${this.getId('submit-payment-form')}" action="${postUrl}" method="post">
                                <input type="hidden" name="action" value="${postAction}">
                                <input type="hidden" name="${nonceId}" value="${nonceValue}">
                                <input type="hidden" id="${this.getId('submit_user_id')}" name="user_id" value="">
                                <input type="hidden" id="${this.getId('submit_family_id')}" name="family_id" value="">
                                <input type="hidden" id="${this.getId('submit_payment_url')}" name="payment_url" value="">
                                <input type="hidden" id="${this.getId('submit_order_url')}" name="order_url" value="">
                                <input type="hidden" id="${this.getId('submit_payment_method')}" name="payment_method" value="">
                                <input type="hidden" id="${this.getId('submit_registrations')}" name="registrations" value="">
                                
                                <div class="submit-payment-button-wrap">
                                    <input 
                                        type="submit" 
                                        name="submit-form"
                                        id="${this.getId('submit-payment-btn')}"
                                        class="button button-primary" 
                                        value="${submitButtonText}">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>`;
            this.container.append(html);
        }

        parsePaymentField($row, selector) {
            const raw = $row.find(selector).val();
            console.log(raw);
            return raw ? parseFloat(parseFloat(raw).toFixed(2)) : 0;
        }

        updatePaymentTotals() {
            const {
                allowPayLater = false,
            } = this.settings;

            const $rows = this.container.find('table tbody tr');
            let debit_total = 0;
            let credit_total = 0;
            $rows.each((index, elem) => {
                const $row = $(elem);
                debit_total += this.parsePaymentField($row, '.debit-input');
                credit_total += this.parsePaymentField($row, '.credit-input');
            });

            let balance = debit_total - credit_total;
            this.container.find(".debit-summary .total")
                .text(USCTDP_Admin.formatUsd(debit_total))
                .data('value', debit_total);
            this.container.find(".credit-summary .total")
                .text(USCTDP_Admin.formatUsd(credit_total))
                .data('value', credit_total);
            this.container.find(".balance-summary .total")
                .text(USCTDP_Admin.formatUsd(balance))
                .data('value', balance);

            if (credit_total > 0) {
                let paymentMethod = $('#' + this.getId('payment_method'));
                let selectedValue = paymentMethod.val();
                paymentMethod.find("option").prop('disabled', false);
                paymentMethod.find("option[value='pay_later']").prop('disabled', true);
                if (selectedValue === 'pay_later') {
                    paymentMethod.val('').trigger('change');
                }
                if (allowPayLater) {
                    this.container.find(".payment-method-note span")
                        .text("Because the payment balance is greater than zero, 'Pay Later' cannot be selected.");
                } else {
                    this.container.find(".payment-method-note span").text("");
                }
            } else {
                let paymentMethod = $('#' + this.getId('payment_method'));
                let selectedValue = paymentMethod.val();
                paymentMethod.find("option").prop('disabled', true);
                paymentMethod.find("option[value='pay_later']").prop('disabled', false);
                if (selectedValue !== 'pay_later') {
                    paymentMethod.val('pay_later').trigger('change');
                }
                if (allowPayLater) {
                    this.container.find(".payment-method-note span")
                        .text("Payment balance is currently zero, 'Pay Later' must be selected.");
                } else {
                    this.container.find(".payment-method-note span")
                        .text("Payment balance is currently zero. Please input a payment amount above to proceed.");
                }
            }
        }

        addOrderRow(options) {
            const { student, session, item, credit, debit } = options;
            return `
                <tr> 
                    <td class="cart-student-name">${student ?? '--'}</td>
                    <td class="cart-session">${session ?? '--'}</td>
                    <td class="cart-item">${item}</td>
                    <td class="cart-debit"> 
                        <input class="price-input debit-input" type="number" name="debit" value="${debit}">
                    </td>
                    <td>
                        <div class="transfer-column">
                            <button class="transfer-btn transfer-one">
                                <div class="transfer-arrows">
                                    <span class="transfer-arrow dashicons dashicons-arrow-right-alt2"></span>
                                    <span class="transfer-arrow dashicons dashicons-arrow-right-alt2"></span>
                                </div>
                            </button>
                        </div>
                    </td>
                    <td class="cart-credit"> 
                        <input class="price-input credit-input" type="number" name="credit" value="${credit}">
                    </td>
                    <td>
                        <button class="button remove-btn">Remove</button> 
                    </td>
                </tr>`
        }

        addEquipment(eq, price) {
            const studentName = `${eq.student_first} ${eq.student_last}`
            var $row = $(this.addOrderRow({
                student: studentName,
                item: eq.product_name,
                debit: price,
                credit: price
            }));
            $row.data('product_code', eq.product_code)
                .data('product_name', eq.product_name)
                .data('student_name', eq.student_name)
                .data('student_id', eq.student_id)
                .data('family_id', eq.family_id)
                .data('notes', eq.notes)
                .data('type', 'equipment');
            this.container.find('table tbody').append($row);
            this.trigger('cart:add', { row: $row });
            this.updatePaymentTotals();
        }

        addRegistration(registration, debit, credit) {
            const studentName = `${registration.student_first} ${registration.student_last}`
            var $row = $(this.addOrderRow({
                student: studentName,
                session: registration.session_name,
                item: registration.activity_name,
                debit: debit ?? "",
                credit: credit ?? ""
            }));
            $row.data('registration_id', registration.registration_id)
                .data('student_id', registration.student_id)
                .data('session_id', registration.session_id)
                .data('activity_id', registration.activity_id)
                .data('product_id', registration.product_id)
                .data('family_id', registration.family_id)
                .data('student_level', registration.student_level)
                .data('notes', registration.notes)
                .data('type', 'registration');
            this.container.find('table tbody').append($row);
            this.trigger('cart:add', { row: $row });
            this.updatePaymentTotals();
            return { success: true };
        }

        addExistingRegistration(registration) {
            if (!registration.registration_id) {
                throw new Error("Tried to add existing registration with no id.");
            }
            const $rows = this.container.find('table tbody tr').toArray();
            const isDuplicate = $rows.some(row => {
                const $row = $(row);
                return $row.data('registration_id') === registration.registration_id;
            });
            if (isDuplicate) {
                return { success: false, error: 'DUPLICATE_ITEM', message: "Item already in cart." };
            }
            const debit = registration.registration_debit;
            const credit = registration.registration_credit;
            var outstanding = debit - credit;
            return this.addRegistration(registration, outstanding, null);
        }

        addNewRegistration(registration, price) {
            const $rows = this.container.find('table tbody tr').toArray();
            const isDuplicate = $rows.some(row => {
                const $row = $(row);
                return $row.data('student_id') === registration.student_id &&
                    $row.data('session_id') === registration.session_id &&
                    $row.data('activity_id') === registration.activity_id;
            });
            if (isDuplicate) {
                return { success: false, error: 'DUPLICATE_ITEM', message: "Item already in cart." };
            }
            return this.addRegistration(registration, price, null);
        }
    };
})(jQuery);
