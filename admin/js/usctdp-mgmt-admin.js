(function ($) {
    window.USCTDP_Admin = window.USCTDP_Admin || {};

    // A native <dialog> shown via showModal() is promoted to the browser's
    // top layer, which renders above ALL regular content regardless of
    // z-index - so a Swal fired while a <dialog> is still open (e.g. an
    // error handler that doesn't close the dialog first) renders behind it.
    // Point Swal at the open dialog instead of document.body so its popup
    // becomes a descendant of that same top-layer subtree, where normal
    // stacking applies. Patched here, once, so every call site across every
    // admin page is covered without each one having to close its dialog
    // first. Deferred to document.ready since window.Swal comes from the
    // vendor bundle, whose load order relative to this file isn't guaranteed.
    $(document).ready(function () {
        if (window.Swal && typeof window.Swal.fire === 'function' && !window.Swal.__usctdpDialogPatched) {
            var originalFire = window.Swal.fire.bind(window.Swal);
            window.Swal.fire = function () {
                var args = Array.prototype.slice.call(arguments);
                var openDialog = document.querySelector('dialog[open]');
                if (openDialog && typeof args[0] === 'object' && args[0] !== null && args[0].target === undefined) {
                    args[0] = Object.assign({}, args[0], { target: openDialog });
                }
                return originalFire.apply(window.Swal, args);
            };
            window.Swal.__usctdpDialogPatched = true;
        }
    });

    USCTDP_Admin.ajax_saveRegistrationFields = async function (id, fields) {
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

    USCTDP_Admin.ajax_setRegistrationStatus = async function (id, status) {
        const response = await $.ajax({
            url: usctdp_mgmt_admin.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: usctdp_mgmt_admin.set_registration_status_action,
                security: usctdp_mgmt_admin.set_registration_status_nonce,
                registration_id: id,
                status: status
            }
        });
        return response;
    }

    USCTDP_Admin.ajax_generateStatement = async function (family_id, purchase_ids) {
        try {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.gen_statement_action,
                    security: usctdp_mgmt_admin.gen_statement_nonce,
                    family_id: family_id,
                    purchase_ids: purchase_ids
                }
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Server error');
            }
        } catch (error) {
            console.error('Generate Statement Failed:', error.statusText || error.message);
            throw error;
        }
    }

    USCTDP_Admin.ajax_generateSessionRoster = async function (session_id) {
        try {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.gen_roster_action,
                    session_id: session_id,
                    security: usctdp_mgmt_admin.gen_roster_nonce,
                }
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Server error');
            }
        } catch (error) {
            console.error('Generate Session Roster Failed:', error.statusText || error.message);
            throw error;
        }
    }

    // Usctdp_Session_Category::Junior_Tournament / Adult_Tournament
    USCTDP_Admin.TOURNAMENT_SESSION_CATEGORIES = [5, 6];

    /**
     * Tournament sessions have exactly one activity, so there's nothing
     * meaningful to pick beyond the session itself. Given a selected session's
     * select2 data, resolves the session's sole activity (or null if the
     * session isn't a tournament, or doesn't have exactly one activity).
     */
    USCTDP_Admin.resolveTournamentActivity = async function (sessionId, sessionData) {
        if (!sessionData || USCTDP_Admin.TOURNAMENT_SESSION_CATEGORIES.indexOf(sessionData.category) === -1) {
            return null;
        }
        try {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.select2_search_action,
                    security: usctdp_mgmt_admin.select2_search_nonce,
                    target: 'activity',
                    session_id: sessionId,
                    q: ''
                }
            });
            const items = (response && response.items) ? response.items : [];
            if (items.length !== 1) {
                return null;
            }
            return items[0];
        } catch (error) {
            console.error('Resolve Tournament Activity Failed:', error.statusText || error.message);
            return null;
        }
    }

    USCTDP_Admin.ajax_addWaitlistStudent = async function (student_id, activity_id) {
        try {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.waitlist_add_action,
                    security: usctdp_mgmt_admin.waitlist_add_nonce,
                    student_id: student_id,
                    activity_id: activity_id
                }
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Server error');
            }
        } catch (error) {
            console.error('Add Waitlist Student Failed:', error.statusText || error.message);
            throw error;
        }
    }

    USCTDP_Admin.ajax_removeWaitlistStudent = async function (student_id, activity_id) {
        try {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.waitlist_remove_action,
                    security: usctdp_mgmt_admin.waitlist_remove_nonce,
                    student_id: student_id,
                    activity_id: activity_id
                }
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Server error');
            }
        } catch (error) {
            console.error('Remove Waitlist Student Failed:', error.statusText || error.message);
            throw error;
        }
    }

    USCTDP_Admin.ajax_submitPayment = async function (orderData) {
        try {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.submit_payment_action,
                    security: usctdp_mgmt_admin.submit_payment_nonce,
                    payment_mode: orderData.payment_mode,
                    payment_method: orderData.payment_method,
                    check_number: orderData.check_number,
                    house_credit_applied: orderData.house_credit_applied,
                    ignore_class_full: 1,
                    line_items: orderData.line_items,
                }
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Server error');
            }
        } catch (error) {
            console.error('Payment Submission Failed:', error.statusText || error.message);
            throw error;
        }
    }

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

    USCTDP_Admin.safeParseFloat = function (value) {
        return parseFloat(value) || 0;
    }

    USCTDP_Admin.formatSnakeCase = function (str) {
        if (!str) return "";
        return str
            .replace(/_+/g, " ")
            .trim()
            .split(" ")
            .map(word => {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            })
            .join(" ");
    }

    USCTDP_Admin.createAdjustmentLedger = function (args) {
        const {
            family_id, student_id, purchase_id, event_id, event,
            amount, direction, reason, purchase_type
        } = args;

        const directionStr = direction.charAt(0).toUpperCase() + direction.slice(1);
        const description = `Price ${directionStr} - ${reason}`;
        var results = [];
        var ledgerBase = {
            event_id: event_id,
            event: event,
            family_id: family_id,
            student_id: student_id,
            purchase_id: purchase_id,
            order_id: null,
            description: description
        }

        const zero = "0.00";
        const amtDisplay = Math.abs(USCTDP_Admin.safeParseFloat(amount)).toFixed(2);
        const isIncrease = direction === "increase";
        results.push({
            ...ledgerBase,
            account: purchase_type + "_fees",
            debit: isIncrease ? amtDisplay : zero,
            credit: isIncrease ? zero : amtDisplay,
            entry_type: "adjustment"
        });
        results.push({
            ...ledgerBase,
            account: "revenue",
            debit: isIncrease ? zero : amtDisplay,
            credit: isIncrease ? amtDisplay : zero,
            entry_type: "adjustment"
        });
        return results;
    }

    USCTDP_Admin.createPayoutLedger = function (args) {
        const {
            family_id, student_id, purchase_id, event_id, event,
            amount, method, reason, purchase_type,
            check_number = null
        } = args;

        var results = [];
        const checkStr = check_number ? ` #${check_number}` : "";
        const methodStr = USCTDP_Admin.formatSnakeCase(method) + checkStr;
        const description = `Payout - ${methodStr} - ${reason}`;

        var ledgerBase = {
            event: event,
            event_id: event_id,
            family_id: family_id,
            student_id: student_id,
            purchase_id: purchase_id,
            order_id: null,
            description: description
        };

        const zero = "0.00";
        const amtDisplay = Math.abs(parseFloat(amount)).toFixed(2);

        // 1. The Customer Side (registration_fees or merchandise_fees)
        // We DEBIT this to "zero out" an overpayment (Credit balance)
        results.push({
            ...ledgerBase,
            account: `${purchase_type}_fees`,
            debit: amtDisplay,
            credit: zero,
            entry_type: method === "house_credit" ? "house_credit" : "refund"
        });

        // 2. The Asset Side (Where the money is physically leaving)
        results.push({
            ...ledgerBase,
            account: `payment_${method}`,
            debit: zero,
            credit: amtDisplay,
            entry_type: method === "house_credit" ? "house_credit" : "refund"
        });

        return results;
    };

    USCTDP_Admin.createRefundLedger = function (args) {
        const {
            amount, method, reason, purchase_type, event_id, event,
            family_id, student_id, purchase_id, check_number
        } = args;
        const adjustmentLedger = USCTDP_Admin.createAdjustmentLedger({
            event: event,
            event_id: event_id,
            family_id: family_id,
            student_id: student_id,
            purchase_id: purchase_id,
            amount: amount,
            direction: "decrease",
            reason: reason,
            purchase_type: purchase_type,
        });
        const payoutLedger = USCTDP_Admin.createPayoutLedger({
            event: event,
            event_id: event_id,
            family_id: family_id,
            student_id: student_id,
            purchase_id: purchase_id,
            amount: amount,
            method: method,
            reason: reason,
            purchase_type: purchase_type,
            check_number: check_number
        });
        return adjustmentLedger.concat(payoutLedger);
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

        reset() {
            Object.entries(this.config).forEach(([id, settings]) => {
                if (settings.isRoot) {
                    $(`#${id}`).val('').trigger('change');
                }
            });
        }

        renderSection(id, settings) {
            const isVisible = settings.isRoot ? '' : 'hidden';
            const required = settings.required ? 'required' : '';
            const html = `
                <div id="${id}-section" class="context-selector-section ${isVisible}">
                    <div class="context-selector-label-wrap">
                        <label for="${id}" class="context-selector-label">${settings.label}</label>
                    </div>
                    <div class="context-selector-wrap">
                        <select id="${id}" name="${settings.name}" class="context-selector" ${required}>
                        </select>
                    </div>
                </div>`;

            this.container.append(html);
        }

        initSelect2(id, settings) {
            const $el = $(`#${id}`);
            var args = {
                placeholder: `Select ${settings.label}...`,
                allowClear: true,
                target: settings.target,
                filter: settings.filter,
                pinnedOptions: settings.pinnedOptions,
            }
            if (settings.dropdownParent) {
                args.dropdownParent = settings.dropdownParent;
            }
            $el.select2(select2Options(args));
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

            if (val && settings.autoSelectChild) {
                const { id: childId, resolve } = settings.autoSelectChild;
                Promise.resolve(resolve(val, $el)).then((resolved) => {
                    if (resolved) {
                        this.setSilently(childId, resolved);
                    }
                });
            }
        }

        /**
         * Programmatically select a value on a select2-driven selector without
         * revealing its section - used when a parent selector can determine a
         * child's value on its own (e.g. a session with only one possible
         * activity), so the user is never shown a selector with nothing to
         * meaningfully pick. `entry` should carry the same fields the target's
         * own remote search would have returned, since consumers read extra
         * fields (e.g. `type`, `product_id`) off `$el.select2('data')[0]`.
         */
        setSilently(id, entry) {
            const $el = $(`#${id}`);
            $el.empty();
            const newOption = new Option(entry.text, entry.id, true, true);
            $(newOption).data('data', entry);
            $el.append(newOption).trigger('change');
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

    USCTDP_Admin.Discount = class {
        constructor(data) {
            this.code = data.code;
            this.value = data.value;
            this.reason = data.reason;
        }

        amount(base_price) {
            return 0;
        }
    }

    USCTDP_Admin.AdditionalDayDiscount = class extends USCTDP_Admin.Discount {
        constructor(amount) {
            super({
                code: 'second_day',
                value: amount,
                reason: 'Second Day Discount'
            });
        }

        amount(base_price) {
            return this.value;
        }
    }

    USCTDP_Admin.EarlySignupDiscount = class extends USCTDP_Admin.Discount {
        constructor(amount) {
            super({
                code: 'early_signup',
                value: amount,
                reason: 'Early Signup Discount'
            });
        }

        amount(base_price) {
            return this.value;
        }
    }

    USCTDP_Admin.WithClinicDiscount = class extends USCTDP_Admin.Discount {
        constructor(amount) {
            super({
                code: 'with_clinic',
                value: amount,
                reason: 'With Clinic Discount'
            });
        }

        amount(base_price) {
            return this.value;
        }
    }

    USCTDP_Admin.SiblingDiscount = class extends USCTDP_Admin.Discount {
        constructor(percent) {
            super({
                code: 'sibling_' + percent,
                value: percent,
                reason: 'Sibling Discount'
            });
        }

        amount(base_price) {
            return Math.floor(base_price * (this.value / 100.0));
        }
    }

    USCTDP_Admin.CustomFlatDiscount = class extends USCTDP_Admin.Discount {
        constructor(amount, reason) {
            super({
                code: 'custom_flat',
                value: amount,
                reason: reason
            });
        }

        amount(base_price) {
            return this.value;
        }
    }

    USCTDP_Admin.CustomPercentDiscount = class extends USCTDP_Admin.Discount {
        constructor(percent, reason) {
            super({
                code: 'custom_percent',
                value: percent,
                reason: reason
            });
        }

        amount(base_price) {
            return Math.floor(base_price * (this.value / 100.0));
        }
    }

    USCTDP_Admin.CartItem = class {
        constructor(data) {
            this.type = data.type || (data.registration_id ? 'registration' : 'merchandise');
            this.family_id = data.family_id;
            this.student_id = data.student_id;
            this.product_id = data.product_id;
            this.base_price = data.base_price;
            this.additional_day_discount = data.additional_day_discount;
            this.purchase_id = data.purchase_id || null;
            this.registration_id = data.registration_id || null;
            this.student_level = data.student_level || null;
            this.session_id = data.session_id || null;
            this.activity_id = data.activity_id || null;
            this.discounts = data.discounts || null;
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
            this.discountsModal = null;
            this.houseCreditAvailable = 0;
            this.houseCreditApplied = 0;
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
            if (this.settings.manageDiscounts) {
                this.discountsModal = new USCTDP_Admin.ManageDiscountsModal('payment-discounts-modal');
            }
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
                    <div id="payment-discounts-modal"></div>
                    <div class="payment-table-wrap">
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Item</th>
                                    <th>Owed</th>
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
                                    <th>Pay</th>
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
                        <div class="house-credit-section hidden">
                            <div class="house-credit-available">
                                <span class="label">Available House Credit</span>
                                <span class="value house-credit-available-value"></span>
                            </div>
                            <div class="house-credit-apply-field checkout-field">
                                <label for="${this.getId('house_credit_apply')}">Apply House Credit</label>
                                <input type="number" min="0" step="0.01"
                                    id="${this.getId('house_credit_apply')}"
                                    name="house_credit_apply" value="0.00">
                                <button type="button" class="button house-credit-max-btn">Max</button>
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
                                <option value="house_credit_only" disabled hidden>House Credit</option>
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
                this.container.find('.remove-order-item-btn').prop('disabled', isCheckout);
                this.trigger(isCheckout ? 'checkout' : 'modify', {});
            });

            if (this.settings.manageDiscounts) {
                this.container.on('click', '.discounts-btn', (e) => {
                    const index = $(e.currentTarget).closest('tr').index();
                    const item = this.items[index];
                    $('#payment-discounts-modal').data('item-index', index);
                    this.discountsModal.show(item.base_price, item.additional_day_discount, item.discounts);
                });

                $('#payment-discounts-modal').on('discounts:apply', (e) => {
                    const index = $('#payment-discounts-modal').data('item-index');
                    const item = this.items[index];
                    item.discounts = e.detail.discounts;
                    var sale_price = item.base_price;
                    if (item.discounts) {
                        sale_price = item.discounts.reduce((acc, discount) => acc - discount.amount, item.base_price);
                    }
                    item.debit = sale_price;
                    this.renderTableBody();
                    this.updatePaymentTotals();
                });
            }

            // Removal
            this.container.on('click', '.remove-order-item-btn', (e) => {
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

            // House Credit
            this.container.on('input', `#${this.getId('house_credit_apply')}`, (e) => {
                this.setHouseCreditApplied(USCTDP_Admin.safeParseFloat(e.currentTarget.value));
            });

            this.container.on('blur', `#${this.getId('house_credit_apply')}`, (e) => {
                e.currentTarget.value = this.houseCreditApplied.toFixed(2);
            });

            this.container.on('click', '.house-credit-max-btn', () => {
                this.setHouseCreditApplied(this.houseCreditAvailable);
            });

            // Submission
            $(`#${this.getId('submit-payment-form')}`).on('submit', (e) => this.handleFormSubmit(e));
        }

        clear() {
            this.items = [];
            this.houseCreditAvailable = 0;
            this.houseCreditApplied = 0;
            this.renderTableBody();
            this.updateHouseCreditUI();
            this.trigger('cart:empty', {});
        }

        isAdditionalDay(student_id, session_id, product_id) {
            return this.items.some(item =>
                item.student_id == student_id &&
                item.session_id == session_id &&
                item.product_id == product_id);
        }

        addNewRegistration(data, base_price, discounts, additional_day_discount) {
            const isDuplicate = this.items.some(item =>
                item.student_id === data.student_id &&
                item.session_id === data.session_id &&
                item.activity_id === data.activity_id);
            if (isDuplicate) {
                return { success: false, error: 'DUPLICATE_ITEM', message: "Item already in cart." };
            }
            data.type = 'registration';
            data.base_price = base_price;
            data.discounts = discounts;
            data.additional_day_discount = additional_day_discount;
            var sale_price = base_price;
            if (discounts) {
                sale_price = discounts.reduce((acc, discount) => acc - discount.amount, base_price);
            }
            return this.addItem(data, sale_price, 0);
        }

        addExistingRegistration(data) {
            const isDuplicate = this.items.some(item =>
                item.registration_id === data.registration_id);
            if (isDuplicate) {
                return { success: false, error: 'DUPLICATE_ITEM', message: "Item already in cart." };
            }
            const fees = USCTDP_Admin.safeParseFloat(data.total_fees);
            const adjustments = USCTDP_Admin.safeParseFloat(data.total_adjustments);
            const payments = USCTDP_Admin.safeParseFloat(data.total_payments);
            const refunds = USCTDP_Admin.safeParseFloat(data.total_refunds);
            const houseCredits = USCTDP_Admin.safeParseFloat(data.total_house_credits);
            var netFees = fees - adjustments;
            var netPayments = payments - refunds - houseCredits;
            var outstanding = netFees - netPayments;
            data.type = 'registration';
            data.base_price = outstanding;
            return this.addItem(data, outstanding, 0);
        }

        addNewMerchandise(data, price) {
            data.type = 'merchandise';
            data.base_price = price;
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
            const isFirstItem = this.items.length === 0;
            this.items.push(item);
            this.renderTableBody();
            this.updatePaymentTotals();
            this.trigger('cart:add', { item });
            if (isFirstItem) {
                this.fetchHouseCredit(item.family_id);
            }
            return { success: true };
        }

        getOrderData() {
            const method = $('#' + this.getId('payment_method')).val();
            return {
                family_id: this.items[0]?.family_id,
                payment_method: method,
                total_debit: this.items.reduce((s, i) => s + i.debit, 0),
                total_credit: this.items.reduce((s, i) => s + i.credit, 0),
                house_credit_applied: this.houseCreditApplied,
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

        async submitPayment(orderData) {
            const { paymentMode = "update" } = this.settings;
            try {
                const response = await USCTDP_Admin.ajax_submitPayment({
                    ...orderData,
                    payment_mode: paymentMode
                });

                if (paymentMode === "create") {
                    for (const lineItem of orderData.line_items) {
                        const created = response.purchases[lineItem.line_item_id];
                        if (created) {
                            lineItem.purchase_id = created.purchase_id;
                            if (lineItem.type === "registration") {
                                lineItem.registration_id = created.registration_id;
                            }
                        } else {
                            console.log("Line item id " + lineItem.line_item_id + " not found in created purchases.");
                        }
                    }
                }

                return response;
            } catch (error) {
                console.error('Submission failed:', error);
                throw error;
            }
        }

        getMaxApplicableHouseCredit() {
            const credit = this.items.reduce((s, i) => s + i.credit, 0);
            return Math.max(0, Math.min(this.houseCreditAvailable, credit));
        }

        setHouseCreditApplied(amount) {
            const clamped = Math.min(Math.max(USCTDP_Admin.safeParseFloat(amount), 0), this.getMaxApplicableHouseCredit());
            this.houseCreditApplied = clamped;
            this.updatePaymentTotals();
        }

        async fetchHouseCredit(familyId) {
            if (!familyId) {
                this.houseCreditAvailable = 0;
                this.updateHouseCreditUI();
                return;
            }
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.get_family_balance_action,
                        security: usctdp_mgmt_admin.get_family_balance_nonce,
                        family_id: familyId
                    }
                });
                this.houseCreditAvailable = response.success
                    ? USCTDP_Admin.safeParseFloat(response.data.house_credit)
                    : 0;
            } catch (error) {
                console.error('Failed to load house credit balance:', error);
                this.houseCreditAvailable = 0;
            }
            this.updateHouseCreditUI();
        }

        updateHouseCreditUI() {
            const hasHouseCredit = this.houseCreditAvailable > 0;
            const $section = this.container.find('.house-credit-section');
            $section.toggleClass('hidden', this.items.length === 0 || !hasHouseCredit);
            this.container.find('.house-credit-available-value').text(USCTDP_Admin.formatUsd(this.houseCreditAvailable));
            this.container.find(`#${this.getId('house_credit_apply')}`).prop('disabled', this.houseCreditAvailable <= 0);
            this.container.find(`#${this.getId('payment_method')} option[value='house_credit_only']`).prop('hidden', !hasHouseCredit);
            this.updatePaymentTotals();
        }

        updatePaymentTotals() {
            const debit = this.items.reduce((s, i) => s + i.debit, 0);
            const credit = this.items.reduce((s, i) => s + i.credit, 0);
            const balance = debit - credit;
            this.container.find(".debit-summary .total").text(USCTDP_Admin.formatUsd(debit));
            this.container.find(".credit-summary .total").text(USCTDP_Admin.formatUsd(credit));
            this.container.find(".balance-summary .total").text(USCTDP_Admin.formatUsd(balance));
            this.container.find(".house-credit-apply-field input").val(this.houseCreditApplied.toFixed(2));
            this.updatePaymentMethodConstraints(credit, this.houseCreditApplied, balance);
        }

        updatePaymentMethodConstraints(creditTotal, houseCredit, balance) {
            const $method = $('#' + this.getId('payment_method'));
            const selectedVal = $method.val();
            const hasPayment = creditTotal > 0;
            const coveredByHouseCredit = hasPayment && this.houseCreditApplied >= creditTotal;
            const $paymentNote = this.container.find(".payment-method-note span");

            $method.find("option").prop('disabled', !hasPayment);
            $method.find("option[value='pay_later']").prop('disabled', hasPayment);
            $method.find("option[value='house_credit_only']").prop('disabled', !coveredByHouseCredit);

            if (hasPayment) {
                $paymentNote.text("");
                if (coveredByHouseCredit) {
                    $method.val('house_credit_only').trigger('change');
                    $paymentNote.text("The payment is fully covered by house credit. No additional payment is required.");
                } else if (selectedVal === 'pay_later' || selectedVal === 'house_credit_only') {
                    $method.val('').trigger('change');
                }
            } else if (balance <= 0) {
                if (this.settings.allowPayLater) {
                    $paymentNote.text("Payment amount is currently zero, 'Pay Later' must be selected.");
                    $method.val('pay_later').trigger('change');
                } else {
                    $paymentNote.text("Payment balance is currently zero.");
                    $method.val('').trigger('change');
                }
            } else {
                $paymentNote.text("Please input a payment amount above, or apply enough house credit, to proceed.");
                $method.val('').trigger('change');
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
                        <span class="price-value debit-value">${USCTDP_Admin.formatUsd(debit)}</span>
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
                        <div class="cart-actions flex-row gap-5">
                            ${this.settings.manageDiscounts ? '<button class="button discounts-btn">Discounts</button>' : ''}
                            <button class="remove-order-item-btn usctdp-remove-btn">&times;</button>
                        </div>
                    </td>
                </tr>`
        }
    }

    USCTDP_Admin.ManageDiscountsModal = class {
        constructor(containerId, settings) {
            this.container = $(`#${containerId}`);
            this.settings = settings ?? {};
            this.discounts = [];
            this.basePrice = 0;
            this.additionalDayDiscount = 0;
            this.init();
        }

        trigger(eventName, detail = {}) {
            const event = new CustomEvent(`discounts:${eventName}`, {
                detail: { ...detail, manager: this },
                bubbles: true
            });
            this.container[0].dispatchEvent(event);
        }

        init() {
            this.renderLayout();
            this.modal = this.container.find('#discount-modal')[0];
            this.form = this.container.find('#discount-form');
            this.list = this.container.find('#active-discounts-list');
            this.bindEvents();
        }

        show(basePrice, additionalDayDiscount, existingDiscounts = []) {
            this.basePrice = basePrice;
            this.additionalDayDiscount = additionalDayDiscount;
            this.discounts = [...existingDiscounts];
            this.updateUI();
            this.modal.showModal();
            this.trigger('opened');
        }

        renderLayout() {
            const supportedDiscounts = this.settings.supportedDiscounts ? this.settings.supportedDiscounts : [
                { code: 'additional_day', label: 'Addl. Day' },
                { code: 'sibling_10', label: 'Sibling (10%)' },
                { code: 'sibling_20', label: 'Sibling (20%)' },
                { code: 'custom_flat', label: 'Custom Flat' },
                { code: 'custom_percent', label: 'Custom Percent' }
            ];
            const discountOptions = supportedDiscounts.map(discount => `
                <option value="${discount.code}">
                    ${discount.label}
                </option>
            `).join('');
            const html = `
                <dialog id="discount-modal">
                    <h2>Manage Discounts</h2>
                    <div id="active-discounts-list"></div>
                    <form id="discount-form">
                        <div id="discount-type-wrap">
                            <select id="discount-type" required>
                                <option value="">Select Discount...</option>
                                ${discountOptions}
                            </select>
                        </div>
                        <div id="discount-input-wrap" class="modal_field_group hidden">
                            <div id="discount-value-wrap" class="modal_field">
                                <label for="discount-value">Value</label>
                                <input type="number" id="discount-value" step="0.01" min="0">
                            </div>
                            <div id="discount-reason-wrap" class="modal_field">
                                <label for="discount-reason">Reason</label>
                                <input type="text" id="discount-reason">
                            </div>
                        </div>
                        <div id="discount-add-btn-wrap">
                            <button type="submit" class="button button-secondary add-discount-btn">
                                Add Discount
                            </button>
                        </div>
                        <div id="price-summary" class="price-summary">
                            <div class="summary-line">Base Price: <span id="base-price-display"></span></div>
                            <div class="summary-line summary-discount">Discounts: <span id="total-savings-display"></span></div>
                            <div class="summary-line total">Final Price: <span id="final-price-display"></span></div>
                        </div>
                    </form>

                    <div class="actions-footer">
                        <button type="button" class="button button-primary apply-discounts-btn">Apply Discounts</button>
                        <button type="button" class="button button-secondary cancel-btn">Cancel</button>
                    </div>
                </dialog>
            `;
            this.container.append(html);
        }

        bindEvents() {
            this.form.on('submit', (e) => {
                e.preventDefault();
                const code = this.container.find('#discount-type').val();
                const value = USCTDP_Admin.safeParseFloat(this.container.find('#discount-value').val());
                const reason = this.container.find('#discount-reason').val();
                if (code === 'additional_day') {
                    const discount = new USCTDP_Admin.AdditionalDayDiscount(this.additionalDayDiscount);
                    this.addDiscount(discount);
                } else if (code === 'sibling_10') {
                    const discount = new USCTDP_Admin.SiblingDiscount(10);
                    this.addDiscount(discount);
                } else if (code === 'sibling_20') {
                    const discount = new USCTDP_Admin.SiblingDiscount(20);
                    this.addDiscount(discount);
                } else if (code === 'custom_flat') {
                    const discount = new USCTDP_Admin.CustomFlatDiscount(value, reason);
                    this.addDiscount(discount);
                } else if (code === 'custom_percent') {
                    const discount = new USCTDP_Admin.CustomPercentDiscount(value, reason);
                    this.addDiscount(discount);
                }
                const $discountInputWrap = this.container.find('#discount-input-wrap');
                $discountInputWrap.addClass('hidden');
                $discountInputWrap.find('select').val('');
                $discountInputWrap.find('input').val('');
                $discountInputWrap.find('input').prop('required', false);
                this.form[0].reset();
            });

            this.container.find('.apply-discounts-btn').on('click', () => {
                this.trigger('apply', { discounts: this.discounts });
                this.modal.close();
            });

            // Handle Remove Buttons (Delegated)
            this.list.on('click', '.remove-discount-btn', (e) => {
                const index = $(e.currentTarget).data('index');
                this.removeDiscount(index);
            });

            // Close button
            this.container.find('.cancel-btn').on('click', () => {
                this.modal.close();
                this.trigger('closed');
            });

            this.container.find('#discount-type').on('change', (e) => {
                const code = e.target.value;
                const $discountValue = this.container.find('#discount-value');
                const $discountInputWrap = this.container.find('#discount-input-wrap');
                const $discountValueLabel = this.container.find('#discount-value-label');
                if (code === 'custom_flat') {
                    $discountValueLabel.text('Amount');
                    $discountInputWrap.removeClass('hidden');
                    $discountInputWrap.find("input").prop("required", true);
                    $discountValue.focus();
                } else if (code === 'custom_percent') {
                    $discountValueLabel.text('Percent');
                    $discountInputWrap.removeClass('hidden');
                    $discountInputWrap.find("input").prop("required", true);
                    $discountValue.focus();
                } else {
                    $discountInputWrap.addClass('hidden');
                    $discountInputWrap.find("input").prop("required", false);
                }
            });
        }

        addDiscount(discount) {
            const amount = discount.amount(this.basePrice);
            this.discounts.push({
                code: discount.code,
                value: discount.value,
                amount: amount,
                reason: discount.reason
            });
            this.updateUI();
            this.trigger('added', { discount });
        }

        removeDiscount(index) {
            const removed = this.discounts.splice(index, 1);
            this.updateUI();
            this.trigger('removed', { discount: removed[0] });
        }

        updateUI() {
            const $addlDayAmount = this.container.find('#addl-day-amount');
            $addlDayAmount.text(USCTDP_Admin.formatUsd(this.additionalDayDiscount));

            this.totalSavings = 0;
            this.discounts.forEach(d => {
                this.totalSavings += d.amount;
            });

            this.salePrice = Math.max(0, this.basePrice - this.totalSavings);
            this.list.empty();
            if (this.discounts.length === 0) {
                this.list.append('<p class="empty-msg">No active discounts.</p>');
            } else {
                this.discounts.forEach((d, idx) => {
                    let label = '';
                    const displayVal = USCTDP_Admin.formatUsd(d.amount);
                    if (d.code === 'second_day') {
                        label = 'Second Day';
                    } else if (d.code === 'early_signup') {
                        label = 'Early Signup';
                    } else if (d.code === 'with_clinic') {
                        label = 'With Clinic';
                    } else if (d.code === 'sibling_10') {
                        label = 'Sibling 10% (rounded)';
                    } else if (d.code === 'sibling_20') {
                        label = 'Sibling 20% (rounded)';
                    } else if (d.code === 'custom_flat') {
                        label = `Custom Flat`;
                    } else if (d.code === 'custom_percent') {
                        label = `Custom ${d.value}% (rounded)`;
                    }
                    this.list.append(`
                        <div class="discount-item flex-row gap-10">
                            <span><strong>${label}:</strong> ${displayVal}</span>
                            <button type="button" class="remove-discount-btn usctdp-remove-btn" data-index="${idx}">&times;</button>
                        </div>
                    `);
                });
            }
            this.container.find('#base-price-display').text(USCTDP_Admin.formatUsd(this.basePrice));
            this.container.find('#total-savings-display').text(`-${USCTDP_Admin.formatUsd(this.totalSavings)}`);
            this.container.find('#final-price-display').text(USCTDP_Admin.formatUsd(this.salePrice));
            this.trigger('updated', { totalSavings: this.totalSavings, salePrice: this.salePrice });
        }
    }

    USCTDP_Admin.PaymentHistoryModal = class {
        constructor(containerId) {
            this.container = $(`#${containerId}`);
            this.purchaseId = null;
            this.account = null;
            this.familyId = null;
            this.init();
        }

        init() {
            this.renderLayout();
            this.modal = this.container.find('#payment-history-modal')[0];
            this.initTable();
            this.bindEvents();
        }

        renderLayout() {
            const html = `
                <dialog id="payment-history-modal">
                    <h2>Payment History</h2>
                    <div class="usctdp-ledger-summary">
                        <div class="summary-group">
                            <span class="summary-label">Status</span>
                            <div id="ledger-status-text" class="summary-value status-pending">Loading...</div>
                        </div>
                        <div class="summary-group text-right">
                            <span class="summary-label">Balance</span>
                            <div id="ledger-total-balance" class="summary-value balance-amount">$0.00</div>
                        </div>
                    </div>
                    <div id="payment-history-table-wrap">
                        <table id="payment-history-table" class="usctdp-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Event</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody id="payment-history-table-body"></tbody>
                        </table>
                    </div>
                    <div class="actions-footer">
                        <button type="button" class="button button-primary" id="generate-statement-btn">Print Statement</button>
                        <button type="button" class="button button-secondary" id="close-payment-history-modal">Close</button>
                    </div>
                </dialog>`;
            this.container.append(html);
        }

        initTable() {
            this.table = this.container.find('#payment-history-table').DataTable({
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
                    data: (d) => {
                        d.action = usctdp_mgmt_admin.ledger_events_datatable_action;
                        d.security = usctdp_mgmt_admin.ledger_events_datatable_nonce;
                        d.purchase_id = this.purchaseId;
                        d.account = this.account;
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
                    beforeSend: () => {
                        if (!this.purchaseId) {
                            return false; // This cancels the Ajax request
                        }
                    },
                },
                columns: [
                    {
                        data: 'event_date',
                        render: function (data, type, row, meta) {
                            return new Date(data).toLocaleString();
                        }
                    },
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
                drawCallback: (settings) => {
                    var api = new $.fn.dataTable.Api(settings);
                    var totalBalance = 0;
                    api.rows().every(function () {
                        var d = this.data();
                        totalBalance += (USCTDP_Admin.safeParseFloat(d.charge_amount) - USCTDP_Admin.safeParseFloat(d.payment_amount));
                    });

                    this.container.find('#ledger-total-balance').text(USCTDP_Admin.formatUsd(totalBalance));

                    if (totalBalance <= 0) {
                        this.container.find('#ledger-status-text').text('PAID').css('color', '#00a32a');
                    } else {
                        this.container.find('#ledger-status-text').text('BALANCE DUE').css('color', '#d63638');
                    }
                }
            });
        }

        bindEvents() {
            this.container.on('click', '#close-payment-history-modal', () => {
                this.modal.close();
            });

            this.container.on('click', '#generate-statement-btn', () => {
                const $btn = this.container.find('#generate-statement-btn');
                $btn.prop('disabled', true);
                $btn.html('<span class="spinner is-active"></span> Generating...');
                USCTDP_Admin.ajax_generateStatement(this.familyId, [this.purchaseId])
                    .then((response) => {
                        window.open(response.doc_url, '_blank');
                    })
                    .catch((error) => {
                        this.modal.close();
                        window.Swal.fire({
                            title: "Error",
                            text: "Failed to generate statement. Inform a developer.",
                            icon: "error"
                        });
                    })
                    .finally(() => {
                        $btn.prop('disabled', false);
                        $btn.html('Print Statement');
                    });
            });
        }

        show(purchaseId, account, familyId) {
            this.purchaseId = purchaseId;
            this.account = account;
            this.familyId = familyId;
            this.table.ajax.reload();
            this.modal.showModal();
        }
    }
})(jQuery);
