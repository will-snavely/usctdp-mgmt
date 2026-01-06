(function ($) {
    "use strict";

    $(document).ready(function () {
        function createSelectSection(kind, label, hidden = false) {
            var classes = '';
            if (hidden) {
                classes = 'hidden';
            }
            return `
                <div id='${kind}-selection-section' class='${classes}'>
                    <h2 id='${kind}-selector-label'> Select a ${label} </h2>
                    <select id='${kind}-selector' name='${kind}_id'></select>
                </div>`;
        }

        var $studentSelector = $(createSelectSection('student', 'Student', false));
        var $sessionSelector = $(createSelectSection('session', 'Session', false));
        var $classSelector = $(createSelectSection('class', 'Class', true));

        if (usctdp_mgmt_admin.preloaded_class_name) {
            $('#context-selection-section').append($sessionSelector);
            $('#context-selection-section').append($classSelector);
            $('#context-selection-section').append($studentSelector);
        } else {
            $('#context-selection-section').append($studentSelector);
            $('#context-selection-section').append($sessionSelector);
            $('#context-selection-section').append($classSelector);
        }

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
                        tag: 'active',
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
            $('#student-selector-label').text('Student:');
            $('#student-selector').append(newOption);
            $('#student-selector').val(usctdp_mgmt_admin.preloaded_student_id);
            $('#session-selector').trigger('change');
            $('#student-selector').prop('disabled', true);
        }

        if (usctdp_mgmt_admin.preloaded_session_name) {
            const newOption = new Option(
                usctdp_mgmt_admin.preloaded_session_name,
                usctdp_mgmt_admin.preloaded_session_id,
                true,
                true
            );
            $('#session-selector-label').text('Session:');
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
            $('#class-selector-label').text('Class:');
            $('#class-selector').append(newOption);
            $('#class-selector').val(usctdp_mgmt_admin.preloaded_class_id);
            $('#class-selector').trigger('change');
            $('#class-selector').prop('disabled', true);
            $('#class-selection-section').removeClass('hidden');
        }

        var registration_history_table = $('#registration-history-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var studentFilterValue = $('#student-selector').val();
                    d.action = usctdp_mgmt_admin.datatable_registrations_action;
                    d.security = usctdp_mgmt_admin.datatable_registrations_nonce;
                    d.student_id = studentFilterValue;
                }
            },
            columns: [
                {
                    data: 'activity',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.name) {
                            return data.name;
                        }
                        return data;
                    },
                    defaultContent: '',
                },
                {
                    data: 'activity',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.session) {
                            return data.session;
                        }
                        return data;
                    },
                    defaultContent: '',
                },
                {
                    data: 'starting_level',
                    defaultContent: '',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.starting_level) {
                            return data.starting_level;
                        }
                        return data;
                    }
                }
            ]
        });

        if (usctdp_mgmt_admin.preloaded_student_name) {
            const student_name = usctdp_mgmt_admin.preloaded_student_name;
            $('#student-name-history').text(student_name);
            $('#registration-history-section').show();
            registration_history_table.ajax.reload();
        }

        var class_roster_table = $('#class-roster-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var classFilterValue = $('#class-selector').val();
                    d.action = usctdp_mgmt_admin.datatable_registrations_action;
                    d.security = usctdp_mgmt_admin.datatable_registrations_nonce;
                    d.class_id = classFilterValue;
                }
            },
            columns: [
                {
                    data: 'student',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.first_name) {
                            return data.first_name;
                        }
                        return data;
                    },
                    defaultContent: '',
                },
                {
                    data: 'student',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.last_name) {
                            return data.last_name;
                        }
                        return data;
                    },
                    defaultContent: '',
                },
                {
                    data: 'starting_level',
                    render: function (data, type, row) {
                        if (type === 'display' && data && data.starting_level) {
                            return data.starting_level;
                        }
                        return data;
                    },
                    defaultContent: '',
                }
            ]
        });

        if (usctdp_mgmt_admin.preloaded_class_name) {
            const class_name = usctdp_mgmt_admin.preloaded_class_name;
            $('#class-roster-name').text(class_name);
            toggle_class_roster(true);
            class_roster_table.ajax.reload();
        }

        function reset_registration_fields() {
            $('#payment-amount-outstanding').val('');
            $('#payment-amount-paid').val('');
            $('#check-number-field').hide();
            $('#check-number').val('');
            $('#payment-method').val('');
            $('#notes').val('');
            $('#notifications-section').children().remove();
        }

        function toggle_registration_fields(visible) {
            if (visible) {
                $('#registration-details-section').removeClass('hidden');
            } else {
                $('#registration-details-section').addClass('hidden');
            }
        }

        function toggle_registration_history(visible) {
            if (visible) {
                $('#registration-history-section').removeClass('hidden');
            } else {
                $('#registration-history-section').addClass('hidden');
            }
        }

        function toggle_class_roster(visible) {
            if (visible) {
                $('#class-roster-section').removeClass('hidden');
            } else {
                $('#class-roster-section').addClass('hidden');
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
            }
            $('#notifications-section').append($notification);

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
                    $('#student-level').val(response.data.student_level).trigger('change');
                    $('#class-level').text(response.data.class_level);

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
                        toggle_registration_fields(true);
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
                $('#class-selection-section').addClass('hidden');
            } else {
                $('#class-selection-section').removeClass('hidden');
            }
            $('#class-selector').val(null);
            $('#class-selector').trigger('change');
        });

        $('#class-selector').on('change', function () {
            const selectedStudent = $('#student-selector').val();
            const selectedClass = this.value;

            reset_registration_fields();
            $('#class-roster-name').text('');
            toggle_class_roster(false);
            if (selectedClass && selectedStudent) {
                load_registration_details_section(selectedClass, selectedStudent);
                const class_name = $('#class-selector').find(':selected').text();
                $('#class-roster-name').text(class_name);
                toggle_class_roster(true);
                class_roster_table.ajax.reload();
            } else {
                toggle_registration_fields(false);
                toggle_class_roster(false);
            }
        });

        $('#student-selector').on('change', function () {
            const selectedStudent = this.value;
            const selectedClass = $('#class-selector').val();

            reset_registration_fields();
            $('#student-name-history').text('');
            toggle_registration_history(false);
            if (selectedStudent) {
                const student_name = $('#student-selector').find(':selected').text();
                $('#student-name-history').text(student_name);
                toggle_registration_history(true);
                registration_history_table.ajax.reload();

                if (selectedClass) {
                    load_registration_details_section(selectedClass, selectedStudent);
                } else {
                    toggle_registration_fields(false);
                }
            } else {
                $('#student-name-history').text('');
                toggle_registration_history(false);
                toggle_registration_fields(false);
            }
        });

        $('#payment-method').on('change', function () {
            if (this.value === 'check') {
                $('#check-number-field').show();
            } else {
                $('#check-number-field').hide();
            }
        });

        $('form').on('submit', function () {
            $('#student-selector').prop('disabled', false);
            $('#session-selector').prop('disabled', false);
            $('#class-selector').prop('disabled', false);
        });
    });
})(jQuery);