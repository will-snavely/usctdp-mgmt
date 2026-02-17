(function ($) {
    "use strict";

    $(document).ready(function () {
        const activityTypes = {
            1: { name: "Clinic", id: "clinic" },
            2: { name: "Tournament", id: "tournament" },
            3: { name: "Camp", id: "camp" }
        };

        var pendingRegistrations = [];

        function createSelector(id, name, label, hidden, disabled, options = []) {
            var classes = 'context-selector-section';
            if (hidden) {
                classes += ' hidden';
            }
            var optionsHtml = '';
            for (const option of options) {
                if ('id' in option && 'name' in option) {
                    optionsHtml += `<option value="${option.id}">${option.name}</option>`;
                } else {
                    optionsHtml += '<option></option>';
                }
            }
            return `
                <div id="${id}-section" class="${classes}">
                    <span id="${id}-label" class="context-selector-label"> ${label} </span>
                    <select id="${id}" name="${name}" class="context-selector" ${disabled ? 'disabled' : ''}>
                        ${optionsHtml}
                    </select>
                </div>`;
        }

        function defaultSelect2Options(placeholder, action, nonce, filter = function () { return {} }) {
            return {
                placeholder: placeholder,
                allowClear: true,
                ajax: {
                    url: usctdp_mgmt_admin.ajax_url,
                    data: function (params) {
                        return {
                            q: params.term,
                            action: action,
                            security: nonce,
                            ...filter()
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.items
                        };
                    }
                }
            }
        }

        var contextData = {};
        var preloadedData = { student: null, activity: null };
        if (usctdp_mgmt_admin.preload) {
            if (usctdp_mgmt_admin.preload.student_id) {
                preloadedData.student = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                contextData['family-selector'] = preloadedData.student.family_id;
                contextData['student-selector'] = preloadedData.student.student_id;
            }
            if (usctdp_mgmt_admin.preload.activity_id) {
                console.log(preloadedData.activity);
                preloadedData.activity = Object.values(usctdp_mgmt_admin.preload.activity_id)[0];
                const activityTypeInt = preloadedData.activity.activity_type;
                const activityType = activityTypes[activityTypeInt].id;
                contextData['session-selector'] = preloadedData.activity.session_id;
                if (activityType === 'clinic') {
                    contextData['clinic-selector'] = preloadedData.activity.activity_id;
                }
            }
        }

        var contextSelectors = {
            'family-selector': {
                selector: function () {
                    var options = [];
                    var hidden = false;
                    var disabled = false;
                    if (preloadedData.student) {
                        options.push({
                            id: preloadedData.student.family_id,
                            name: preloadedData.student.family_name
                        });
                        disabled = true;
                    }

                    return $(createSelector(
                        'family-selector',
                        'family_id',
                        'Family',
                        hidden,
                        disabled,
                        options
                    ));
                },
                nextSelector: {
                    options: ['student-selector'],
                    choose: function () {
                        return 'student-selector';
                    },
                },
                select2Options: function () {
                    if (preloadedData.student) {
                        return {
                            placeholder: "Select a family...",
                            allowClear: true
                        };
                    } else {
                        return defaultSelect2Options(
                            "Search for a family...",
                            usctdp_mgmt_admin.select2_family_search_action,
                            usctdp_mgmt_admin.select2_family_search_nonce
                        );
                    }
                },
            },
            'student-selector': {
                selector: function () {
                    var options = [];
                    var hidden = true;
                    var disabled = false;
                    if (preloadedData.student) {
                        options.push({
                            id: preloadedData.student.student_id,
                            name: preloadedData.student.student_name
                        });
                        hidden = false;
                        disabled = true;
                    }

                    return $(createSelector(
                        'student-selector',
                        'student_id',
                        'Student',
                        hidden,
                        disabled,
                        options
                    ));
                },
                nextSelector: {
                    options: ['session-selector'],
                    choose: function () {
                        return 'session-selector';
                    },
                },
                select2Options: function () {
                    if (preloadedData.student) {
                        return {
                            placeholder: "Select a student...",
                            allowClear: true
                        };
                    } else {
                        return defaultSelect2Options(
                            "Search for a student...",
                            usctdp_mgmt_admin.select2_student_search_action,
                            usctdp_mgmt_admin.select2_student_search_nonce,
                            function () {
                                return {
                                    family_id: $('#family-selector').val()
                                };
                            }
                        );
                    }
                }
            },
            'session-selector': {
                selector: function () {
                    var options = [];
                    var hidden = true;
                    var disabled = false;
                    if (preloadedData.activity) {
                        options.push({
                            id: preloadedData.activity.session_id,
                            name: preloadedData.activity.session_name
                        });
                        hidden = false;
                        disabled = true;
                    }
                    return $(createSelector(
                        'session-selector',
                        'session_id',
                        'Session',
                        hidden,
                        disabled,
                        options
                    ));
                },
                nextSelector: {
                    options: ['clinic-selector', 'tournament-selector'],
                    choose: function () {
                        return 'clinic-selector'
                    }
                },
                select2Options: function () {
                    if (preloadedData.activity) {
                        return {
                            placeholder: "Select a session...",
                            allowClear: true
                        };
                    } else {
                        return defaultSelect2Options(
                            "Search for a session...",
                            usctdp_mgmt_admin.select2_session_search_action,
                            usctdp_mgmt_admin.select2_session_search_nonce
                        );
                    }
                },
            },
            'clinic-selector': {
                selector: function () {
                    var options = [];
                    var hidden = true;
                    var disabled = false;
                    if (preloadedData.activity) {
                        options.push({
                            id: preloadedData.activity.activity_id,
                            name: preloadedData.activity.activity_name
                        });
                        hidden = false;
                        disabled = true;
                    }
                    return $(createSelector(
                        'clinic-selector',
                        'activity_id',
                        'Clinic',
                        hidden,
                        disabled,
                        options
                    ));
                },
                nextSelector: {
                    options: [],
                    choose: function () {
                        return null;
                    }
                },
                select2Options: function () {
                    if (preloadedData.activity) {
                        return {
                            placeholder: "Search for an activity...",
                            allowClear: true
                        };
                    } else {
                        return defaultSelect2Options(
                            "Search for an activity...",
                            usctdp_mgmt_admin.select2_activity_search_action,
                            usctdp_mgmt_admin.select2_activity_search_nonce,
                            function () {
                                return {
                                    session_id: $('#session-selector').val()
                                }
                            }
                        );
                    }
                }
            }
        }

        for (const [key, value] of Object.entries(contextSelectors)) {
            var $selector = value.selector();
            $selector.appendTo('#context-selectors');
            if (value.select2Options) {
                $(`#${key}`).select2(value.select2Options());
            }
            if ($(`#${key}`).prop('disabled')) {
                continue;
            }

            $(`#${key}`).on('change', function () {
                const selectedValue = this.value;
                const nextSelector = value.nextSelector.choose();
                const $nextSection = $(`#${nextSelector}-section`);
                contextData[key] = selectedValue;
                if ($nextSection) {
                    if (selectedValue === '') {
                        for (const option of value.nextSelector.options) {
                            if ($(`#${option}`).prop('disabled')) {
                                continue;
                            }
                            $(`#${option}-section`).addClass('hidden');
                        }
                    } else {
                        $nextSection.removeClass('hidden');
                    }
                }
                for (const option of value.nextSelector.options) {
                    if ($(`#${option}`).prop('disabled')) {
                        continue;
                    }
                    $(`#${option}`).val(null);
                    $(`#${option}`).trigger('change');
                }

                if (contextData['clinic-selector'] && contextData['student-selector']) {
                    toggle_registration_fields(false);
                    $('#notifications-section').children().remove();
                    load_clinic_registration(contextData['clinic-selector'], contextData['student-selector']);
                } else {
                    $('#notifications-section').children().remove();
                    toggle_registration_fields(false);
                }
            });
        }

        function toggle_registration_fields(visible) {
            if (visible) {
                $('#additional-details').removeClass('hidden');
            } else {
                $('#additional-details').addClass('hidden');
            }
        }

        function reset_registration_fields() {
            $('.registration-field input').val('');
            $('.registration-field select').val(null);
            $('#notes').val('');
            $('#notifications-section').children().remove();
        }

        function set_notification(slug, message, ignoreable = false) {
            var $notification = $("<div></div>");
            $notification.addClass('notification');
            var $message = $("<p></p>");
            $message.text(message);
            $notification.append($message);
            if (ignoreable) {
                var $ignoreBtn = $("<a></a>");
                $ignoreBtn.attr('href', 'javascript:void(0);');
                $ignoreBtn.attr('id', 'ignore-notification');
                $ignoreBtn.addClass('ignore-notification');
                $ignoreBtn.addClass('button');
                $ignoreBtn.text('Proceed Anyway');
                $notification.append($ignoreBtn);

                $ignoreBtn.click(function () {
                    reset_registration_fields();
                    toggle_registration_fields(true);
                    var $hiddenInput = $('<input type="hidden"></input>');
                    $hiddenInput.attr('id', 'ignore-' + slug);
                    $hiddenInput.attr('name', 'ignore-' + slug);
                    $hiddenInput.attr('value', "true");
                    $('#notifications-section').append($hiddenInput);
                });
            }
            $('#notifications-section').append($notification);
        }

        function formatUsd(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }

        function load_clinic_registration(clinic_id, student_id) {
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.activity_preregistration_action,
                    activity_id: clinic_id,
                    student_id: student_id,
                    security: usctdp_mgmt_admin.activity_preregistration_nonce,
                },
                success: function (response) {
                    var current_size = response.data.registered;
                    var max_size = response.data.capacity;
                    var student_level = response.data.student_level;
                    var pricing = response.data.pricing;
                    var session_id = response.data.session_id;
                    var product_id = response.data.product_id;

                    if (response.data.student_registered) {
                        set_notification(
                            'student-registered',
                            'The selected student is already registered for this class.',
                            false
                        );
                    } else if (current_size >= max_size) {
                        set_notification(
                            'class-full',
                            'This class is currently full.',
                            true
                        );
                    } else {
                        reset_registration_fields();
                        $('#student-level').val(student_level);
                        $('#clinic-current-size').text(current_size);
                        $('#clinic-max-size').text(max_size);
                        $('#clinic-info-capacity .clinic-info-value').removeClass('full available');
                        $('#clinic-info-capacity .clinic-info-value').addClass(current_size >= max_size ? 'full' : 'available');
                        $('#clinic-one-day-price').text(formatUsd(pricing['One']));
                        $('#clinic-two-day-price').text(formatUsd(pricing['Two']));
                        $("#clinic-info").removeData();
                        $("#clinic-info").data('session_id', session_id);
                        $("#clinic-info").data('product_id', product_id);
                        $("#clinic-info").data('student_level', student_level);
                        $("#clinic-info").data('pricing', pricing);
                        toggle_registration_fields(true);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                }
            });
        }

        $('#payment-method').on('change', function () {
            if (this.value === 'check') {
                $('#check-fields').removeClass('hidden');
            } else {
                $('#check-fields').addClass('hidden');
            }
        });

        function clinicPriceEstimate(reg, one_day_price, two_day_price) {
            const $rows = $('#registration-order-table tbody tr');
            const match = $rows.filter(function () {
                const $row = $(this);
                return $row.data('product_id') === reg.product_id &&
                    $row.data('session_id') === reg.session_id &&
                    $row.data('student_id') === reg.student_id;
            });
            return match.length > 0 ? two_day_price : one_day_price;
        }

        function addPendingRegistration(registration, priceEstimate) {
            var $row = $('<tr></tr>');
            $row.data('student_id', registration.student_id)
                .data('session_id', registration.session_id)
                .data('activity_id', registration.activity_id)
                .data('product_id', registration.product_id)
                .data('student_level', registration.student_level)
                .data('family_id', registration.family_id)
                .data('notes', registration.notes);

            $row.append($('<td></td>')
                .addClass('registration-student-name')
                .append($('<span></span>')
                    .text(registration.student_name)));
            $row.append($('<td></td>')
                .addClass('registration-session-name')
                .append($('<span></span>')
                    .text(registration.session_name)));
            $row.append($('<td></td>')
                .addClass('registration-activity-name')
                .append($('<span></span>')
                    .text(registration.activity_name)));
            $row.append($('<td></td>')
                .append($('<input></input>')
                    .addClass('price-input')
                    .attr('name', 'price-input')
                    .on('change', function () {
                        updateRegistrationTotal();
                    })
                    .val(priceEstimate)));

            $row.append($('<td></td>')
                .append($('<button></button>')
                    .text('Remove')
                    .addClass('button')
                    .on('click', function () {
                        $row.remove();
                        if ($('#registration-order-table tbody tr').length === 0) {
                            $('#registration-order-section').addClass('hidden');
                        }
                    })));
            $('#registration-order-table tbody').append($row);
            $('#registration-order-section').removeClass('hidden');
            updateRegistrationTotal();
        }

        function updateRegistrationTotal() {
            const $rows = $('#registration-order-table tbody tr');
            let total = 0;
            $rows.each(function () {
                const $row = $(this);
                const price = parseFloat($row.find('.price-input').val());
                total += price;
            });
            $('#registration-order-total-value').text(formatUsd(total));
        }

        function getOrderData() {
            const $rows = $('#registration-order-table tbody tr');
            const orderData = [];
            $rows.each(function () {
                const $row = $(this);
                const registration = {
                    student_id: $row.data('student_id'),
                    session_id: $row.data('session_id'),
                    activity_id: $row.data('activity_id'),
                    product_id: $row.data('product_id'),
                    student_level: $row.data('student_level'),
                    family_id: $row.data('family_id'),
                    notes: $row.data('notes'),
                    credit: 0,
                    debit: parseFloat($row.find('.price-input').val())
                };
                orderData.push(registration);
            });
            return orderData;
        }

        async function createRegistrations(orderData) {
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.commit_registrations_action,
                        security: usctdp_mgmt_admin.commit_registrations_nonce,
                        registration_data: orderData,
                    }
                });
                if (response.success) {
                    return response.data;
                } else {
                    throw new Error(response.data || 'PHP logic error');
                }

            } catch (error) {
                console.error('Registration Commit Failed:', error.statusText || error.message);
                throw error;
            }
        }

        async function createWooCommerceOrder(orderData) {
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.create_woocommerce_order_action,
                        security: usctdp_mgmt_admin.create_woocommerce_order_nonce,
                        order_data: orderData,
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

        async function submitRegistrations(orderData) {
            try {
                await createRegistrations(orderData);
                return await createWooCommerceOrder(orderData);
            } catch (error) {
                console.error('Sequence failed:', error);
                throw error;
            }
        }

        $('#add-registration').on('click', function () {
            const newRegistration = {
                activity_id: $('#clinic-selector').val(),
                activity_name: $('#clinic-selector option:selected').text(),
                family_id: $('#family-selector').val(),
                student_id: $('#student-selector').val(),
                student_name: $('#student-selector option:selected').text(),
                session_id: $('#clinic-info').data('session_id'),
                session_name: $('#session-selector option:selected').text(),
                product_id: $('#clinic-info').data('product_id'),
                student_level: $('#clinic-info').data('student_level'),
                notes: $('#notes').val()
            };
            const one_day_price = parseInt($('#clinic-info').data('pricing')['One']);
            const two_day_price = parseInt($('#clinic-info').data('pricing')['Two']);
            const diff = two_day_price - one_day_price;
            const priceEstimate = clinicPriceEstimate(
                newRegistration,
                one_day_price,
                diff
            );
            addPendingRegistration(newRegistration, priceEstimate);
            reset_registration_fields();
            toggle_registration_fields(false);
            $('#clinic-selector').val(null).trigger('change');
        });

        $('#registration-checkout').on('click', function () {
            $('#registration-checkout-section').removeClass('hidden');
            $('#registration-order-info input').prop('disabled', true);
            $('#registration-order-info button').prop('disabled', true);
            $('#context-selection').addClass('hidden');
        });

        $('#payment_method').on('change', function () {
            $(".payment-option").addClass('hidden');
            if (this.value !== "") {
                $('#submit-registration-button').removeClass('hidden');
            } else {
                $('#submit-registration-button').addClass('hidden');
            }
            if (this.value === 'check') {
                $('#check-fields').removeClass('hidden');
            } else if (this.value === 'card') {
                $('#card-fields').removeClass('hidden');
            }
        });

        $('#submit-registration-form').on('submit', function (event) {
            event.preventDefault();
            const form = this;
            const $btn = $('#submit-registration-btn');
            $btn.prop('disabled', true).text('Processing...');

            const orderData = getOrderData();
            submitRegistrations(orderData)
                .then(function (response) {
                    const payment_method = $('#payment_method').val();
                    $('#submit_pay_now').val("false");
                    if (payment_method === 'card') {
                        $('#submit_pay_now').val("true");
                    }
                    $('#submit_user_id').val(response.user_id);
                    $('#submit_family_id').val(response.family_id);
                    $('#submit_payment_url').val(response.payment_url);
                    $('#submit_order_url').val(response.order_url);
                    form.submit();
                })
                .catch(function (error) {
                    console.error("Order creation failed:", error);
                    $btn.prop('disabled', false).text('Submit Registration');
                    alert('There was an error. Please try again.');
                });
        });
    });
})(jQuery);
