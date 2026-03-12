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
                processResults: function (data) {
                    return {
                        results: data.items || [],
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
                    filter: settings.filter
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
                this.container.find('.checkout-btn').addClass('hidden');
                this.container.find('.checkout-section').removeClass('hidden');
                this.trigger('checkout', {});
            });

            this.container.on('change', `#${this.getId('payment_method')}`, (event) => {
                const value = event.currentTarget.value;
                this.container.find(".payment-option").addClass('hidden');
                this.container.find('.submit-payment-wrap').toggleClass('hidden', value === "");

                if (value === 'check') {
                    this.container.find('.check-fields').removeClass('hidden');
                } else if (value === 'pay_later') {
                    this.container.find('.pay-later-fields').removeClass('hidden');
                } else if (value === 'card') {
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
                        const payment_method = $('#' + this.getId('payment_method')).val();
                        if(payment_method == 'card' || redirectOnComplete) {
                            const regIds = Object.values(response.registrations);
                            $('#' + this.getId('submit_user_id')).val(response.order.user_id);
                            $('#' + this.getId('submit_family_id')).val(response.order.family_id);
                            $('#' + this.getId('submit_payment_method')).val(payment_method);
                            $('#' + this.getId('submit_payment_url')).val(response.order.payment_url);
                            $('#' + this.getId('submit_registrations')).val(JSON.stringify(regIds));
                            form[0].submit();
                        }
                    })
                    .catch((error) => {
                        const submitBtnText = this.settings.submitButtonText ?? "Submit Payment";
                        console.error("Order creation failed:", error);
                        $submitBtn.prop('disabled', false).val(submitBtnText);
                        alert('There was an error. Please try again, or inform a developer.');
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
            const paymentMethodId = this.getId('payment_method')
            const paymentMethod = $('#' + paymentMethodId).val();
            const $rows = this.container.find('.payment-table tbody tr');
            const orderData = [];
            let lineItemId = 0;
            $rows.each(function () {
                const $row = $(this);
                const type = $row.data('type');
                lineItemId++;
                if (type === 'registration') {
                    const debit = parseFloat($row.find('.debit-input').val()).toFixed(2);
                    var credit = parseFloat($row.find('.credit-input').val()).toFixed(2);
                    if (paymentMethod === 'pay_later') {
                        credit = 0;
                    }
                    const registration = {
                        registration_id: $row.data('registration_id'),
                        student_id: $row.data('student_id'),
                        session_id: $row.data('session_id'),
                        activity_id: $row.data('activity_id'),
                        student_level: $row.data('student_level'),
                        family_id: $row.data('family_id'),
                        notes: $row.data('notes'),
                        credit: parseFloat(credit),
                        debit: parseFloat(debit),
                        type: 'registration',
                        line_item_id: lineItemId,
                    };
                    orderData.push(registration);
                } else if (type == "equipment") {
                    orderData.push({
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
            return orderData;
        }

        async createRegistrations(orderData) {
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.commit_registrations_action,
                        security: usctdp_mgmt_admin.commit_registrations_nonce,
                        registration_data: orderData.filter(item => item.type === 'registration'),
                    }
                });
                if (response.success) {
                    return response.data.ids;
                } else {
                    throw new Error(response.data || 'PHP logic error');
                }
            } catch (error) {
                console.error('Registration Commit Failed:', error.statusText || error.message);
                throw error;
            }
        }

        async createWooCommerceOrder(orderData) {
            try {
                const paymentMethod = this.container.find("#" + this.getId('payment_method')).val();
                var checkNumber = '';
                if (paymentMethod === 'check') {
                    checkNumber = this.container.find("#" + this.getId('check_number')).val();
                }
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.create_woocommerce_order_action,
                        security: usctdp_mgmt_admin.create_woocommerce_order_nonce,
                        order_data: orderData,
                        payment_method: paymentMethod,
                        check_number: checkNumber
                    }
                });

                if (response.success) {
                    return response.data;
                } else {
                    throw new Error(response.data || 'PHP logic error');
                }

            } catch (error) {
                console.error('Order Creation Failed:', error.statusText || error.message);
                throw error;
            }
        }

        async saveRegistrationFields(id, fields) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.save_registration_fields_action,
                    security: usctdp_mgmt_admin.save_registration_fields_nonce,
                    registration_id: id,
                    ...fields
                }
            });     
            return response;
        } 

        async submitPayment(orderData) {
            const payment_method = $('#' + this.getId('payment_method')).val();
            const { registrationMode = "update" } = this.settings;
            try {
                var ids = [];
                if (registrationMode === "create") {
                    ids = await this.createRegistrations(orderData);
                    for (var i = 0; i < orderData.length; i++) {
                        const line_item_id = orderData[i].line_item_id;
                        if (line_item_id in ids) {
                            orderData[i].registration_id = ids[line_item_id];
                        } else {
                            console.log("Line item id " + line_item_id + " not found in created registrations.");
                        }
                    }
                } else {
                    for (var i = 0; i < orderData.length; i++) {
                    }
 
                }

                const order = await this.createWooCommerceOrder(orderData);

                return {
                    order: order,
                    registrations: ids
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
                ? '<button class="checkout-btn button button-primary">Checkout</button>'
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
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
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
                        ${checkoutButtonHtml}
                    </div>
                    <div class="checkout-section ${checkoutButton ? 'hidden' : ''}">
                        <div class="payment-method checkout-field">
                            <label for="${this.getId('payment_method')}">Payment Method</label>
                            <select name="payment_method" id="${this.getId('payment_method')}" autocomplete="off">
                                <option value="">Select...</option>
                                <option value="card">Card</option>
                                <option value="check">Check</option>
                                <option value="cash">Cash</option>
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
                        <div class="pay-later-fields payment-option payment-note hidden">
                            <h3> Note </h3>
                            <p>
                                By selecting <b>Pay Later</b>, the credit amount for every line item
                                will be set to 0 (regardless of the payment amount entered above). 
                                Payment can be posted later on the <b>Registration History</b> page.
                            </p>
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

        updatePaymentTotals() {
            const $rows = this.container.find('table tbody tr');
            let debit_total = 0;
            let credit_total = 0;
            $rows.each(function () {
                const $row = $(this);
                const debit = parseFloat($row.find('.debit-input').val()).toFixed(2);
                const credit = parseFloat($row.find('.credit-input').val()).toFixed(2);
                debit_total += parseFloat(debit);
                credit_total += parseFloat(credit);
            });
            this.container.find(".debit-summary .total")
                .text(USCTDP_Admin.formatUsd(debit_total));
            this.container.find(".credit-summary .total")
                .text(USCTDP_Admin.formatUsd(credit_total));
            this.container.find(".balance-summary .total")
                .text(USCTDP_Admin.formatUsd(debit_total - credit_total));
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
            this.updatePaymentTotals();
        }

        addExistingRegistration(registration) {
            const debit = registration.registration_debit;
            const credit = registration.registration_credit;
            var outstanding = debit - credit;
            const studentName = `${registration.student_first} ${registration.student_last}`
            var $row = $(this.addOrderRow({
                student: studentName,
                session: registration.session_name,
                item: registration.activity_name,
                debit: outstanding,
                credit: outstanding
            }));
            $row.data('registration_id', registration.registration_id ?? null)
                .data('student_id', registration.student_id)
                .data('session_id', registration.session_id)
                .data('activity_id', registration.activity_id)
                .data('product_id', registration.product_id)
                .data('family_id', registration.family_id)
                .data('student_level', registration.student_level)
                .data('notes', registration.notes)
                .data('type', 'registration');
            this.container.find('table tbody').append($row);
            this.updatePaymentTotals();
        }

        addNewRegistration(registration, price) {
            const debit = price;
            const credit = price;
            const studentName = `${registration.student_first} ${registration.student_last}`
            var $row = $(this.addOrderRow({
                student: studentName,
                session: registration.session_name,
                item: registration.activity_name,
                debit: price,
                credit: price
            }));
            $row.data('registration_id', null)
                .data('student_id', registration.student_id)
                .data('session_id', registration.session_id)
                .data('activity_id', registration.activity_id)
                .data('product_id', registration.product_id)
                .data('family_id', registration.family_id)
                .data('student_level', registration.student_level)
                .data('notes', registration.notes)
                .data('type', 'registration');
            this.container.find('table tbody').append($row);
            this.updatePaymentTotals();
        }
    };
})(jQuery);
