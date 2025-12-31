(function ($) {
    "use strict";

    $(document).ready(function () {
        $('#registration-details-section').hide();
        $('#preloaded-student-heading').hide();
        $('#registration-history-section').hide();

        function createSelectSection(kind, label) {
            return `
                <div id='${kind}-selection-section'>
                    <h2 id='${kind}-selector-label'> Select a ${label} </h2>
                    <select id='${kind}-selector' name='${kind}_id'></select>
                </div>`;
        }

        var $studentSelector = $(createSelectSection('student', 'Student'));
        var $sessionSelector = $(createSelectSection('session', 'Session'));
        var $classSelector = $(createSelectSection('class', 'Class'));

        $('#context-selection-section').append($studentSelector);
        $('#context-selection-section').append($sessionSelector);
        $('#context-selection-section').append($classSelector);

        $('#student-selector').select2({
            placeholder: "Search for a student...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-student',
                        action: usctdp_mgmt_admin.select2_search_action,
                        security: usctdp_mgmt_admin.select2_search_nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
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
                        action: usctdp_mgmt_admin.select2_search_action,
                        security: usctdp_mgmt_admin.select2_search_nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
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
                        action: usctdp_mgmt_admin.select2_search_action,
                        security: usctdp_mgmt_admin.select2_search_nonce,
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

        if (usctdp_mgmt_admin.preloaded_student_name) {
            const newOption = new Option(
                usctdp_mgmt_admin.preloaded_student_name,
                usctdp_mgmt_admin.preloaded_student_id,
                true,
                true
            );
            $('#student-selector').append(newOption);
            $('#student-selector').val(usctdp_mgmt_admin.preloaded_student_id);
            $('#session-selector').trigger('change');
            $('#student-selector').prop('disabled', true);
            $('#student-selector-label').text('Registering ' + usctdp_mgmt_admin.preloaded_student_name);


            //$('#preloaded-student-heading').show();
            //$('#registration-history-section').show();
            //$('#student-selector').hide();
            //$('#student-selector').attr('disabled', true);
            //$('#student-name-header').text(usctdp_mgmt_admin.preloaded_student_name);
            //$('#student-name-history').text(usctdp_mgmt_admin.preloaded_student_name);
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
            $('#session-selector').prop('disabled', true);
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
            $('#class-selector').prop('disabled', true);
        }


        var registration_history_table = $('#registration-history-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: true,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var studentFilterValue = $('#student-selector').val();
                    d.action = usctdp_mgmt_admin.datatable_search_action;
                    d.security = usctdp_mgmt_admin.datatable_search_nonce;
                    d.post_type = 'usctdp-registration';
                    d['filter[student][value]'] = studentFilterValue;
                    d['filter[student][compare]'] = '=';
                    d['filter[student][type]'] = 'NUMERIC';
                    d['expand[]'] = 'usctdp-class';
                }
            },
            columns: [
                {
                    data: 'class',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return data.title;
                        }
                        return data;
                    }
                },
                {
                    data: 'class',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.session && data.session.post_title) {
                            return data.session.post_title;
                        }
                        return data;
                    }
                },
                {
                    data: 'level',
                    defaultContent: '',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.level) {
                            return data.level;
                        }
                        return data;
                    }
                }
            ]
        });

        function reset_registration_fields() {
            $('#student-level').val('');
            $('#payment-amount-outstanding').val('');
            $('#payment-amount-paid').val('');
            $('#check-number-field').hide();
            $('#check-number').val('');
            $('#payment-method').val('');
            $('#notes').val('');
            $('#notifications-section').children().remove();
        }

        function hide_registration_fields() {
            $('#registration-details-section').hide();
        }

        function show_registration_fields() {
            $('#registration-details-section').show();
        }

        function set_notification(message, ignoreable = false) {
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
            }
            $('#notifications-section').append($notification);

            $ignoreBtn.click(function () {
                reset_registration_fields();
                show_registration_fields();
            });
        }

        function load_registration_details_section(class_id, student_id) {
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.class_qualification_action,
                    class_id: class_id,
                    student_id: student_id,
                    security: usctdp_mgmt_admin.class_qualification_nonce,
                },
                success: function (response) {
                    var current_size = response.data.registered;
                    var max_size = response.data.capacity;
                    $('#class-current-size').text(current_size);
                    $('#class-max-size').text(max_size);
                    $('#one-day-price').text("$" + response.data.one_day_price);
                    $('#two-day-price').text("$" + response.data.two_day_price);

                    if (response.data.student_registered) {
                        set_notification('The selected student is already registered for this class.', false);
                    } else if (current_size >= max_size) {
                        set_notification('This class is currently full.', true);
                    } else {
                        reset_registration_fields();
                        show_registration_fields();
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                }
            });
        }

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

        $('#class-selector').on('change', function () {
            const selectedStudent = $('#student-selector').val();
            const selectedClass = this.value;

            reset_registration_fields();
            if (selectedClass && selectedStudent) {
                load_registration_details_section(selectedClass, selectedStudent);
            } else {
                hide_registration_fields();
            }
        });

        $('#student-selector').on('change', function () {
            const selectedStudent = this.value;
            const selectedClass = $('#class-selector').val();

            reset_registration_fields();

            $('#student-name-history').text('');
            $('#registration-history-section').hide();
            if (selectedStudent) {
                const student_name = $('#student-selector').find(':selected').text();
                $('#student-name-history').text(student_name);
                $('#registration-history-section').show();
                registration_history_table.ajax.reload();

                if (selectedClass) {
                    load_registration_details_section(selectedClass, selectedStudent);
                } else {
                    hide_registration_fields();
                }
            } else {
                $('#student-name-history').text('');
                $('#registration-history-section').hide();
                hide_registration_fields();
            }
        });

        $('#payment-method').on('change', function () {
            if (this.value === 'check') {
                $('#check-number-field').show();
            } else {
                $('#check-number-field').hide();
            }
        });
    });
})(jQuery);