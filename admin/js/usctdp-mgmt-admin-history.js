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
        var preloadedData = { family: null, student: null };
        if (usctdp_mgmt_admin.preload) {
            if (usctdp_mgmt_admin.preload.family_id) {
                preloadedData.family = Object.values(usctdp_mgmt_admin.preload.family_id)[0];
                contextData['family-selector'] = preloadedData.family.id;
            }

            if (usctdp_mgmt_admin.preload.student_id) {
                preloadedData.student = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                preloadedData.family = {
                    id: preloadedData.student.family_id,
                    title: preloadedData.student.family_name
                }
                contextData['family-selector'] = preloadedData.student.family_id;

                $('#student-filter').prop('disabled', true);
                $('#student-filter-section').addClass('hidden');

            }
        }

        var contextSelectors = {
            'family-selector': {
                selector: function () {
                    var options = [];
                    var hidden = false;
                    var disabled = false;
                    if (preloadedData.family) {
                        options.push({
                            id: preloadedData.family.id,
                            name: preloadedData.family.title
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
                    options: [],
                    choose: function () {
                        return null;
                    },
                },
                select2Options: function () {
                    if (preloadedData.family) {
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

                const family = $('#family-selector').val();
                if (family) {
                    $('#session-filter').val(null).trigger('change');
                    $('#student-filter').val(null).trigger('change');
                    load_registration_history();
                } else {
                    $('#session-filter').val(null).trigger('change');
                    $('#student-filter').val(null).trigger('change');
                    $('#history-container').addClass("hidden");
                }
            });
        }

        function createStudentDetails(data) {
            const container = document.createElement('div');
            container.className = 'student-details-wrap';
            container.innerHTML = `
                <span class="student-name">${data.student_first} ${data.student_last}</span>
                <div class="student-meta">
                    <span class="student-age"> Age: ${data.student_age}</span>
                </div>
            `;

            return container;
        }

        function initSessionSelector($selectElem) {
            $selectElem.select2({
                placeholder: "Search for a session...",
                ajax: {
                    url: usctdp_mgmt_admin.ajax_url,
                    data: function (params) {
                        return {
                            q: params.term,
                            action: usctdp_mgmt_admin.select2_session_search_action,
                            security: usctdp_mgmt_admin.select2_session_search_nonce
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.items
                        };
                    }
                }
            });
        }

        function initActivitySelector($selectElem, sessionSelectId) {
            $selectElem.select2({
                placeholder: "Search for an activity...",
                ajax: {
                    url: usctdp_mgmt_admin.ajax_url,
                    data: function (params) {
                        var selectedSession = $('#' + sessionSelectId).val();
                        return {
                            q: params.term,
                            session_id: selectedSession,
                            action: usctdp_mgmt_admin.select2_activity_search_action,
                            security: usctdp_mgmt_admin.select2_activity_search_nonce
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.items
                        };
                    }
                }
            });
        }

        function saveRegistrationFields(id, fields) {
            const changedData = {};
            Object.entries(fields).forEach(([elemId, fieldName]) => {
                const curValue = $('#' + elemId).val().trim();
                const origValue = $('#' + elemId).data('orig-value').trim();
                if (curValue !== origValue) {
                    changedData[fieldName] = curValue;
                }
            });
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.save_registration_fields_action,
                    security: usctdp_mgmt_admin.save_registration_fields_nonce,
                    id: id,
                    ...changedData
                },
                success: function (responseData) {
                    Object.entries(fields).forEach(([divId, fieldName]) => {
                        if (fieldName in changedData) {

                        }
                    });
                },
                error: function (xhr, status, error) {
                    alert("Update failed!");
                },
                complete: function () {

                }
            });
        }

        function createActivityDetails(data, idx) {
            const container = document.createElement('div');
            const sessionSelectId = `session-selector-${idx}`;
            const activitySelectId = `activity-selector-${idx}`;
            container.className = 'activity-details-wrap';
            container.innerHTML = `
                <div class="session-selector-wrap activity-field">
                    <label>Session:</label>
                    <span id="session-name-${idx}" class="view-mode">
                        ${data.session_name}
                    </span>
                    <div id="session-selector-wrap-${idx}" class="hidden edit-mode">
                        <select id="${sessionSelectId}" data-orig-value="${data.session_id}"></select>
                    </div>
                </div>
                <div class="activity-selector-wrap activity-field">
                    <label>Activity:</label>
                    <span id="activity-name-${idx}" class="view-mode">
                        ${data.activity_name}
                    </span>
                    <div id="activity-selector-wrap-${idx}" class="hidden edit-mode">
                        <select id="${activitySelectId}" data-orig-value="${data.activity_id}"></select>
                    </div>
                </div>
                <div class="student-level-wrap activity-field">
                    <label>Level:</label>
                    <span id="student-level-${idx}" class="view-mode">
                        ${data.registration_student_level}
                    </span>
                    <input 
                        id="student-level-input-${idx}" 
                        class="hidden edit-mode" 
                        data-orig-value="${data.registration_student_level}" 
                        value="${data.registration_student_level}">
                </div>
                <div class="activity-actions">
                    <button id="edit-activity-${idx}" class="button" data-state="edit">Edit</button>
                </div>
            `;

            container.querySelector(`#edit-activity-${idx}`).addEventListener('click', () => {
                const $button = $(`#edit-activity-${idx}`);
                const state = $button.data("state");
                if (state == "edit") {
                    $button.text("Save");
                    $button.data("state", "save");
                    const $sessionSelect = $('#' + sessionSelectId);
                    if (!$sessionSelect.hasClass("select2-hidden-accessible")) {
                        initSessionSelector($sessionSelect);
                        const curSession = new Option(
                            data.session_name,
                            data.session_id,
                            true,
                            true
                        );
                        $('#' + sessionSelectId)
                            .append(curSession)
                            .val(data.session_id)
                            .trigger("change");
                        $('#' + sessionSelectId).on('change', function () {
                            $('#' + activitySelectId).val(null).trigger("change");
                        });
                    }

                    const $activitySelect = $('#' + activitySelectId);
                    if (!$activitySelect.hasClass("select2-hidden-accessible")) {
                        initActivitySelector($activitySelect, sessionSelectId);
                        const curActivity = new Option(
                            data.activity_name,
                            data.activity_id,
                            true,
                            true
                        );
                        $('#' + activitySelectId)
                            .append(curActivity)
                            .val(data.activity_id)
                            .trigger("change");
                    }
                    $(this).find('.view-mode').addClass('hidden');
                    $(this).find('.edit-mode').removeClass('hidden');
                } else {
                    saveRegistrationFields(data.registration_id, {
                        [`student-level-input-${idx}`]: 'student_level',
                        [activitySelectId]: 'activity_id',
                        [sessionSelectId]: 'session_id'
                    });
                    $button.text("Edit");
                    $button.data("state", "edit");
                    $(this).find('.view-mode').removeClass('hidden');
                    $(this).find('.edit-mode').addClass('hidden');
                }
            });
            return container;
        }

        function createBalanceDetails(data, idx) {
            const container = document.createElement('div');
            container.className = 'balance-details-wrap';
            const total = data.registration_credit - data.registration_debit;
            container.innerHTML = `
                <div class="credit-wrap balance-field">
                    <label>Credit:</label>
                    <span id="credit-amt-${idx}">${data.registration_credit}</span>
                    <input id="credit-amt-input-${idx}" class="hidden">
                </div>
                <div class="debit-wrap balance-field">
                    <label>Debit:</label>
                    <span id="debit-amt-${idx}">${data.registration_debit}</span>
                    <input id="debit-amt-input-${idx}" class="hidden">
                </div>
                <div class="total-wrap balance-field">
                    <label>Total:</label>
                    <span id="total-amt-${idx}">${total}</span>
                </div>
                <div class="activity-actions">
                    <button id="edit-balance-${idx}" class="button" data-state="edit">Edit</button>
                </div>
            `;

            container.querySelector(`#edit-balance-${idx}`).addEventListener('click', () => {
                const $button = $(`#edit-balance-${idx}`);
                const state = $button.data("state");
                if (state == "edit") {
                    $button.text("Save");
                    $button.data("state", "save");
                    $(`#credit-amt-input-${idx}`).removeClass("hidden");
                    $(`#debit-amt-input-${idx}`).removeClass("hidden");
                    $(`#credit-amt-${idx}`).addClass("hidden");
                    $(`#debit-amt-${idx}`).addClass("hidden");
                } else {
                    $button.text("Edit");
                    $button.data("state", "edit");
                    $(`#credit-amt-input-${idx}`).addClass("hidden");
                    $(`#debit-amt-input-${idx}`).addClass("hidden");
                    $(`#credit-amt-${idx}`).removeClass("hidden");
                    $(`#debit-amt-${idx}`).removeClass("hidden");
                }
            });

            return container;
        }

        function createNotesEditor(data, idx) {
            const container = document.createElement('div');
            container.className = 'notes-container';

            // Extract existing notes or provide an empty string
            const existingNotes = data.registration_notes || "";

            container.innerHTML = `
                <textarea 
                    rows=6,
                    id="note-field-${idx}" 
                    class="notes-textarea" 
                >${existingNotes}</textarea>
                <button class="button save-btn" id="save-btn-${idx}">
                    Save Notes
                </button>
            `;

            return container;
        }

        var historyTable = $('#history-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            paging: true,
            info: true,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var familyId = $('#family-selector').val();
                    d.action = usctdp_mgmt_admin.registration_history_datatable_action;
                    d.security = usctdp_mgmt_admin.registration_history_datatable_nonce;
                    d.family_id = familyId;

                    if (preloadedData.student) {
                        d.student_id = preloadedData.student.id;
                    } else {
                        var studentFilterValue = $('#student-filter').val();
                        if (studentFilterValue) {
                            d.student_id = studentFilterValue;
                        }
                    }

                    var sessionFilterValue = $('#session-filter').val();
                    if (sessionFilterValue) {
                        d.session_id = sessionFilterValue;
                    }
                }
            },
            columns: [
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return createStudentDetails(row);
                        }
                        return '';
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row, meta) {
                        if (type === 'display') {
                            return createActivityDetails(row, meta.row);
                        }
                        return '';
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row, meta) {
                        if (type === 'display') {
                            return createBalanceDetails(row, meta.row);
                        }
                        return '';
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return createNotesEditor(row);
                        }
                        return '';
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                        }
                        return '';
                    }
                }
            ],
            initComplete: function () {
                if ($("#table-filter-row").length === 0) {
                    var $table_controls = $('#history-table_wrapper');
                    var $first_row = $table_controls.find("div.dt-layout-row").first();
                    var filter_row = "<div id='table-filter-row' class='dt-layout-row'></div>"
                    $first_row.after(filter_row);
                    $('#table-filters').appendTo('#table-filter-row');
                    $('#session-filter, #student-filter').on('change', function () {
                        historyTable.ajax.reload();
                    });
                }
            }
        });

        $('#student-filter').select2({
            placeholder: "Search for a student...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        action: usctdp_mgmt_admin.select2_student_search_action,
                        security: usctdp_mgmt_admin.select2_student_search_nonce,
                        family_id: $('#family-selector').val()
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        $('#session-filter').select2({
            placeholder: "Search for a session...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        action: usctdp_mgmt_admin.select2_session_search_action,
                        security: usctdp_mgmt_admin.select2_session_search_nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        function load_registration_history(title) {
            historyTable.ajax.reload();
            $('#family-name').text(title);
            $('#history-container').removeClass('hidden');
        }

        if (preloadedData.student) {
            $('#context-selectors').addClass('hidden');
            load_registration_history(preloadedData.student.student_name);
        } else if (preloadedData.family) {
            $('#context-selectors').addClass('hidden');
            load_registration_history(preloadedData.family.title);
        }
    });
})(jQuery);
