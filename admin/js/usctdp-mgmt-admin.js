(function($) {
    window.USCTDP_Admin = window.USCTDP_Admin || {};

    USCTDP_Admin.displayTime = function(dateObj) {
        const options = {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };
        return new Intl.DateTimeFormat('en-US', options).format(dateObj);
    }

    USCTDP_Admin.applyReplacements = function(input, replacements) {
        return replacements.reduce((currentString, [pattern, replacement]) => {
            return currentString.replace(pattern, replacement);
        }, input);
    }

    USCTDP_Admin.formatUsd = function(amount) {
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
            action=usctdp_mgmt_admin.select2_search_action,
            nonce=usctdp_mgmt_admin.select2_search_nonce,
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

        init() {
            this.renderTable();

            this.container.on('change', '.price-input', (event) => {
                this.updateRegistrationTotal();
            });

            this.container.on('click', '.checkout-btn', (event) => {
                event.preventDefault();
                this.container.find('.checkout-section').removeClass('hidden');
            });

            this.container.on('change', `#${this.getId('payment_method')}`, (event) => {
                const value = event.currentTarget.value;
                this.container.find(".payment-option").addClass('hidden');
                this.container.find('.submit-payment-wrap').toggleClass('hidden', value === "");

                if (value === 'check') {
                    this.container.find('.check-fields').removeClass('hidden');
                } 
            });

            this.container.on('click', '.remove-btn', (e) => {
                e.preventDefault();
                const $row = $(e.currentTarget).closest('tr');
                $row.remove();
                this.updateRegistrationTotal();
            });
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
                checkoutButton = false
            } = this.settings; 
            const checkoutButtonHtml = checkoutButton
                        ? '<button class="checkout-btn button button-primary">Checkout</button>'
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
                        <div class="order-summary credit-summary">
                            <span class="label">Current</br>Balance</span>
                            <span class="total"></span>
                        </div>
                        <div class="order-summary debit-summary">
                            <span class="label">Total</br>Payment</span>
                            <span class="total"></span>
                        </div>
                        <div class="order-summary balance-summary">
                            <span class="label">Updated</br>Balance</span>
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
                        <div class="submit-payment-wrap hidden">
                            <form id="${this.getId('submit-payment-form')}">
                                <input type="hidden" name="action" value="<?php echo esc_attr($submit_hook); ?>">
                                <input type="hidden" id="${this.getId('submit_user_id')}" name="user_id" value="">
                                <input type="hidden" id="${this.getId('submit_family_id')}" name="family_id" value="">
                                <input type="hidden" id="${this.getId('submit_payment_url')}" name="payment_url" value="">
                                <input type="hidden" id="${this.getId('submit_order_url')}" name="order_url" value="">
                                <input type="hidden" id="${this.getId('submit_pay_now')}" name="pay_now" value="">
                                <input type="hidden" id="${this.getId('registrations')}" name="registrations" value="">
                                <?php wp_nonce_field($nonce_action, $nonce_name);?>
                                <div class"submit-payment-button-wrap">
                                    <button class="button button-primary" id="${this.getId('registration-payment')}">
                                        ${submitButtonText}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>`;
            this.container.append(html);
        }

        updateRegistrationTotal() {
            const $rows = this.container.find('table tbody tr');
            let debit_total = 0;
            let credit_total = 0;
            $rows.each(function () {
                const $row = $(this);
                console.log($row);
                debit_total += parseFloat($row.find('.debit-input').val());
                credit_total += parseFloat($row.find('.credit-input').val());
            });
            this.container.find(".credit-summary .total")
                .text(USCTDP_Admin.formatUsd(debit_total));
            this.container.find(".debit-summary .total")
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
                        <input class="price-input debit-input" name="debit" value="${debit}">
                    </td>
                    <td class="cart-credit"> 
                        <input class="price-input credit-input" name="credit" value="${credit}">
                    </td>
                    <td>
                        <button class="button remove-btn">Remove</button> 
                    </td>
                </tr>`
        }

        addRegistration(registration) {
            const debit = registration.registration_debit;
            const credit = registration.registration_credit;
            var outstanding = debit - credit;
            var $row = $(this.addOrderRow({
                student: registration.student_first + ' ' + registration.student_last,
                session: registration.session_name,
                item: registration.activity_name,
                debit: outstanding,
                credit: outstanding
            }));
            
            $row.data('student_id', registration.student_id)
                .data('session_id', registration.session_id)
                .data('activity_id', registration.activity_id)
                .data('product_id', registration.product_id)
                .data('student_level', registration.student_level)
                .data('family_id', registration.family_id)
                .data('notes', registration.notes)
                .data('type', 'registration');
            this.container.find('table tbody').append($row);

            this.updateRegistrationTotal();
        }
    };
})(jQuery);
