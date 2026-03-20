(function ($) {
    "use strict";

    $(document).ready(function () {
        var preloadedData = {};

        const paymentSettings = {
            checkoutButton: true,
            allowPayLater: true,
            paymentMode: "create",
            submitButtonText: "Submit"
        };
        const paymentTable = new USCTDP_Admin.RegistrationPaymentTable(
            "payment-table-section",
            paymentSettings
        );

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

        function loadActivityRegistration(activityId, activityType, studentId) {
            $('#notifications-section').children().remove();
            if (activityType === 1) { // Clinic
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

        function checkoutActivityName(name) {
            const replacements = [
                [/^Adult/, ""],
            ];
            return USCTDP_Admin.applyReplacements(name, replacements);
        }

        $('#add-clinic-registration').on('click', function () {
            const activityName = $('#activity-selector option:selected').text();
            var displayActivityName = checkoutActivityName(activityName);
            const addRacket = $('#add_racket').is(':checked');
            var racketFee = 0;
            if (addRacket) {
                var rawFee = $('#racket_fee').val();
                const fixedFee = parseFloat(rawFee).toFixed(2);
                racketFee = parseFloat(fixedFee);
            }
            const studentData = $("#student-selector").select2('data')[0];
            const activityData = $("#activity-selector").select2('data')[0];
            const registration = {
                activity_id: $('#activity-selector').val(),
                activity_name: displayActivityName,
                product_id: activityData.product_id,
                student_id: $('#student-selector').val(),
                family_id: $('#family-selector').val(),
                student_first: studentData.first,
                student_last: studentData.last,
                student_level: $('#student-level').val(),
                session_id: $('#session-selector').val(),
                session_name: $('#session-selector option:selected').text(),
                notes: $('#clinic-notes').val()
            };
            const one_day_price = parseInt($('#clinic-info').data('pricing')['One']);
            const two_day_price = parseInt($('#clinic-info').data('pricing')['Two']);
            const diff = two_day_price - one_day_price;
            const priceEstimate = clinicPriceEstimate(
                registration,
                one_day_price,
                diff
            );
            const result = paymentTable.addNewRegistration(registration, priceEstimate);
            if (!result.success) {
                alert("Failed to add item: " + result.message);
                return;
            }
            if (addRacket) {
                const equipment = {
                    product_code: 'racket',
                    product_name: 'Wilson Tennis Racket',
                    student_id: $('#student-selector').val(),
                    student_first: studentData.first,
                    student_last: studentData.last,
                };
                const price = racketFee;
                paymentTable.addEquipment(equipment, price);
            }
            clearNotifications();
            togglePreorderDetails(false);
            togglePaymentTable(true);
            $('#activity-selector').val(null).trigger('change');
        });

        $('#add_racket').on('change', function () {
            const addRacket = $('#add_racket').is(':checked');
            if (addRacket) {
                $('#racket_fee').val(50);
                $('#racket-fee-field').removeClass('hidden');
            } else {
                $('#racket_fee').val('');
                $('#racket-fee-field').addClass('hidden');
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

        $('#payment-table-section').on('payment:checkout', function () {
            clearNotifications();
            togglePreorderDetails(false);
            $('#registration-info').addClass('hidden');
        });

        $('#payment-table-section').on('payment:modify', function () {
            $('#activity-selector').val(null).trigger('change');
            $('#registration-info').removeClass('hidden');
        });

        $('#payment-table-section').on('payment:empty', function () {
            togglePaymentTable(false);
            $('#family-selector').prop('disabled', false);
            $('#family-selector-section .context-selector-label-wrap .edit-note').remove();
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
                branches: ['activity-selector'],
                next: function (value) {
                    if (value === 'merch_only' || value === 'new_session') {
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
                } else if (value === 'merch_only') {
                    togglePreorderDetails(true, "merch-preorder");
                } else if (value === 'new_session') {
                    togglePreorderDetails(true, "new-session-preorder");
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
