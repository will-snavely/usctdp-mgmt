(function ($) {
    "use strict";

    $(document).ready(function () {
        $('input[type="radio"][name="payment_status"]').on('change', function () {
            var selectedValue = $(this).val();
            if (selectedValue === 'paid') {
                $('#existing-payment-info-section').show();
                $('#payment-required-section').hide();
            } else {
                $('#existing-payment-info-section').hide();
                $('#payment-required-section').show();
            }
        });

        $('#session-selector').select2({
            placeholder: "Search for a session...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-session',
                        action: usctdp_mgmt_admin.search_action,
                        security: usctdp_mgmt_admin.search_nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        $('#session-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#class-selection-section').hide();
            } else {
                $('#class-selection-section').show();
            }
            $('#class-selector').val(null);
            $('#class-selector').trigger('change');
        });

        $('#class-selector').select2({
            placeholder: "Search for a class...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-class',
                        action: usctdp_mgmt_admin.search_action,
                        security: usctdp_mgmt_admin.search_nonce,
                        'filter[session][value]': $('#session-selector').val(),
                        'filter[session][compare]': '=',
                        'filter[session][type]': 'NUMERIC'
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        $('#class-selector').on('change', function () {
            const selectedValue = this.value;
            if (selectedValue === '') {
                $('#student-selection-section').hide();
            } else {
                $('#student-selection-section').show();
            }
            $('#student-selector').val(null);
            $('#student-selector').trigger('change');
        });

        $('#student-selector').select2({
            placeholder: "Search for a student...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-student',
                        action: usctdp_mgmt_admin.search_action,
                        security: usctdp_mgmt_admin.search_nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        $('#student-selector').on('change', function () {
            const selectedStudent = this.value;
            const selectedClass = $('#class-selector').val();
            $('#registration-section').hide();
            $('#notifications-section').children().remove();
            if (selectedStudent === '' || selectedClass === '') {
                return;
            }
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.qualification_action,
                    class_id: selectedClass,
                    student_id: selectedStudent,
                    security: usctdp_mgmt_admin.qualification_nonce,
                },
                success: function (responseData) {
                    var current_size = responseData.registered;
                    var max_size = responseData.capacity;
                    $('#class-current-size').text(current_size);
                    $('#class-max-size').text(max_size);
                    if (responseData.student_registered) {
                        add_notification('The selected student is already registered for this class.');
                    } else {
                        if (current_size >= max_size) {
                            add_notification('This class is currently full.');
                        } else {
                            $('#registration-section').show();
                            reset_registration_section();
                        }
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                }
            });
        });

        function add_notification(message) {
            var notif = '<div class="notification">';
            notif += '<p>' + message + '</p>';
            notif += '</div>';
            $('#notifications-section').append(notif);
        }

        function reset_registration_section() {
            $('input[name="payment_status"][value="paid"]').prop('checked', true);
            $('#existing-payment-info-section').show();
            $('#payment-required-section').hide();
            $('#payment-method').val('');
            $('#payment-amount-existing').val('');
            $('#payment-date').val('');
            $('#payment-amount-pending').val('');
            $('#notes').val('');
        }


        if (usctdp_mgmt_admin.preloaded_session_name) {
            const newOption = new Option(
                usctdp_mgmt_admin.preloaded_session_name,
                usctdp_mgmt_admin.preloaded_session_id,
                true,
                true
            );
            $('#session-selector').append(newOption)
            $('#session-selector').val(usctdp_mgmt_admin.preloaded_session_id);
            $('#session-selector').trigger('change');
        }

        if (usctdp_mgmt_admin.preloaded_class_name) {
            const newOption = new Option(
                usctdp_mgmt_admin.preloaded_class_name,
                usctdp_mgmt_admin.preloaded_class_id,
                true,
                true
            );
            $('#class-selector').append(newOption);
            $('#class-selector').val(usctdp_mgmt_admin.preloaded_class_id);
            $('#class-selector').trigger('change');
        }

    });
})(jQuery);

