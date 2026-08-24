(function ($) {
    "use strict";

    $(document).ready(function () {
        var discounts = [];
        var selectedFamily;
        var selectedStudent;
        var selectedActivity;
        var selectedMerchandise;

        const MERCHANDISE_PRICING = {
            'tshirt': USCTDP_Admin.safeParseFloat(usctdp_mgmt_admin.tshirt_pricing),
            'racket': USCTDP_Admin.safeParseFloat(usctdp_mgmt_admin.racket_pricing)
        };
        // Pre-fills the Add Racket/T-Shirt price fields with their normal
        // price, same starting-point-the-admin-can-edit role
        // bind_merchandise_info() plays for #merch_base_price below - these
        // two have no dropdown-driven equivalent to re-trigger that fill
        // from, so it just happens once here instead.
        $('#racket_price').val(MERCHANDISE_PRICING.racket.toFixed(2));
        $('#tshirt_price').val(MERCHANDISE_PRICING.tshirt.toFixed(2));

        // The price field is only relevant once its add-on is actually
        // selected - starts hidden (see the "hidden" class in
        // usctdp-mgmt-admin-register.php) and toggles with the checkbox.
        $('#add_racket').on('change', function () {
            $('#racket_price').toggleClass('hidden', !this.checked);
        });
        $('#add_tshirt').on('change', function () {
            $('#tshirt_price').toggleClass('hidden', !this.checked);
        });
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

        function update_sale_price() {
            let base_price = USCTDP_Admin.safeParseFloat($('#activity_base_price').val());
            let computed_price = base_price;
            let discount_objects = [];

            if ($('#discount-additional-day').is(':checked')) {
                const addtl_day_discount_amount = $('#activity-preorder').data('additional_day_discount');
                discount_objects.push(new USCTDP_Admin.AdditionalDayDiscount(addtl_day_discount_amount));
            }
            if ($('#discount-early-signup').is(':checked')) {
                const tier_price = $('#discount-early-signup').data('tier_price');
                discount_objects.push(new USCTDP_Admin.EarlySignupDiscount(base_price - tier_price));
            }
            if ($('#discount-with-clinic').is(':checked')) {
                const tier_price = $('#discount-with-clinic').data('tier_price');
                discount_objects.push(new USCTDP_Admin.WithClinicDiscount(base_price - tier_price));
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

        function bind_activity_basic_info(info) {
            const { active, waitlist, capacity, student_level, shared_with, roster_title } = info;
            const full = active >= capacity;

            $('#activity-preorder input[type="checkbox"]').prop('checked', false);
            $('#activity-preorder input[type="text"]').val('');
            $('#activity-current-size').text(active);
            $('#activity-waitlist-size').text(waitlist);
            $('#activity-max-size').text(capacity);
            $('#activity-capacity .activity-capacity-value').removeClass('red-bg green-bg');
            $('#activity-capacity .activity-capacity-value').addClass(full ? 'red-bg' : 'green-bg');
            $('#student-level').val(student_level);
            $('#discount-sibling-percent').prop('disabled', true);

            // Capacity/active above reflect every activity sharing this
            // one's reservation group. View Roster now shows the same
            // combined group (see viewRosterTable's activity_ids
            // expansion), but Waitlist stays scoped to this one activity by
            // design - call out the shared group here so that difference
            // isn't a mystery.
            const isShared = Array.isArray(shared_with) && shared_with.length > 0;
            $('#activity-shared-capacity-note').toggleClass('hidden', !isShared);
            if (isShared) {
                $('#activity-shared-capacity-list').text(shared_with.join(', '));
            }

            // Stashed on selectedActivity so the View Roster modal (opened
            // later, from a separate click handler with no access to this
            // response) can show the same note/title.
            if (selectedActivity) {
                selectedActivity.shared_with = shared_with;
                selectedActivity.roster_title = roster_title;
            }
        }

        function bind_tournament_info(info) {
            const { pricing } = info;
            const base_price = parseFloat(pricing.base);
            const early_signup_price = (pricing.early_signup !== null && pricing.early_signup !== undefined)
                ? parseFloat(pricing.early_signup) : null;
            const with_clinic_price = (pricing.with_clinic !== null && pricing.with_clinic !== undefined)
                ? parseFloat(pricing.with_clinic) : null;
            const hasTiers = early_signup_price !== null || with_clinic_price !== null;

            $('#activity_base_price').val(base_price.toFixed(2));
            $('#clinic-only-discounts').addClass('hidden');
            $('#tournament-only-discounts').toggleClass('hidden', !hasTiers);

            $('#discount-early-signup').closest('.discount-field').toggleClass('hidden', early_signup_price === null);
            $('#discount-with-clinic').closest('.discount-field').toggleClass('hidden', with_clinic_price === null);
            $('#discount-early-signup-value').text(
                early_signup_price !== null ? '(' + USCTDP_Admin.formatUsd(early_signup_price) + ')' : ''
            );
            $('#discount-with-clinic-value').text(
                with_clinic_price !== null ? '(' + USCTDP_Admin.formatUsd(with_clinic_price) + ')' : ''
            );
            $('#discount-early-signup').data('tier_price', early_signup_price);
            $('#discount-with-clinic').data('tier_price', with_clinic_price);

            update_sale_price();
            $("#activity-preorder").removeData();
            $("#activity-preorder").data('pricing', pricing);
        }

        function bind_clinic_info(info) {
            const { pricing } = info;
            const one_day_price = parseFloat(pricing['One']);
            var discount = 20;
            if (pricing['Two']) {
                const two_day_price = parseFloat(pricing['Two']);
                const diff = two_day_price - one_day_price;
                discount = one_day_price - diff;
            }

            $('#activity_base_price').val(one_day_price.toFixed(2));
            $('#tournament-only-discounts').addClass('hidden');
            $('#clinic-only-discounts').removeClass('hidden');
            $('#discount-additional-day-value').text('($' + discount.toFixed(2) + ')');
            $('#discount-additional-day').data('discount_value', discount);
            update_sale_price();
            $("#activity-preorder").removeData();
            $("#activity-preorder").data('pricing', pricing);
            $("#activity-preorder").data('additional_day_discount', discount);
        }

        $('#activity_base_price').on('change', function () {
            update_sale_price();
        });

        $('#discount-additional-day').on('change', function () {
            update_sale_price();
        });

        $('#discount-early-signup').on('change', function () {
            if ($(this).is(':checked')) {
                $('#discount-with-clinic').prop('checked', false);
            }
            update_sale_price();
        });

        $('#discount-with-clinic').on('change', function () {
            if ($(this).is(':checked')) {
                $('#discount-early-signup').prop('checked', false);
            }
            update_sale_price();
        });

        $('#discount-sibling').on('change', function () {
            update_sale_price();
        });

        $('#discount-sibling-percent').on('change', function () {
            update_sale_price();
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
                bind_activity_basic_info(info);
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
                    togglePreorderDetails(true, "activity-preorder");
                }
            } catch (error) {
                console.log("Error: ", error);
                alert("Failed to load clinic registration data. Try again or report this to a developer.");
            }
        }

        async function loadTournamentRegistration(tournamentId, studentId) {
            try {
                const info = await getPreregistrationInfo(tournamentId, studentId);
                bind_activity_basic_info(info);
                bind_tournament_info(info);
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
                    togglePreorderDetails(true, "activity-preorder");
                }
            } catch (error) {
                console.log("Error: ", error);
                alert("Failed to load tournament registration data. Try again or report this to a developer.");
            }
        }

        async function loadActivityRegistration(activityId, activityType, studentId) {
            clearNotifications();
            if (activityType === "clinic") { // Clinic
                await loadClinicRegistration(activityId, studentId);
            } else if (activityType === "tournament") {
                await loadTournamentRegistration(activityId, studentId);
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
            togglePreorderDetails(true, "activity-preorder");
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
                        var rosterUrl = `admin.php?page=usctdp-admin-activities&activity_id=${activityId}`;
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

        $('#add-activity-registration').on('click', function () {
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
                notes: $('#activity-notes').val()
            };

            const basePrice = USCTDP_Admin.safeParseFloat($('#activity_base_price').val());
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
                // Reads whatever's currently in the price field (pre-filled
                // from usctdp_mgmt_admin.racket_pricing on page load below,
                // but editable) rather than that default directly, so a
                // price typed in to override it is actually respected -
                // same fix as #add-merchandise's Base Price field above.
                const racket_pricing = USCTDP_Admin.safeParseFloat($('#racket_price').val());
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
                const tshirt_pricing = USCTDP_Admin.safeParseFloat($('#tshirt_price').val());
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
            $('#activity-selector').val(null).trigger('change');
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
            // Reads whatever's currently in the Base Price field, not the
            // MERCHANDISE_PRICING lookup - bind_merchandise_info() only
            // uses that lookup to pre-fill this field with a sensible
            // default when the dropdown selection changes, same as
            // #activity_base_price does for activities (see basePrice
            // above). Re-deriving from the lookup here instead of reading
            // the field silently discarded any price the admin had typed
            // in to override it.
            var pricing = USCTDP_Admin.safeParseFloat($('#merch_base_price').val());
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
                // Don't offer archived sessions as somewhere to register a
                // new student - active:1 excludes only 'archived', not
                // 'scheduled' (see search_sessions() in Usctdp_Mgmt_Session_Query).
                filter: function () {
                    return { active: 1 };
                },
                branches: ['clinic-selector', 'activity-selector', 'merchandise-selector'],
                next: function (value, $el) {
                    if (value === 'merch_only') {
                        return 'merchandise-selector';
                    } else if (value === 'new_session') {
                        return null;
                    } else {
                        var sessionData = $el.select2('data')[0];
                        var isTournament = sessionData &&
                            USCTDP_Admin.TOURNAMENT_SESSION_CATEGORIES.indexOf(sessionData.category) !== -1;
                        return isTournament ? null : 'clinic-selector';
                    }
                },
                autoSelectChild: {
                    id: 'activity-selector',
                    resolve: function (value, $el) {
                        return USCTDP_Admin.resolveTournamentActivity(value, $el.select2('data')[0]);
                    }
                },
                pinnedOptions: [
                    { id: 'merch_only', text: '🎾 Merchandise Only' },
                    { id: 'new_session', text: '➕ New Special Session' }
                ]
            },
            'clinic-selector': {
                name: 'product_id',
                label: 'Clinic',
                target: 'product',
                next: 'activity-selector',
                filter: function () {
                    return {
                        session_id: $('#session-selector').val(),
                    };
                }
            },
            'activity-selector': {
                name: 'activity_id',
                label: 'Day',
                target: 'activity',
                next: null,
                filter: function () {
                    return {
                        session_id: $('#session-selector').val(),
                        product_id: $('#clinic-selector').val(),
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
                { data: 'registration_student_level' },
                { data: 'activity_name' }
            ],
            autoWidth: false,
            columnDefs: [
                { width: "20%", targets: 0 },
                { width: "20%", targets: 1 },
                { width: "10%", targets: 2 },
                { width: "10%", targets: 3 },
                { width: "40%", targets: 4 }
            ],
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
            // Falls back to the activity's own name if roster_title hasn't
            // come back yet for some reason - roster_title is the combined
            // group name/title (see get_shared_activities()'s sibling,
            // get_roster_title_for_activity(), in the ajax handler).
            $('#roster-activity-name').text(selectedActivity.roster_title || selectedActivity.name);

            // shared_with is [{id, title}] (see get_shared_activities()) -
            // only .title is needed here.
            const sharedWith = selectedActivity.shared_with;
            const isShared = Array.isArray(sharedWith) && sharedWith.length > 0;
            $('#roster-shared-capacity-note').toggleClass('hidden', !isShared);
            if (isShared) {
                $('#roster-shared-capacity-list').text(sharedWith.map(function (a) { return a.title; }).join(', '));
            }

            viewRosterModal.showModal();
            viewRosterTable.ajax.reload();
        });

        $('#close-view-roster-modal').on('click', function () {
            viewRosterModal.close();
        });

        $('#view-waitlist-btn').on('click', function () {
            $('#waitlist-activity-name').text(selectedActivity.name);
            viewWaitlistModal.showModal();
            viewWaitlistTable.ajax.reload();
        });

        $('#close-view-waitlist-modal').on('click', function () {
            viewWaitlistModal.close();
        });

        // Prepopulate (but don't lock) the selectors from page preloads.
        // Must run after the cascade:change handler above is bound -
        // applyData fires the normal change cascade, which is what populates
        // selectedFamily/selectedStudent/selectedActivity and kicks off the
        // registration load when the cascade completes.
        if (usctdp_mgmt_admin.preload.student_id) {
            const preloadedStudent = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
            const preloadedData = {
                'family-selector': {
                    id: preloadedStudent.family_id,
                    text: preloadedStudent.family_name,
                    disable: false
                },
                'student-selector': {
                    id: preloadedStudent.student_id,
                    text: preloadedStudent.student_name,
                    first: preloadedStudent.student_first,
                    last: preloadedStudent.student_last,
                    disable: false
                }
            };

            // An activity preload is only honored alongside a student (the
            // waitlist "Register" link supplies both); an activity on its
            // own doesn't fit this page's flow and is ignored.
            if (usctdp_mgmt_admin.preload.activity_id) {
                const preloadedActivity = Object.values(usctdp_mgmt_admin.preload.activity_id)[0];
                // wp_localize_script delivers DB values as strings, but the
                // tournament check compares numeric categories.
                const category = parseInt(preloadedActivity.session_category, 10);
                preloadedData['session-selector'] = {
                    id: preloadedActivity.session_id,
                    text: preloadedActivity.session_name,
                    category: category,
                    disable: false
                };
                // Tournament sessions never show the Clinic/Day selectors;
                // the session's autoSelectChild resolves its sole activity
                // silently, same as a manual selection - so only clinic
                // sessions preload those two directly.
                if (USCTDP_Admin.TOURNAMENT_SESSION_CATEGORIES.indexOf(category) === -1) {
                    preloadedData['clinic-selector'] = {
                        id: preloadedActivity.product_id,
                        text: preloadedActivity.product_name,
                        disable: false
                    };
                    preloadedData['activity-selector'] = {
                        id: preloadedActivity.activity_id,
                        text: preloadedActivity.activity_name,
                        type: preloadedActivity.activity_type,
                        product_id: preloadedActivity.product_id,
                        disable: false
                    };
                }
            }

            selectHandler.applyData(preloadedData);
        }
    });
})(jQuery);
