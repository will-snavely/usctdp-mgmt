(function ($) {
    "use strict";

    $(document).ready(function () {
        var preloadedData = {};

        function refreshFamilyBalance(family_id, student_id) {
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.get_family_balance_action,
                    security: usctdp_mgmt_admin.get_family_balance_nonce,
                    family_id: family_id,
                    student_id: student_id
                },
                success: function (response) {
                    $('#family-total-balance').text(USCTDP_Admin.formatUsd(response.data.balance));
                    if (response.data.balance >= 0) {
                        $('#family-total-balance').addClass('balance-red');
                        $('#family-total-balance').removeClass('balance-green');
                    } else {
                        $('#family-total-balance').addClass('balance-green');
                        $('#family-total-balance').removeClass('balance-red');
                    }
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
            $selectElem.select2(
                USCTDP_Admin.select2Options({
                    placeholder: "Search for a session...",
                    allowClear: true,
                    target: 'session'
                })
            );
        }

        function initActivitySelector($selectElem, sessionSelectId) {
            $selectElem.select2(
                USCTDP_Admin.select2Options({
                    placeholder: "Search for an activity...",
                    allowClear: true,
                    target: 'activity'
                })
            );
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

                            const family_id = $('#family-selector').val();
                            var student_id = null;
                            if (preloadedData.student) {
                                student_id = preloadedData.student.student_id;
                            }
                            refreshFamilyBalance(family_id, student_id);
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
            const existingNotes = data.registration_notes || "";
            container.innerHTML = `
                <textarea 
                    rows=6
                    id="note-field-${idx}" 
                    class="notes-textarea">${existingNotes}</textarea>
                <div class="notes-actions">
                    <button class="button save-btn save-notes-btn" id="save-notes-btn-${idx}">
                        Save Notes
                    </button>
                    <div id="save-notes-status-${idx}" class="notes-status">
                        <span id="save-notes-success-${idx}" class="hidden success">
                            Notes Saved!
                        </span>
                    </div>
                </div>
            `;

            container.querySelector(`#save-notes-btn-${idx}`).addEventListener('click', () => {
                const $button = $(`#save-notes-btn-${idx}`);
                $button.prop('disabled', true);
                $button.text("Saving...");
                $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: usctdp_mgmt_admin.save_registration_fields_action,
                        security: usctdp_mgmt_admin.save_registration_fields_nonce,
                        id: data.registration_id,
                        notes: $('#note-field-' + idx).val()
                    },
                    success: function (response) {
                        $(`#save-notes-success-${idx}`).removeClass('hidden');
                        setTimeout(() => {
                            $(`#save-notes-success-${idx}`).addClass('hidden');
                        }, 3000);
                    },
                    error: function (error) {
                        alert("Update failed!");
                    },
                    complete: function () {
                        $button.prop('disabled', false);
                        $button.text("Save Notes");
                    }
                });
            });
            return container;
        }

        var historyTable = $('#history-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            paging: true,
            searching: false,
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
                        d.student_id = preloadedData.student.student_id;
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

                    if ($('#owes-filter').is(':checked')) {
                        d.owes = 1;
                    } else {
                        d.owes = 0;
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
                    $("#owes-filter").on('change', function () {
                        historyTable.ajax.reload();
                    });
                }
            }
        });

        $('#student-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a student...",
                allowClear: true,
                target: 'student'
            })
        );

        $('#session-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a session...",
                allowClear: true,
                target: 'session'
            })
        );

        function load_registration_history(title, family_id, student_id) {
            historyTable.ajax.reload();
            refreshFamilyBalance(family_id, student_id);
            $('#family-name').text(title);
            $('#history-container').removeClass('hidden');
        }

        const selectorConfig = {
            'family-selector': {
                name: 'family_id',
                label: 'Family',
                target: 'family',
                next: null,
                isRoot: true
            },
        };

        const selectHandler = new USCTDP_Admin.CascasdingSelect('context-selectors', selectorConfig);

        $('#context-selectors').on('cascade:change', function (e) {
            const { selectorId, value, state } = e.detail;
            if(value) {
                $('#session-filter').val(null).trigger('change');
                $('#student-filter').val(null).trigger('change');
                load_registration_history(
                    $('#family-selector').find('option:selected').text(),
                    value,
                    $('#student-filter').val()
                );
            } else {
                $('#session-filter').val(null).trigger('change');
                $('#student-filter').val(null).trigger('change');
                $('#history-container').addClass("hidden");
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
            }

            if (usctdp_mgmt_admin.preload.student_id) {
                const preloadedStudent = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                preloadedData['family-selector'] = {
                    id: preloadedStudent.family_id,
                    text: preloadedFamily.family_name,
                    disable: true
                }
                preloadedData['student-selector'] = {
                    id: preloadedStudent.student_id,
                    text: preloadedFamily.student_name,
                }
                $('#student-filter').prop('disabled', true);
                $('#student-filter-section').addClass('hidden');
            }
        }

        if (preloadedData['student-selector']) {
            $('#context-selectors').addClass('hidden');
            /*
            load_registration_history(
                preloadedData.student.student_name,
                preloadedData.student.family_id,
                preloadedData.student.student_id
            );
            */
        } else if (preloadedData['family-selector']) {
            $('#context-selectors').addClass('hidden');
            /*
            load_registration_history(
                preloadedData.family.title,
                preloadedData.family.id,
                null
            );
            */
        }
    });
})(jQuery);
