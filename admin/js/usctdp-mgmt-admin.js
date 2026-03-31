(function ($) {
    window.USCTDP_Admin = window.USCTDP_Admin || {};

    USCTDP_Admin.ajax_submitLedgerEntries = async function (entries) {
        try {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.create_ledger_entries_action,
                    security: usctdp_mgmt_admin.create_ledger_entries_nonce,
                    entries: entries,
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

    USCTDP_Admin.createRefundEntries = function (args) {
        const {
            amount, method, reason, purchase_type,
            family_id, student_id, purchase_id,
        } = args;
        var results = [];
        var ledgerBase = {
            family_id: family_id,
            student_id: student_id,
            purchase_id: purchase_id,
            order_id: null,
            event_id: "account_refund",
            event: "Refund, " + method + ", " + reason
        }
        const amtFormatted = parseFloat(amount).toFixed(2);

        results.push({
            ...ledgerBase,
            account: "refund_contra",
            debit: amtFormatted,
            credit: parseFloat(0).toFixed(2),
            entry_type: "adjustment"
        });
        results.push({
            ...ledgerBase,
            account: purchase_type + "_fees",
            debit: parseFloat(0).toFixed(2),
            credit: amtFormatted,
            entry_type: "adjustment"
        });
        results.push({
            ...ledgerBase,
            account: "revenue",
            debit: amtFormatted,
            credit: parseFloat(0).toFixed(2),
            entry_type: "adjustment"
        });
        results.push({
            ...ledgerBase,
            account: "refund_contra",
            payment_method: method,
            debit: parseFloat(0).toFixed(2),
            credit: amtFormatted,
            entry_type: "adjustment"
        });

        var refundType = "payout";
        if (method == "house_credit") {
            refundType = "house_credit";
        }
        results.push({
            ...ledgerBase,
            account: purchase_type + "_fees",
            payment_method: method,
            debit: amtFormatted,
            credit: parseFloat(0).toFixed(2),
            entry_type: refundType
        });
        results.push({
            ...ledgerBase,
            account: "payment_" + method,
            payment_method: method,
            debit: parseFloat(0).toFixed(2),
            credit: amtFormatted,
            entry_type: refundType
        });

        return results;
    }

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
                    <div class="context-selector-label-wrap">
                        <label for="${id}" class="context-selector-label">${settings.label}</label>
                    </div>
                    <div class="context-selector-wrap">
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

    USCTDP_Admin.CartItem = class {
        constructor(data) {
            this.type = data.type || (data.registration_id ? 'registration' : 'merchandise');
            this.family_id = data.family_id;
            this.student_id = data.student_id;
            this.product_id = data.product_id;
            this.purchase_id = data.purchase_id || null;
            this.registration_id = data.registration_id || null;
            this.student_level = data.student_level || null;
            this.session_id = data.session_id || null;
            this.activity_id = data.activity_id || null;
            this.notes = data.notes || "";

            this.debit = parseFloat(data.debit || 0);
            this.credit = parseFloat(data.credit || 0);

            this.item_name = this.type === 'registration'
                ? `${data.session_name}: ${data.activity_name}`
                : data.product_name;
            this.student_name = `${data.student_first} ${data.student_last}`;
        }
    }

    USCTDP_Admin.RegistrationPaymentTable = class {
        constructor(containerId, settings) {
            this.container = $(`#${containerId}`);
            this.settings = settings ?? {};
            this.items = [];
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
            this.renderLayout();
            this.bindEvents();
        }

        renderLayout() {
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
                                    <th>Item</th>
                                    <th>Balance</th>
                                    <th>
                                        <div class="transfer-column">
                                            <button class="transfer-btn transfer-all">
                                                <div class="transfer-arrows">
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
                    <div class="checkout-section flex-col gap-10 ${checkoutButton ? 'hidden' : ''}">
                        <div class="payment-method-note">
                            <span></span>
                        </div>
                        <div class="payment-method checkout-field">
                            <label for="${this.getId('payment_method')}">Payment Method</label>
                            <select name="payment_method" id="${this.getId('payment_method')}" autocomplete="off">
                                <option value="">Select...</option>
                                <option value="card" disabled>Card</option>
                                <option value="check" disabled>Check</option>
                                <option value="cash" disabled>Cash</option>
                                ${payLaterHtml}
                            </select>
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

        bindEvents() {
            // Data Syncing
            this.container.on('change', '.price-input', (e) => {
                const index = $(e.currentTarget).closest('tr').index();
                this.items[index][e.currentTarget.name] = parseFloat(e.currentTarget.value) || 0;
                this.updatePaymentTotals();
            });

            // Transfers
            this.container.on('click', '.transfer-one', (e) => {
                const index = $(e.currentTarget).closest('tr').index();
                this.items[index].credit = this.items[index].debit;
                this.renderTableBody();
                this.updatePaymentTotals();
            });

            this.container.on('click', '.transfer-all', () => {
                this.items.forEach(item => item.credit = item.debit);
                this.renderTableBody();
                this.updatePaymentTotals();
            });

            // UI Toggles
            this.container.on('click', '.checkout-btn, .modify-btn', (e) => {
                const isCheckout = $(e.currentTarget).hasClass('checkout-btn');
                this.container.find('.checkout-btn-wrap').toggleClass('hidden', isCheckout);
                this.container.find('.modify-btn-wrap').toggleClass('hidden', !isCheckout);
                this.container.find('.checkout-section').toggleClass('hidden', !isCheckout);
                this.container.find('.remove-btn').prop('disabled', isCheckout);
                this.trigger(isCheckout ? 'checkout' : 'modify', {});
            });

            // Removal
            this.container.on('click', '.remove-btn', (e) => {
                const index = $(e.currentTarget).closest('tr').index();
                this.items.splice(index, 1);
                this.renderTableBody();
                this.updatePaymentTotals();
                this.trigger('cart:remove', { remaining: this.items.length });
                if (this.items.length === 0) {
                    this.trigger('cart:empty', {});
                }
            });

            // Payment Method Toggle
            this.container.on('change', `#${this.getId('payment_method')}`, (e) => {
                const val = e.currentTarget.value;
                this.container.find(".payment-option").addClass('hidden');
                this.container.find('.submit-payment-wrap').toggleClass('hidden', val === "");
                if (val) {
                    this.container.find(`.${val}-fields input`).val('');
                    this.container.find(`.${val}-fields`).removeClass('hidden');
                }
            });

            // Submission
            $(`#${this.getId('submit-payment-form')}`).on('submit', (e) => this.handleFormSubmit(e));
        }

        clear() {
            this.items = [];
            this.renderTableBody();
            this.updatePaymentTotals();
            this.trigger('cart:empty', {});
        }

        addNewRegistration(data, price) {
            const isDuplicate = this.items.some(item =>
                item.student_id === data.student_id &&
                item.session_id === data.session_id &&
                item.activity_id === data.activity_id);
            if (isDuplicate) {
                return { success: false, error: 'DUPLICATE_ITEM', message: "Item already in cart." };
            }
            data.type = 'registration';
            return this.addItem(data, price, 0);
        }

        addExistingRegistration(data) {
            const isDuplicate = this.items.some(item =>
                item.registration_id === data.registration_id);
            if (isDuplicate) {
                return { success: false, error: 'DUPLICATE_ITEM', message: "Item already in cart." };
            }
            const debit = data.total_debit;
            const credit = data.total_credit;
            var outstanding = debit - credit;
            data.type = 'registration';
            return this.addItem(data, outstanding, 0);
        }

        addNewMerchandise(data, price) {
            data.type = 'merchandise';
            return this.addItem(data, price, 0);
        }

        addExistingMerchandise(data) {
            const debit = data.total_debit;
            const credit = data.total_credit;
            var outstanding = debit - credit;
            data.type = 'merchandise';
            return this.addItem(data, outstanding, 0);
        }

        addItem(data, debit, credit) {
            const item = new USCTDP_Admin.CartItem({ ...data, debit, credit });
            this.items.push(item);
            this.renderTableBody();
            this.updatePaymentTotals();
            this.trigger('cart:add', { item });
            return { success: true };
        }

        getOrderData() {
            const method = $('#' + this.getId('payment_method')).val();
            return {
                family_id: this.items[0]?.family_id,
                payment_method: method,
                total_debit: this.items.reduce((s, i) => s + i.debit, 0),
                total_credit: this.items.reduce((s, i) => s + i.credit, 0),
                check_number: this.container.find("#" + this.getId('check_number')).val(),
                line_items: this.items.map((item, idx) => ({ ...item, line_item_id: idx + 1 }))
            };
        }

        async handleFormSubmit(e) {
            e.preventDefault();
            const $btn = $('#' + this.getId('submit-payment-btn'));
            $btn.prop('disabled', true).val('Processing...');

            try {
                const $form = $(e.currentTarget);
                const orderData = this.getOrderData();
                const response = await this.submitPayment(orderData);
                this.trigger('complete', { response });
                if (orderData.payment_method === 'card' || this.settings.redirectOnComplete) {
                    this.finalizeFormRedirect($form, response, orderData);
                }
            } catch (err) {
                alert('Error processing payment.');
            } finally {
                $btn.prop('disabled', false).val(this.settings.submitButtonText || "Submit Payment");
            }
        }

        finalizeFormRedirect($form, response, orderData) {
            var purchaseIds = [];
            var registrationIds = [];
            for (var i = 0; i < orderData.line_items.length; i++) {
                purchaseIds.push(orderData.line_items[i].purchase_id);
                if (orderData.line_items[i].registration_id) {
                    registrationIds.push(orderData.line_items[i].registration_id);
                }
            }
            const orderUrl = response.order ? response.order.order_url : '';
            const paymentUrl = response.order ? response.order.payment_url : '';
            const userId = response.order ? response.order.user_id : '';
            $('#' + this.getId('submit_user_id')).val(userId);
            $('#' + this.getId('submit_payment_url')).val(paymentUrl);
            $('#' + this.getId('submit_order_url')).val(orderUrl);
            $('#' + this.getId('submit_family_id')).val(orderData.family_id);
            $('#' + this.getId('submit_payment_method')).val(orderData.payment_method);
            $('#' + this.getId('submit_registrations')).val(JSON.stringify(registrationIds));
            $form[0].submit();
        }

        async createOrder(orderData) {
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.commit_order_action,
                        security: usctdp_mgmt_admin.commit_order_nonce,
                        line_items: orderData.line_items,
                    }
                });
                if (response.success) {
                    return response.data;
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

        buildLedgerEntries(args) {
            const { lineItem, orderId, eventId, event, paymentMethod, checkNumber, isNew } = args;
            var result = [];
            var ledgerBase = {
                family_id: lineItem.family_id,
                student_id: lineItem.student_id,
                purchase_id: lineItem.purchase_id,
                order_id: orderId,
                event_id: eventId,
                event: event
            }
            if (isNew) {
                result.push({
                    ...ledgerBase,
                    account: lineItem.type + "_fees",
                    debit: parseFloat(lineItem.debit).toFixed(2),
                    credit: parseFloat(0).toFixed(2),
                    entry_type: "charge"
                });

                result.push({
                    ...ledgerBase,
                    account: "revenue",
                    debit: parseFloat(0).toFixed(2),
                    credit: parseFloat(lineItem.debit).toFixed(2),
                    entry_type: "charge"
                });
            }

            if (lineItem.credit > 0) {
                result.push({
                    ...ledgerBase,
                    account: "payment_" + paymentMethod,
                    payment_method: paymentMethod,
                    reference_id: checkNumber ?? null,
                    debit: parseFloat(lineItem.debit).toFixed(2),
                    credit: parseFloat(0).toFixed(2),
                    entry_type: "payment"
                });

                result.push({
                    ...ledgerBase,
                    account: lineItem.type + "_fees",
                    payment_method: paymentMethod,
                    reference_id: checkNumber ?? null,
                    debit: parseFloat(0).toFixed(2),
                    credit: parseFloat(lineItem.credit).toFixed(2),
                    entry_type: "payment"
                });
            }
            return result;
        }

        async submitPayment(orderData) {
            const { paymentMode = "update" } = this.settings;
            try {
                const lineItems = orderData.line_items;
                if (paymentMode === "create") {
                    var order = await this.createOrder(orderData);
                    var purchaseIds = order.purchases;
                    for (var i = 0; i < lineItems.length; i++) {
                        const line_item_id = lineItems[i].line_item_id;
                        if (line_item_id in purchaseIds) {
                            lineItems[i].purchase_id = purchaseIds[line_item_id]['purchase_id'];
                            if (lineItems[i].type === "registration") {
                                lineItems[i].registration_id = purchaseIds[line_item_id]['registration_id'];
                            }
                        } else {
                            console.log("Line item id " + line_item_id + " not found in created purchases.");
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

                var ledgerEntries = [];
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
                    ledgerEntries.push(...entries);
                }
                const result = await USCTDP_Admin.ajax_submitLedgerEntries(ledgerEntries);

                return {
                    order: order,
                    purchases: purchaseIds,
                    ledger_entries: result
                };
            } catch (error) {
                console.error('Submission failed:', error);
                throw error;
            }
        }

        updatePaymentTotals() {
            const debit = this.items.reduce((s, i) => s + i.debit, 0);
            const credit = this.items.reduce((s, i) => s + i.credit, 0);
            const balance = debit - credit;

            this.container.find(".debit-summary .total").text(USCTDP_Admin.formatUsd(debit));
            this.container.find(".credit-summary .total").text(USCTDP_Admin.formatUsd(credit));
            this.container.find(".balance-summary .total").text(USCTDP_Admin.formatUsd(balance));

            this.updatePaymentMethodConstraints(credit);
        }

        updatePaymentMethodConstraints(creditTotal) {
            const $method = $('#' + this.getId('payment_method'));
            const selectedVal = $method.val();
            const hasPayment = creditTotal > 0;
            const $paymentNote = this.container.find(".payment-method-note span");

            $method.find("option").prop('disabled', !hasPayment);
            $method.find("option[value='pay_later']").prop('disabled', hasPayment);

            if (hasPayment) {
                if (selectedVal === 'pay_later') {
                    $method.val('').trigger('change');
                }
                if (this.settings.allowPayLater) {
                    $paymentNote.text("Because the payment balance is greater than zero, 'Pay Later' cannot be selected.");
                } else {
                    $paymentNote.text("");
                }
            } else {
                if (this.settings.allowPayLater) {
                    $paymentNote.text("Payment balance is currently zero, 'Pay Later' must be selected.");
                    $method.val('pay_later').trigger('change');
                } else {
                    $paymentNote.text("Payment balance is currently zero. Please input a payment amount above to proceed.");
                    $method.val('').trigger('change');
                }
            }
        }

        renderTableBody() {
            const $tbody = this.container.find('table tbody').empty();
            this.items.forEach(item => {
                $tbody.append(this.addOrderRow({
                    student: item.student_name,
                    item: item.item_name,
                    debit: item.debit,
                    credit: item.credit
                }));
            });
        }

        getId(base) {
            const { idPrefix = "__usctdp_payment_" } = this.settings;
            return idPrefix + base;
        }

        addOrderRow(options) {
            const { student, item, credit, debit } = options;
            return `
                <tr> 
                    <td class="cart-student-name">${student ?? '--'}</td>
                    <td class="cart-item">${item}</td>
                    <td class="cart-debit"> 
                        <input class="price-input debit-input" type="number" name="debit" value="${debit}">
                    </td>
                    <td>
                        <div class="transfer-column">
                            <button class="transfer-btn transfer-one">
                                <div class="transfer-arrows">
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
    }
})(jQuery);
