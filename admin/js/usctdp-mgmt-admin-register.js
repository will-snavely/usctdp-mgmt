(function ($) {
    "use strict";

    $(document).ready(function () {
        function createSelector(id, name, label, hidden, disabled, options = []) {
            var classes = 'context-selector-section';
            if (hidden) {
                classes = 'hidden';
            }
            var optionsHtml = '';
            for (const option of options) {
                if ('id' in option && 'name' in option) {
                    optionsHtml += `<option value='${option.id}'>${option.name}</option>`;
                } else {
                    optionsHtml += '<option></option>';
                }
            }
            return `
                <div id='${id}-section' class='${classes}'>
                    <h2 id='${id}-label'> ${label} </h2>
                    <select id='${id}' name='${name}' class='context-selector' ${disabled ? 'disabled' : ''}>
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
        var pageMode = 'none_preloaded';
        var preloadedData = { student: null, class: null };
        if (usctdp_mgmt_admin.preload) {
            if (usctdp_mgmt_admin.preload.student_id && usctdp_mgmt_admin.preload.class_id) {
                pageMode = 'all_preloaded';
            } else if (usctdp_mgmt_admin.preload.student_id) {
                pageMode = 'student_preloaded';
            } else if (usctdp_mgmt_admin.preload.class_id) {
                pageMode = 'class_preloaded';
            }
            if (usctdp_mgmt_admin.preload.student_id) {
                preloadedData.student = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                contextData['family-selector'] = preloadedData.student.family_id;
                contextData['student-selector'] = preloadedData.student.student_id;
            }
            if (usctdp_mgmt_admin.preload.class_id) {
                preloadedData.class = Object.values(usctdp_mgmt_admin.preload.class_id)[0];
                contextData['clinic-class-selector'] = preloadedData.class.class_id;
                contextData['session-selector'] = preloadedData.class.session_id;
                contextData['activity-kind-selector'] = "Clinic";
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
                        'Select a Family',
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
                        'Select a Student',
                        hidden,
                        disabled,
                        options
                    ));
                },
                nextSelector: {
                    options: ['activity-kind-selector'],
                    choose: function () {
                        return 'activity-kind-selector';
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
            'activity-kind-selector': {
                selector: function () {
                    var disabled = false;
                    var hidden = true;
                    var options = [
                        {},
                        { id: 'clinic', name: 'Clinic' },
                        { id: 'tournament', name: 'Tournament' },
                    ];

                    if (preloadedData.class) {
                        options = [
                            { id: 'clinic', name: 'Clinic' }
                        ];
                        disabled = true;
                        hidden = false;
                    } else {
                        hidden = preloadedData.student === null;
                    }

                    return $(createSelector(
                        'activity-kind-selector',
                        'activity_kind',
                        'Select an Activity Kind',
                        hidden,
                        disabled,
                        options
                    ));
                },
                nextSelector: {
                    options: ['session-selector', 'activity-selector'],
                    choose: function () {
                        var val = $('#activity-kind-selector').val();
                        if (val === 'clinic') {
                            return 'session-selector'
                        } else if (val === 'tournament') {
                            return 'activity-selector'
                        }
                    }
                },
                select2Options: function () {
                    return {
                        placeholder: "Select the type of activity...",
                        allowClear: true
                    };
                }
            },
            'tournament-selector': {
                selector: function () {
                    return $(createSelector(
                        'tournament-selector',
                        'tournament_id',
                        'Select a Tournament',
                        true,
                        false,
                        []
                    ))
                },
                nextSelector: {
                    options: ['activity-selector'],
                    choose: function () {
                        return 'activity-selector'
                    }
                },
                select2Options: function () {
                    return defaultSelect2Options(
                        "Search for a tournament...",
                        usctdp_mgmt_admin.select2_tournament_search_action,
                        usctdp_mgmt_admin.select2_tournament_search_nonce
                    );
                },
            },
            'session-selector': {
                selector: function () {
                    var options = [];
                    var hidden = true;
                    var disabled = false;
                    if (preloadedData.class) {
                        options.push({
                            id: preloadedData.class.session_id,
                            name: preloadedData.class.session_name
                        });
                        hidden = false;
                        disabled = true;
                    }
                    return $(createSelector(
                        'session-selector',
                        'session_id',
                        'Select a Session',
                        hidden,
                        disabled,
                        options
                    ));
                },
                nextSelector: {
                    options: ['clinic-class-selector', 'tournament-class-selector'],
                    choose: function () {
                        return 'clinic-class-selector'
                    }
                },
                select2Options: function () {
                    if (preloadedData.class) {
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
            'clinic-class-selector': {
                selector: function () {
                    var options = [];
                    var hidden = true;
                    var disabled = false;
                    if (preloadedData.class) {
                        options.push({
                            id: preloadedData.class.class_id,
                            name: preloadedData.class.class_name
                        });
                        hidden = false;
                        disabled = true;
                    }
                    return $(createSelector(
                        'clinic-class-selector',
                        'class_id',
                        'Select a Clinic',
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
                    if (preloadedData.class) {
                        return {
                            placeholder: "Select a clinic...",
                            allowClear: true
                        };
                    } else {
                        return defaultSelect2Options(
                            "Search for a clinic...",
                            usctdp_mgmt_admin.select2_class_search_action,
                            usctdp_mgmt_admin.select2_class_search_nonce,
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
                console.log(contextData);
                if (contextData['student-selector'] && contextData['clinic-class-selector']) {
                    load_class_registration(contextData['clinic-class-selector'], contextData['student-selector']);
                } else {
                    toggle_registration_fields(false);
                }
            });
        }

        function toggle_registration_fields(visible) {
            if (visible) {
                $('#registration-details-section').removeClass('hidden');
            } else {
                $('#registration-details-section').addClass('hidden');
            }
        }

        function reset_registration_fields() {
            $('.form-field input').val('');
            $('.form-field select').val(null);
            $('#check-fields').addClass('hidden');
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

        function load_class_registration(class_id, student_id) {
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

        $('#payment-method').on('change', function () {
            if (this.value === 'check') {
                $('#check-fields').removeClass('hidden');
            } else {
                $('#check-fields').addClass('hidden');
            }
        });

        $('form').on('submit', function () {
            $('.context-selector').prop('disabled', false);
        });
    });
})(jQuery);