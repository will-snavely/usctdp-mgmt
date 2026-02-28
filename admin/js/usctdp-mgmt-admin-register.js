(function ($) {
    "use strict";

    $(document).ready(function () {
        const activityTypes = {
            1: { name: "Clinic", id: "clinic" },
            2: { name: "Tournament", id: "tournament" },
            3: { name: "Camp", id: "camp" }
        };

        var pendingRegistrations = [];

        var contextData = {};
        var preloadedData = { student: null, activity: null };
        if (usctdp_mgmt_admin.preload) {
            if (usctdp_mgmt_admin.preload.student_id) {
                preloadedData.student = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                contextData['family-selector'] = preloadedData.student.family_id;
                contextData['student-selector'] = preloadedData.student.student_id;
            }
        }

        function togglePreorderDetails(visible) {
            if (visible) {
                $('#preorder-details').removeClass('hidden');
            } else {
                $('#notifications-section').children().remove();
                $('#preorder-details').addClass('hidden');
            }
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
                    toggleOrderDetails(true);
                });
            }
            $('#notifications-section').append($notification);
        }

        async function getPreregistrationInfo(activity_id, student_id) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.activity_preregistration_action,
                    activity_id: activity_id,
                    student_id: student_id,
                    security: usctdp_mgmt_admin.activity_preregistration_nonce,
                }
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Unknown error');
            }
        }


        function bind_clinic_info(info) {
            const full = info.registered >= info.capacity;
            $('#clinic-current-size').text(info.registered);
            $('#clinic-max-size').text(info.capacity);
            $('#clinic-info-capacity .clinic-info-value').removeClass('full available');
            $('#clinic-info-capacity .clinic-info-value').addClass(full ? 'full' : 'available');
            $('#clinic-one-day-price').text(USCTDP_Admin.formatUsd(info.pricing['One']));
            $('#clinic-two-day-price').text(USCTDP_Admin.formatUsd(info.pricing['Two']));
            $('#student-level').val(info.student_level);
            $("#clinic-info").removeData();
            $("#clinic-info").data('pricing', info.pricing);
        }

        async function loadClinicRegistration(clinicId, studentId) {
            try {
                const info = await getPreregistrationInfo(clinicId, studentId);
                bind_clinic_info(info);
                $("#clinic_notes").val('');
                if (info.student_registered) {
                    set_notification(
                        'student-registered',
                        'The selected student is already registered for this activity.',
                        false
                    );
                } else if (info.registered >= info.capacity) {
                    set_notification(
                        'activity-full',
                        'This activity is currently full.',
                        true
                    );
                } else {
                    togglePreorderDetails(true);
                }
            } catch(error) {
                console.log("Error: ", error);
                alert("Failed to load clinic registration data. Try again or report this to a developer.");
            }
        }

        function loadActivityRegistration(activityId, activityType, studentId) {
            $('#notifications-section').children().remove();
            if(activityType === 1) { // Clinic
                loadClinicRegistration(activityId, studentId);
            }
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
            $('#registration-order-total-value').text(USCTDP_Admin.formatUsd(total));
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

        function checkoutActivityName(name) {
            const replacements = [
                [/^Adult/, ""],
            ];
            return USCTDP_Admin.applyReplacements(name, replacements);
        }

        $('#add-registration').on('click', function () {
            const activityName = $('#activity-selector option:selected').text();
            var displayActivityName = checkoutActivityName(activityName);
            const newRegistration = {
                activity_id: $('#activity-selector').val(),
                activity_name: displayActivityName, 
                family_id: $('#family-selector').val(),
                student_id: $('#student-selector').val(),
                student_name: $('#student-selector option:selected').text(),
                session_id: $('#session-selector').val(),
                session_name: $('#session-selector option:selected').text(),
                product_id: $('#product-selector').val(),
                student_level: $('#student-level').val(),
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
            togglePreorderDetails(false);
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
 
        const selectorConfig = {
            'family-selector': {
                name: 'family_id',
                label: 'Family',
                target: 'family',
                next: 'student-selector',
                isRoot: true
            },
            'student-selector': {
                name: 'student_id',
                label: 'Student',
                target: 'student',
                next: 'product-selector', 
                filter: function () {
                    return {
                        family_id: $('#family-selector').val()
                    };
                }
            },
            'product-selector': {
                name: 'product_id',
                label: 'Product',
                target: 'product',
                branches: ['session-selector'], 
                next: function(val, $el) {
                    return "session-selector";
                }
            },
            'session-selector': {
                name: 'session_id',
                label: 'Session',
                target: 'session',
                next: 'activity-selector', 
                filter: function () {
                    const productData = $("#product-selector").select2('data');
                    if(productData && productData.length > 0) {
                        const selectedProduct = productData[0];
                        return {
                            category: selectedProduct.category
                        };
                    } else {
                        return {};
                    }
                }
            },
            'activity-selector': {
                name: 'activity_id',
                label: 'Activity',
                target: 'activity',
                next: null,
                filter: function () {
                    return {
                        session_id: $('#session-selector').val(),
                        product_id: $('#product-selector').val()
                    };
                }
            },
        };

        const selectHandler = new USCTDP_Admin.CascasdingSelect('context-selectors', selectorConfig);

        $('#context-selectors').on('cascade:change', function (e) {
            const { selectorId, value, state } = e.detail;
            if (selectorId === 'activity-selector') {
                if(value) {
                    const activityId = value;
                    const studentId = $('#student-selector').val()
                    if (activityId && studentId) {
                        const activityData = $("#product-selector").select2('data')[0];
                        const activityType = activityData.type;
                        togglePreorderDetails(false);
                        loadActivityRegistration(activityId, activityType, studentId);
                    } else {
                        togglePreorderDetails(false);
                    }
                } 
            }
        });

        if (usctdp_mgmt_admin.preload?.student_id) {
            const preloadedStudent = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
            preloadedData['family-selector'] = {
                id: preloadedStudent.family_id,
                text: preloadedFamily.family_name,
                disable: true
            }
            preloadedData['student-selector'] = {
                id: preloadedStudent.student_id,
                text: preloadedFamily.student_name,
                disable: false
            }
            $('#student-filter').prop('disabled', true);
            $('#student-filter-section').addClass('hidden');
            $('#context-selectors').addClass('hidden');
            selectHandler.applyData(preloadedData);
        }
   });
})(jQuery);
