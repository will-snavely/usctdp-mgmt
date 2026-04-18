(function ($) {
    "use strict";

    $(document).ready(function () {
        var preloadedData = {};
        var discounts = [];
        var selectedFamily;
        var selectedStudent;
        var selectedActivity;
        var selectedMerchandise;

        const MERCHANDISE_PRICING = {
            'tshirt': USCTDP_Admin.safeParseFloat(usctdp_mgmt_admin.tshirt_pricing),
            'racket': USCTDP_Admin.safeParseFloat(usctdp_mgmt_admin.racket_pricing)
        };
        const paymentSettings = {
            checkoutButton: true,
            allowPayLater: true,
            manageDiscounts: true,
            paymentMode: "create",
            submitButtonText: "Submit",
            redirectOnComplete: true
        };
        const paymentTable = new USCTDP_Admin.RegistrationPaymentTable(
            "payment-table-section",
            paymentSettings
        );
        const viewRosterModal = document.querySelector('#view-roster-modal');
        const viewWaitlistModal = document.querySelector('#view-waitlist-modal');

        function clearNotifications() {
            $('#notifications-list').children().remove();
            $('#notifications-section').addClass('hidden');
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

        function set_notification(slug, message, ignoreable = false) {
            const notification = `
                <div id="${slug}-notification" class="notification">
                    <p>${message}</p>
                    ${ignoreable ? `
                    <div class="flex-row gap-10 align-center">
                        <button id="ignore-notification-btn" class="notification-button button">
                            Proceed
                        </button>
                        <button id="waitlist-student-btn" class="notification-button button">
                            Add to Waitlist
                        </button>
                    </div>
                    ` : ''}
                </div>`;
            $('#notifications-list').append(notification);
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
            let base_price = USCTDP_Admin.safeParseFloat($('#clinic_base_price').val());
            let computed_price = base_price;
            let discount_objects = [];

            if ($('#discount-additional-day').is(':checked')) {
                const addtl_day_discount_amount = $('#clinic-preorder').data('additional_day_discount');
                discount_objects.push(new USCTDP_Admin.AdditionalDayDiscount(addtl_day_discount_amount));
            }
            if ($('#discount-sibling').is(':checked') && $('#discount-sibling-percent').val()) {
                const sibling_discount_percent = parseFloat($('#discount-sibling-percent').val());
                discount_objects.push(new USCTDP_Admin.SiblingDiscount(sibling_discount_percent));
            }

            discounts = [];
            discount_objects.forEach(discount => {
                const amount = discount.amount(base_price);
                discounts.push({
                    code: discount.code,
                    amount: amount,
                    value: discount.value,
                    reason: discount.reason
                });
                computed_price -= amount;
            });
            $('#sale-price-value').text(USCTDP_Admin.formatUsd(computed_price));
        }

        function bind_clinic_info(info) {
            const { active, waitlist, capacity, pricing, student_level } = info;
            const full = active >= capacity;
            const one_day_price = parseFloat(pricing['One']);
            const two_day_price = parseFloat(pricing['Two']);
            const diff = two_day_price - one_day_price;
            const discount = one_day_price - diff;
            $('#clinic-preorder input[type="checkbox"]').prop('checked', false);
            $('#clinic-preorder input[type="text"]').val('');
            $('#clinic-current-size').text(active);
            $('#clinic-waitlist-size').text(waitlist);
            $('#clinic-max-size').text(capacity);
            $('#clinic-capacity .clinic-capacity-value').removeClass('red-bg green-bg');
            $('#clinic-capacity .clinic-capacity-value').addClass(full ? 'red-bg' : 'green-bg');
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
                discounts = [];
                if (info.student_registered) {
                    set_notification(
                        'student-registered',
                        'This student is already registered for this activity.',
                        false
                    );
                } else if (info.active >= info.capacity) {
                    set_notification(
                        'activity-full',
                        'This activity is full.',
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

        async function loadActivityRegistration(activityId, activityType, studentId) {
            clearNotifications();
            if (activityType === "clinic") { // Clinic
                await loadClinicRegistration(activityId, studentId);
            }
        }

        async function loadMerchandiseRegistration(productId, productCode) {
            clearNotifications();
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

        $('#notifications-section').on('click', '#activity-full-notification #ignore-notification-btn', function () {
            clearNotifications();
            togglePreorderDetails(true, "clinic-preorder");
        });

        $('#notifications-section').on('click', '#activity-full-notification #waitlist-student-btn', function () {
            const studentId = selectedStudent.id;
            const activityId = selectedActivity.id;
            USCTDP_Admin.ajax_addWaitlistStudent(studentId, activityId)
                .then(function () {
                    Swal.fire({
                        title: 'Success',
                        text: 'Student added to waitlist.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(function () {
                        var rosterUrl = `admin.php?page=usctdp-admin-clinic-rosters&activity_id=${activityId}`;
                        window.location.href = rosterUrl;
                    });
                })
                .catch(function (error) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to add student to waitlist. Inform a developer.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        });

        $('#add-clinic-registration').on('click', function () {
            var displayActivityName = checkoutActivityName(selectedActivity.name);
            const familyId = $("#family-selector").val();

            const registration = {
                activity_id: selectedActivity.id,
                activity_name: displayActivityName,
                product_id: selectedActivity.product_id,
                student_id: selectedStudent.id,
                family_id: selectedFamily.id,
                student_first: selectedStudent.first,
                student_last: selectedStudent.last,
                student_level: $('#student-level').val(),
                session_id: selectedActivity.session_id,
                session_name: selectedActivity.session_name,
                discounts: discounts,
                notes: $('#clinic-notes').val()
            };

            const basePrice = USCTDP_Admin.safeParseFloat($('#clinic_base_price').val());
            const additionalDayDiscount = $('#discount-additional-day').data('discount_value');
            const result = paymentTable.addNewRegistration(
                registration,
                basePrice,
                discounts,
                additionalDayDiscount
            );
            if (!result.success) {
                alert("Failed to add item: " + result.message);
                return;
            }

            const addRacket = $('#add_racket').is(':checked');
            const addTshirt = $('#add_tshirt').is(':checked');
            if (addRacket) {
                const racket_pricing = USCTDP_Admin.safeParseFloat(usctdp_mgmt_admin.racket_pricing);
                const merch = {
                    product_id: usctdp_mgmt_admin.racket_product_id,
                    product_name: 'Wilson Tennis Racket',
                    student_id: $('#student-selector').val(),
                    family_id: familyId,
                    student_first: selectedStudent.first,
                    student_last: selectedStudent.last,
                };
                paymentTable.addNewMerchandise(merch, racket_pricing);
            }
            if (addTshirt) {
                const tshirt_pricing = USCTDP_Admin.safeParseFloat(usctdp_mgmt_admin.tshirt_pricing);
                const merch = {
                    product_id: usctdp_mgmt_admin.tshirt_product_id,
                    product_name: 'USCTDP T-Shirt',
                    student_id: $('#student-selector').val(),
                    family_id: familyId,
                    student_first: selectedStudent.first,
                    student_last: selectedStudent.last,
                };
                paymentTable.addNewMerchandise(merch, tshirt_pricing);
            }

            clearNotifications();
            togglePreorderDetails(false);
            togglePaymentTable(true);
            if ('activity-selector' in selectorConfig) {
                $('#activity-selector').val(null).trigger('change');
            } else if ('student-selector' in selectorConfig) {
                $('#student-selector').val(null).trigger('change');
            }
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

            if (usctdp_mgmt_admin.preload.student_id && usctdp_mgmt_admin.preload.activity_id) {
                $("#context-selection").addClass("hidden");
                loadActivityRegistration(
                    selectedActivity.id,
                    selectedActivity.type,
                    selectedStudent.id);
            }
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

        if (usctdp_mgmt_admin.preload.student_id) {
            const preloadedStudent = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
            delete selectorConfig['family-selector'];
            delete selectorConfig['student-selector'];
            selectorConfig['session-selector'].isRoot = true;
            selectedStudent = {
                id: preloadedStudent.student_id,
                first: preloadedStudent.student_first,
                last: preloadedStudent.student_last,
                name: preloadedStudent.student_name
            };
            selectedFamily = {
                id: preloadedStudent.family_id,
                name: preloadedStudent.family_name
            };

            $('#preloaded-data').removeClass("hidden");
            $('#preloaded-family-name').text(selectedFamily.name);
            $('#preloaded-family').removeClass('hidden');
            $('#preloaded-student-name').text(selectedStudent.name);
            $('#preloaded-student').removeClass('hidden');
        }

        if (usctdp_mgmt_admin.preload.activity_id) {
            const preloadedActivity = Object.values(usctdp_mgmt_admin.preload.activity_id)[0];

            delete selectorConfig['session-selector'];
            delete selectorConfig['activity-selector'];
            delete selectorConfig['merchandise-selector'];

            if ('student-selector' in selectorConfig) {
                selectorConfig['student-selector'].next = null;
            }
            selectedActivity = {
                id: preloadedActivity.activity_id,
                name: preloadedActivity.activity_name,
                type: preloadedActivity.activity_type,
                product_id: preloadedActivity.product_id,
                session_id: preloadedActivity.session_id,
                session_name: preloadedActivity.session_name
            };

            $('#preloaded-data').removeClass("hidden");
            $('#preloaded-session-name').text(selectedActivity.session_name);
            $('#preloaded-session').removeClass('hidden');
            $('#preloaded-activity-name').text(selectedActivity.name);
            $('#preloaded-activity').removeClass('hidden');
        }

        const selectHandler = new USCTDP_Admin.CascasdingSelect('context-selectors', selectorConfig);

        $('#context-selectors').on('cascade:change', function (e) {
            const { selectorId, value, complete } = e.detail;
            clearNotifications();
            togglePreorderDetails(false);

            if (selectorId === 'family-selector') {
                if (value) {
                    var familyData = $('#family-selector').select2('data')[0];
                    selectedFamily = {
                        id: value,
                        name: familyData.text
                    };
                } else {
                    selectedFamily = null;
                }
            }

            if (selectorId === 'student-selector') {
                if (value) {
                    var studentData = $('#student-selector').select2('data')[0];
                    selectedStudent = {
                        id: value,
                        first: studentData.first,
                        last: studentData.last,
                        name: studentData.text
                    };
                } else {
                    selectedStudent = null;
                }
            }

            if (selectorId === 'activity-selector') {
                if (value) {
                    const sessionData = $("#session-selector").select2('data')[0];
                    const activityData = $("#activity-selector").select2('data')[0];
                    const activityType = activityData.type;
                    selectedActivity = {
                        id: value,
                        name: activityData.text,
                        type: activityType,
                        product_id: activityData.product_id,
                        session_id: sessionData.id,
                        session_name: sessionData.text
                    };
                } else {
                    selectedActivity = null;
                }
            }

            if (selectorId === 'merchandise-selector') {
                if (value) {
                    const merchandiseData = $("#merchandise-selector").select2('data')[0];
                    selectedMerchandise = {
                        id: value,
                        name: merchandiseData.text,
                        type: merchandiseData.type,
                        code: merchandiseData.code
                    };
                } else {
                    selectedMerchandise = null;
                }
            }

            if (complete && value) {
                $('#preorder-details .preorder-subtype').addClass('hidden');
                if (selectedActivity && selectedStudent) {
                    loadActivityRegistration(selectedActivity.id, selectedActivity.type, selectedStudent.id);
                } else if (selectedMerchandise) {
                    loadMerchandiseRegistration(selectedMerchandise.id, selectedMerchandise.code);
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
                    d.action = usctdp_mgmt_admin.registrations_datatable_action;
                    d.security = usctdp_mgmt_admin.registrations_datatable_nonce;
                    d.activity_id = selectedActivity.id;
                    d.status = 'active';
                }
            },
            columns: [
                { data: 'student_first' },
                { data: 'student_last' },
                { data: 'student_age' },
                { data: 'registration_student_level' }
            ]
        });

        var viewWaitlistTable = $('#view-waitlist-table').DataTable({
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
                    d.action = usctdp_mgmt_admin.waitlist_datatable_action;
                    d.security = usctdp_mgmt_admin.waitlist_datatable_nonce;
                    d.activity_id = selectedActivity.id;
                }
            },
            columns: [
                { data: 'student_first' },
                { data: 'student_last' },
                {
                    data: 'waitlist_created_at',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            const createdDate = new Date(data).toLocaleString();
                            return createdDate;
                        }
                        return data;
                    }
                }
            ]
        });

        $('#view-roster-btn').on('click', function () {
            $('#roster-clinic-name').text(selectedActivity.name);
            viewRosterModal.showModal();
            viewRosterTable.ajax.reload();
        });

        $('#close-view-roster-modal').on('click', function () {
            viewRosterModal.close();
        });

        $('#view-waitlist-btn').on('click', function () {
            $('#waitlist-clinic-name').text(selectedActivity.name);
            viewWaitlistModal.showModal();
            viewWaitlistTable.ajax.reload();
        });

        $('#close-view-waitlist-modal').on('click', function () {
            viewWaitlistModal.close();
        });

        if (usctdp_mgmt_admin.preload.student_id && usctdp_mgmt_admin.preload.activity_id) {
            $("#context-selection").addClass("hidden");
            loadActivityRegistration(
                selectedActivity.id,
                selectedActivity.type,
                selectedStudent.id);
        }
    });
})(jQuery);
