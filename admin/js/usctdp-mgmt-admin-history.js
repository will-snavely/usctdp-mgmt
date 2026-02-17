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

        function getElementContent(el) {
            var $el = $(el);
            if ($el.is('select')) {
                return $el.find('option:selected').text();
            }
            return $el.val();
        }

        function resetElementVal($el) {
            const origVal = $el.attr('data-orig-value');
            const origText = $el.attr('data-orig-text');
            if ($el.is('select')) {
                const $option = $el.find('option[value="' + origVal + '"]');
                if ($option.length > 0) {
                    $el.val(origVal).trigger('change', [true]);
                } else {
                    const newVal = new Option(origText, origVal, true, true);
                    $el.append(newVal).val(origVal).trigger('change', [true]);
                }
            } else {
                $el.val(origVal);
            }
        }

        async function saveRegistrationFields(id, fields) {
            const changedData = {};
            const changedText = {};
            for (const field of fields) {
                const curValue = $('#' + field.input).val().trim();
                const origValue = $('#' + field.input).attr('data-orig-value').trim();
                if (curValue !== origValue) {
                    changedData[field.field] = curValue;
                    changedText[field.field] = getElementContent($('#' + field.input)).trim();
                }
            }

            console.log(changedData);
            console.log(changedText);

            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.save_registration_fields_action,
                    security: usctdp_mgmt_admin.save_registration_fields_nonce,
                    id: id,
                    ...changedData
                }
            });

            for (const field of fields) {
                if (field.field in changedText) {
                    $('#' + field.display).text(changedText[field.field]);
                    $('#' + field.input).attr('data-orig-text', changedText[field.field]);
                }
                if (field.field in changedData) {
                    $('#' + field.input).attr('data-orig-value', changedData[field.field]);
                }
            }
        }

        function createActivityDetails(data, idx) {
            const container = document.createElement('div');
            const sessionSelectId = `session-selector-${idx}`;
            const activitySelectId = `activity-selector-${idx}`;
            container.className = 'activity-details-wrap';
            container.id = `activity-details-wrap-${idx}`;
            container.innerHTML = `
                <div class="session-selector-wrap activity-field">
                    <label>Session:</label>
                    <span id="session-name-${idx}" class="view-mode">
                        ${data.session_name}
                    </span>
                    <div id="session-selector-wrap-${idx}" class="hidden edit-mode">
                        <select id="${sessionSelectId}" 
                            data-orig-value="${data.session_id}" 
                            data-orig-text="${data.session_name}">
                        </select>
                    </div>
                </div>
                <div class="activity-selector-wrap activity-field">
                    <label>Activity:</label>
                    <span id="activity-name-${idx}" class="view-mode">
                        ${data.activity_name}
                    </span>
                    <div id="activity-selector-wrap-${idx}" class="hidden edit-mode">
                        <select id="${activitySelectId}" 
                            data-orig-value="${data.activity_id}" 
                            data-orig-text="${data.activity_name}">
                        </select>
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
                        data-orig-text="${data.registration_student_level}"
                        value="${data.registration_student_level}">
                </div>
                <div class="activity-actions">
                    <button id="edit-activity-${idx}" class="button" data-state="edit">Edit</button>
                </div>
            `;

            const fields = [
                {
                    input: `student-level-input-${idx}`,
                    field: 'student_level',
                    display: `student-level-${idx}`
                },
                {
                    input: activitySelectId,
                    field: 'activity_id',
                    display: `activity-name-${idx}`
                },
                {
                    input: sessionSelectId,
                    field: 'session_id',
                    display: `session-name-${idx}`
                }
            ];

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
                        $('#' + sessionSelectId).on('change', function (e, restrict) {
                            if (!restrict) {
                                $('#' + activitySelectId).val(null).trigger("change");
                            }
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

                    $('#activity-details-wrap-' + idx + ' .view-mode').addClass('hidden');
                    $('#activity-details-wrap-' + idx + ' .edit-mode').removeClass('hidden');
                } else {
                    $button.prop('disabled', true);
                    saveRegistrationFields(data.registration_id, fields)
                        .catch((error) => {
                            alert("Update failed!");
                            for (const field of fields) {
                                resetElementVal($('#' + field.input));
                            }
                        })
                        .finally(() => {
                            $button.text("Edit");
                            $button.data("state", "edit");
                            $button.prop('disabled', false);
                            $('#activity-details-wrap-' + idx + ' .view-mode').removeClass('hidden');
                            $('#activity-details-wrap-' + idx + ' .edit-mode').addClass('hidden');
                        });
                }
            });
            return container;
        }

        function createBalanceDetails(data, idx) {
            const container = document.createElement('div');
            container.className = 'balance-details-wrap';
            container.id = `balance-details-wrap-${idx}`;
            const total = data.registration_debit - data.registration_credit;
            container.innerHTML = `

                <div class="debit-wrap balance-field">
                    <label>Debit:</label>
                    <span id="debit-amt-${idx}" class="view-mode">${data.registration_debit}</span>
                    <input 
                        id="debit-amt-input-${idx}" 
                        class="edit-mode hidden"
                        data-orig-value="${data.registration_debit}"
                        data-orig-text="${data.registration_debit}"
                        value="${data.registration_debit}">
                </div>
                <div class="credit-wrap balance-field">
                    <label>Credit:</label>
                    <span id="credit-amt-${idx}" class="view-mode">${data.registration_credit}</span>
                    <input 
                        id="credit-amt-input-${idx}" 
                        class="edit-mode hidden"
                        data-orig-value="${data.registration_credit}"
                        data-orig-text="${data.registration_credit}"
                        value="${data.registration_credit}">
                </div>
                <div class="total-wrap balance-field">
                    <label>Total:</label>
                    <span id="total-amt-${idx}">${total}</span>
                </div>
                <div class="activity-actions">
                    <button id="edit-balance-${idx}" class="button" data-state="edit">Edit</button>
                </div>
            `;

            const fields = [
                {
                    input: `credit-amt-input-${idx}`,
                    field: 'credit',
                    display: `credit-amt-${idx}`
                },
                {
                    input: `debit-amt-input-${idx}`,
                    field: 'debit',
                    display: `debit-amt-${idx}`
                }
            ];

            container.querySelector(`#edit-balance-${idx}`).addEventListener('click', () => {
                const $button = $(`#edit-balance-${idx}`);
                const state = $button.data("state");
                if (state == "edit") {
                    $button.text("Save");
                    $button.data("state", "save");
                    $('#balance-details-wrap-' + idx + ' .view-mode').addClass('hidden');
                    $('#balance-details-wrap-' + idx + ' .edit-mode').removeClass('hidden');
                } else {
                    $button.prop('disabled', true);
                    saveRegistrationFields(data.registration_id, fields)
                        .catch((error) => {
                            alert("Update failed!");
                            for (const field of fields) {
                                resetElementVal($('#' + field.input));
                            }
                        })
                        .finally(() => {
                            $button.text("Edit");
                            $button.data("state", "edit");
                            $button.prop('disabled', false);
                            const debit = $('#debit-amt-input-' + idx).val();
                            const credit = $('#credit-amt-input-' + idx).val();
                            const total = debit - credit;
                            $('#total-amt-' + idx).text(total);
                            $('#balance-details-wrap-' + idx + ' .view-mode').removeClass('hidden');
                            $('#balance-details-wrap-' + idx + ' .edit-mode').addClass('hidden');
                        });
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
                            try {
                                return createActivityDetails(row, meta.row);
                            } catch (error) {
                                console.error(error);
                                return '';
                            }
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
