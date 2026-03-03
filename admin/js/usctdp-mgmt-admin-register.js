(function ($) {
    "use strict";

    $(document).ready(function () {
        var preloadedData = {};

        function clearNotifications() {
            $('#notifications-section').children().remove();
        }

        function togglePreorderDetails(visible, subtype) {
            if (visible) {
                $('.preorder-subtype').addClass('hidden');
                $('#preorder-details').removeClass('hidden');
                $('#' + subtype).removeClass('hidden');
            } else {
                $('#preorder-details').addClass('hidden');
                $('.preorder-subtype').addClass('hidden');
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
                    togglePreorderDetails(true, "clinic-preorder");
                }
            } catch (error) {
                console.log("Error: ", error);
                alert("Failed to load clinic registration data. Try again or report this to a developer.");
            }
        }

        function loadActivityRegistration(activityId, activityType, studentId) {
            $('#notifications-section').children().remove();
            if (activityType === 1) { // Clinic
                loadClinicRegistration(activityId, studentId);
            }
        }

        function loadEquipmentPurchase() {
            $('#notifications-section').children().remove();
            togglePreorderDetails(true, "equipment-preorder");
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

        function createCartRow(options) {
            const { student, session, item, price } = options;
            return `
                <tr> 
                    <td class="cart-student-name">${student ?? '--'}</td>
                    <td class="cart-session">${session ?? '--'}</td>
                    <td class="cart-item">${item}</td>
                    <td class="cart-price"> 
                        <input class="price-input" name="price" value="${price}">
                    </td>
                    <td>
                        <button class="button remove-btn">Remove</button> 
                    </td>
                </tr>`
        }

        function addEquipment(equipment, price) {
            var $row = $(createCartRow({
                student: equipment.student_name,
                item: equipment.product_name,
                price: price
            }));
            $row.data('product_id', equipment.product_id)
                .data('product_name', equipment.product_name)
                .data('student_name', equipment.student_name)
                .data('student_id', equipment.student_id)
                .data('family_id', equipment.family_id)
                .data('notes', equipment.notes)
                .data('type', 'equipment');
            $('#registration-order-table tbody').append($row);
            $('#registration-order-section').removeClass('hidden');
            updateRegistrationTotal();
        }

        function addPendingRegistration(registration, priceEstimate) {
            var $row = $(createCartRow({
                student: registration.student_name,
                session: registration.session_name,
                item: registration.activity_name,
                price: priceEstimate
            }));
            $row.data('student_id', registration.student_id)
                .data('session_id', registration.session_id)
                .data('activity_id', registration.activity_id)
                .data('product_id', registration.product_id)
                .data('student_level', registration.student_level)
                .data('family_id', registration.family_id)
                .data('notes', registration.notes)
                .data('type', 'registration');
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
                const type = $row.data('type');
                if (type === 'registration') {
                    const registration = {
                        student_id: $row.data('student_id'),
                        session_id: $row.data('session_id'),
                        activity_id: $row.data('activity_id'),
                        product_id: $row.data('product_id'),
                        student_level: $row.data('student_level'),
                        family_id: $row.data('family_id'),
                        notes: $row.data('notes'),
                        credit: 0,
                        debit: parseFloat($row.find('.price-input').val()),
                        type: 'registration'
                    };
                    orderData.push(registration);
                } else if (type === 'equipment') {
                    const equipment = {
                        product_id: $row.data('product_id'),
                        student_id: $row.data('student_id'),
                        family_id: $row.data('family_id'),
                        notes: $row.data('notes'),
                        credit: 0,
                        debit: parseFloat($row.find('.price-input').val()),
                        type: 'equipment'
                    };
                    orderData.push(equipment);
                }
            });
            return orderData;
        }

        async function createRegistrations(orderData, order_id) {
            try {
                const response = await $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: usctdp_mgmt_admin.commit_registrations_action,
                        security: usctdp_mgmt_admin.commit_registrations_nonce,
                        registration_data: orderData.filter(item => item.type === 'registration'),
                        order_id: order_id
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
                const order = await createWooCommerceOrder(orderData);
                const registrations = await createRegistrations(orderData, order.order_id);
                return {
                    order: order,
                    registrations: registrations.registration_ids
                };
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

        $('#add-clinic-registration').on('click', function () {
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
                notes: $('#clinic-notes').val()
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
            clearNotifications();
            togglePreorderDetails(false);
            $('#activity-selector').val(null).trigger('change');
        });

        $('#add-equipment').on('click', function () {
            const equipmentName = $('#product-selector option:selected').text();
            const newEquipment = {
                product_id: $('#product-selector').val(),
                product_name: equipmentName,
                family_id: $('#family-selector').val(),
                student_id: $('#student-selector').val(),
                student_name: $('#student-selector option:selected').text(),
                notes: $('#equipment-notes').val()
            };
            addEquipment(newEquipment, 50);
            clearNotifications();
            togglePreorderDetails(false);
            $('#product-selector').val(null).trigger('change');
        });

        $('#registration-order-table').on('change', '.price-input', function () {
            updateRegistrationTotal();
        });

        $('#registration-order-table').on('click', '.remove-btn', function () {
            const $row = $(this).closest('tr');
            $row.remove()
            if ($('#registration-order-table tbody tr').length === 0) {
                $('#registration-order-section').addClass('hidden');
            }
            updateRegistrationTotal();
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
                    $('#submit_user_id').val(response.order.user_id);
                    $('#submit_family_id').val(response.order.family_id);
                    $('#submit_payment_url').val(response.order.payment_url);
                    $('#submit_order_url').val(response.order.order_url);
                    $('#registrations').val(JSON.stringify(response.registrations));
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
                next: function (val, $el) {
                    // 1 == clinic, 2 == tourney, 3 == camp
                    const activities = new Set([1, 2, 3]);
                    const productData = $el.select2('data');
                    if (productData && productData.length > 0) {
                        const selectedProduct = productData[0];
                        const productType = selectedProduct.type;
                        if (activities.has(productType)) {
                            return "session-selector";
                        } else {
                            return null;
                        }
                    }
                }
            },
            'session-selector': {
                name: 'session_id',
                label: 'Session',
                target: 'session',
                next: 'activity-selector',
                filter: function () {
                    const productData = $("#product-selector").select2('data');
                    if (productData && productData.length > 0) {
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
            const { selectorId, value, complete } = e.detail;
            clearNotifications();
            togglePreorderDetails(false);
            if (complete && value) {
                if (selectorId === 'activity-selector') {
                    const activityId = value;
                    const studentId = $('#student-selector').val()
                    if (activityId && studentId) {
                        const activityData = $("#product-selector").select2('data')[0];
                        const activityType = activityData.type;
                        loadActivityRegistration(activityId, activityType, studentId);
                    }
                } else if (selectorId === 'product-selector') {
                    loadEquipmentPurchase();
                }
            }
        });

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
                    text: preloadedStudent.student_name,
                    disable: true
                }
            }
            selectHandler.applyData(preloadedData);
        }
    });
})(jQuery);
