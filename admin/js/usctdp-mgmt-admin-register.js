(function ($) {
    "use strict";

    $(document).ready(function () {
        var preloadedData = {};

        const MERCHANDISE_PRICING = {
            'tshirt': usctdp_mgmt_admin.tshirt_pricing,
            'racket': usctdp_mgmt_admin.racket_pricing
        };
        const paymentSettings = {
            checkoutButton: true,
            allowPayLater: true,
            paymentMode: "create",
            submitButtonText: "Submit",
            redirectOnComplete: true
        };
        const paymentTable = new USCTDP_Admin.RegistrationPaymentTable(
            "payment-table-section",
            paymentSettings
        );
        const viewRosterModal = document.querySelector('#view-roster-modal');

        function clearNotifications() {
            $('#notifications-section').children().remove();
        }

        function togglePreorderDetails(visible, subtype) {
            if (visible) {
                $('.preorder-subtype').addClass('hidden');
                $('#preorder-details').removeClass('hidden');
                if (subtype) {
                    $('#' + subtype).removeClass('hidden');
                }
            } else {
                $('#preorder-details').addClass('hidden');
                $('.preorder-subtype').addClass('hidden');
            }
        }

        function togglePaymentTable(visible) {
            $("#payment-table-section").toggleClass("hidden", !visible);
        }

        function set_notification(slug, message, ignoreable = false, ignore_action = null) {
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
                    if (ignore_action) {
                        ignore_action();
                    }
                });
            }
            $('#notifications-section').append($notification);
            $('#notifications-section').removeClass('hidden');
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

        function update_clinic_sale_price() {
            let computed_price = parseFloat($('#clinic_base_price').val());
            if ($('#discount-additional-day').is(':checked')) {
                computed_price -= $('#clinic-preorder').data('additional_day_discount');
            }
            if ($('#discount-sibling').is(':checked') && $('#discount-sibling-percent').val()) {
                const sibling_discount = parseFloat($('#discount-sibling-percent').val());
                computed_price -= computed_price * (sibling_discount / 100.0);
            }
            computed_price = parseFloat(computed_price.toFixed(2));
            $('#sale-price-value').text(computed_price.toFixed(2));
            $('#sale-price-value').data('sale_price', computed_price);
        }

        function bind_clinic_info(info) {
            const { registered, capacity, pricing, student_level } = info;
            const full = registered >= capacity;
            const one_day_price = parseFloat(pricing['One']);
            const two_day_price = parseFloat(pricing['Two']);
            const diff = two_day_price - one_day_price;
            const discount = one_day_price - diff;
            $('#clinic-preorder input[type="checkbox"]').prop('checked', false);
            $('#clinic-preorder input[type="text"]').val('');
            $('#clinic-current-size').text(registered);
            $('#clinic-max-size').text(capacity);
            $('#clinic-capacity .clinic-capacity-value').removeClass('full available');
            $('#clinic-capacity .clinic-capacity-value').addClass(full ? 'full' : 'available');
            $('#student-level').val(student_level);
            $('#clinic_base_price').val(one_day_price.toFixed(2));
            $('#discount-additional-day-value').text('($' + discount.toFixed(2) + ')');
            $('#discount-additional-day').data('discount_value', discount);
            update_clinic_sale_price();
            $("#clinic-preorder").removeData();
            $("#clinic-preorder").data('pricing', pricing);
            $("#clinic-preorder").data('additional_day_discount', discount);
        }

        $('#clinic_base_price').on('change', function () {
            update_clinic_sale_price();
        });

        $('#discount-additional-day').on('change', function () {
            update_clinic_sale_price();
        });

        $('#discount-sibling').on('change', function () {
            update_clinic_sale_price();
        });

        $('#discount-sibling-percent').on('change', function () {
            update_clinic_sale_price();
        });

        function bind_merchandise_info(info) {
            const { pricing, product_id, product_code } = info;
            $('#merch_base_price').val(parseFloat(pricing).toFixed(2));
            $("#merch-preorder").removeData();
            $("#merch-preorder").data('pricing', pricing);
            $("#merch-preorder").data('product_id', product_id);
            $("#merch-preorder").data('product_code', product_code);
        }

        async function loadClinicRegistration(clinicId, studentId) {
            try {
                const info = await getPreregistrationInfo(clinicId, studentId);
                bind_clinic_info(info);

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
                        true,
                        function () {
                            togglePreorderDetails(true, "clinic-preorder");
                        }
                    );
                } else {
                    togglePreorderDetails(true, "clinic-preorder");
                }
            } catch (error) {
                console.log("Error: ", error);
                alert("Failed to load clinic registration data. Try again or report this to a developer.");
            }
        }

        async function loadActivityRegistration(activityId, activityType, studentId) {
            $('#notifications-section').children().remove();
            if (activityType === 1) { // Clinic
                await loadClinicRegistration(activityId, studentId);
            }
        }

        async function loadMerchandiseRegistration(productId, productCode) {
            $('#notifications-section').children().remove();
            bind_merchandise_info({
                pricing: MERCHANDISE_PRICING[productCode],
                product_id: productId,
                product_code: productCode
            });
            togglePreorderDetails(true, "merch-preorder");
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
            const studentData = $("#student-selector").select2('data')[0];
            const activityData = $("#activity-selector").select2('data')[0];
            const familyId = $("#family-selector").val();
            const registration = {
                activity_id: $('#activity-selector').val(),
                activity_name: displayActivityName,
                product_id: activityData.product_id,
                student_id: $('#student-selector').val(),
                family_id: familyId,
                student_first: studentData.first,
                student_last: studentData.last,
                student_level: $('#student-level').val(),
                session_id: $('#session-selector').val(),
                session_name: $('#session-selector option:selected').text(),
                notes: $('#clinic-notes').val()
            };

            const price = $('#sale-price-value').data('sale_price');
            const result = paymentTable.addNewRegistration(registration, price);
            if (!result.success) {
                alert("Failed to add item: " + result.message);
                return;
            }

            const addRacket = $('#add_racket').is(':checked');
            const addTshirt = $('#add_tshirt').is(':checked');
            if (addRacket) {
                const racket_pricing = parseFloat(usctdp_mgmt_admin.racket_pricing);
                const merch = {
                    product_id: usctdp_mgmt_admin.racket_product_id,
                    product_name: 'Wilson Tennis Racket',
                    student_id: $('#student-selector').val(),
                    family_id: familyId,
                    student_first: studentData.first,
                    student_last: studentData.last,
                };
                paymentTable.addNewMerchandise(merch, racket_pricing);
            }
            if (addTshirt) {
                const tshirt_pricing = parseFloat(usctdp_mgmt_admin.tshirt_pricing);
                const merch = {
                    product_id: usctdp_mgmt_admin.tshirt_product_id,
                    product_name: 'USCTDP T-Shirt',
                    student_id: $('#student-selector').val(),
                    family_id: familyId,
                    student_first: studentData.first,
                    student_last: studentData.last,
                };
                paymentTable.addNewMerchandise(merch, tshirt_pricing);
            }
            clearNotifications();
            togglePreorderDetails(false);
            togglePaymentTable(true);
            $('#activity-selector').val(null).trigger('change');
        });

        $('#view-roster').on('click', function () {
        });

        $('#discount-sibling').on('change', function () {
            const checked = $('#discount-sibling').is(':checked');
            $('#discount-sibling-percent').prop('disabled', !checked);
        });

        $('#add-merchandise').on('click', function () {
            const studentData = $("#student-selector").select2('data')[0];
            const merchandiseData = $("#merchandise-selector").select2('data')[0];
            const merchandiseName = $('#merchandise-selector option:selected').text();
            const merch = {
                product_id: merchandiseData.id,
                product_name: merchandiseName,
                student_id: $('#student-selector').val(),
                family_id: $("#family-selector").val(),
                student_first: studentData.first,
                student_last: studentData.last,
            };

            var pricing = MERCHANDISE_PRICING[merchandiseData.code];
            const result = paymentTable.addNewMerchandise(merch, pricing);
            if (!result.success) {
                alert("Failed to add item: " + result.message);
                return;
            }

            clearNotifications();
            togglePreorderDetails(false);
            togglePaymentTable(true);
            $('#merchandise-selector').val(null).trigger('change');
        });

        $('#payment-method').on('change', function () {
            if (this.value === 'check') {
                $('#check-fields').removeClass('hidden');
            } else {
                $('#check-fields').addClass('hidden');
            }
        });

        $('#payment-table-section').on('payment:cart:add', function () {
            const editNode = `
            <div class="edit-note">
                <span> 
                    <strong>NOTE:</strong> All purchases must come from one family.
                </span>
            </div>
            `;
            const $labelWrap = $('#family-selector-section .context-selector-label-wrap');
            if ($labelWrap.find('.edit-note').length === 0) {
                $labelWrap.append(editNode);
            }
            $('#family-selector').prop('disabled', true);
        });


        $('#payment-table-section').on('payment:cart:empty', function () {
            togglePaymentTable(false);
            $('#family-selector').prop('disabled', false);
            $('#family-selector-section .context-selector-label-wrap .edit-note').remove();
        });

        $('#payment-table-section').on('payment:checkout', function () {
            clearNotifications();
            togglePreorderDetails(false);
            $('#registration-info').addClass('hidden');
            $('#notifications-section').addClass('hidden');
            $('#registration-container').removeClass('edit-order-mode');
            $('#registration-container').addClass('checkout-mode');

        });

        $('#payment-table-section').on('payment:modify', function () {
            $('#activity-selector').val(null).trigger('change');
            $('#registration-info').removeClass('hidden');
            $('#registration-container').removeClass('checkout-mode');
            $('#registration-container').addClass('edit-order-mode');
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
                next: 'session-selector',
                filter: function () {
                    return {
                        family_id: $('#family-selector').val()
                    };
                }
            },
            'session-selector': {
                name: 'session_id',
                label: 'Session',
                target: 'session',
                branches: ['activity-selector', 'merchandise-selector'],
                next: function (value) {
                    if (value === 'merch_only') {
                        return 'merchandise-selector';
                    } else if (value === 'new_session') {
                        return null;
                    } else {
                        return 'activity-selector';
                    }
                },
                pinnedOptions: [
                    { id: 'merch_only', text: '🎾 Merchandise Only' },
                    { id: 'new_session', text: '➕ New Special Session' }
                ]
            },
            'activity-selector': {
                name: 'activity_id',
                label: 'Activity',
                target: 'activity',
                next: null,
                filter: function () {
                    return {
                        session_id: $('#session-selector').val(),
                    };
                }
            },
            'merchandise-selector': {
                name: 'merchandise_id',
                label: 'Merchandise',
                target: 'product',
                next: null,
                filter: function () {
                    return {
                        type: 'merch',
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
                $('#preorder-details .preorder-subtype').addClass('hidden');
                if (selectorId === 'activity-selector') {
                    const activityId = value;
                    const studentId = $('#student-selector').val()
                    if (activityId && studentId) {
                        const activityData = $("#activity-selector").select2('data')[0];
                        const activityType = activityData.type;
                        loadActivityRegistration(activityId, activityType, studentId);
                    }
                } else if (selectorId === 'merchandise-selector') {
                    const merchandiseId = value;
                    const merchandiseData = $("#merchandise-selector").select2('data')[0];
                    const merchandiseCode = merchandiseData.code;
                    loadMerchandiseRegistration(merchandiseId, merchandiseCode);
                } else if (selectorId === 'session-selector' && value === 'new_session') {
                    togglePreorderDetails(true, "new-session-preorder");
                }
            }
        });

        var viewRosterTable = $('#view-roster-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var activityFilterValue = $('#activity-selector').val();
                    d.action = usctdp_mgmt_admin.registrations_datatable_action;
                    d.security = usctdp_mgmt_admin.registrations_datatable_nonce;
                    d.activity_id = activityFilterValue;
                }
            },
            columns: [
                { data: 'student_first' },
                { data: 'student_last' },
                { data: 'student_age' },
                { data: 'registration_student_level' },
            ]
        });
        $('#view-roster-btn').on('click', function () {
            const activityData = $("#activity-selector").select2('data')[0];
            $('#roster-clinic-name').text(activityData.text);
            viewRosterModal.showModal();
            viewRosterTable.ajax.reload();
        });
        $('#close-view-roster-modal').on('click', function () {
            viewRosterModal.close();
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
