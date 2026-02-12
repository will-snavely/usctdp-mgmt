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
        var preloadedData = { student: null, family: null };
        if (usctdp_mgmt_admin.preload) {
            if (usctdp_mgmt_admin.preload.student_id) {
                preloadedData.student = Object.values(usctdp_mgmt_admin.preload.family_id)[0];
                contextData['family-selector'] = preloadedData.student.family_id;
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
                    options: [],
                    choose: function () {
                        return null; 
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

                const family = $('#family-selector').val()
                if (family) {
                    load_registration_history();
                } else {
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

        function createActivityDetails(data, idx) {
            const container = document.createElement('div');
            const sessionSelectId = `session-selector-${idx}`;
            const activitySelectId = `activity-selector-${idx}`;
            container.className = 'activity-details-wrap';
            container.innerHTML = `
                <div class="session-selector-wrap activity-field">
                    <label>Session:</label>
                    <span id="session-name-${idx}">${data.session_name}</span>
                    <div id="session-selector-wrap-${idx}" class="hidden">
                        <select id="${sessionSelectId}"></select> 
                    </div>
                </div>
                <div class="activity-selector-wrap activity-field">
                    <label>Activity:</label>
                    <span id="activity-name-${idx}" class="activty-name">${data.activity_name}</span>
                    <div id="activity-selector-wrap-${idx}" class="hidden">
                        <select id="${activitySelectId}"></select>
                    </div>
                </div>
                <div class="activity-student-level-wrap activity-field">
                    <label>Stu. Level:</label>
                    <span id="student-level-${idx}">${data.registration_starting_level}</span>
                    <input id="student-level-input-${idx}" class="hidden">
                    </input>
                </div> 
                <div class="activity-actions">
                    <button id="edit-activity-${idx}" class="button" data-state="edit">Edit</button>
                </div>
            `;

            container.querySelector(`#edit-activity-${idx}`).addEventListener('click', () => {
                const $button = $(`#edit-activity-${idx}`);
                const state = $button.data("state");
                if(state == "edit") {
                    $button.text("Save");
                    $button.data("state", "save");
                    const $sessionSelect = $('#' + sessionSelectId);
                    if(!$sessionSelect.hasClass("select2-hidden-accessible")) {
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
                    } 

                    const $activitySelect = $('#' + activitySelectId);
                    if(!$activitySelect.hasClass("select2-hidden-accessible")) {
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
                    $(`#session-selector-wrap-${idx}`).removeClass("hidden");
                    $(`#activity-selector-wrap-${idx}`).removeClass("hidden");
                    $(`#student-level-input-${idx}`).removeClass("hidden");
                    $(`#activity-student-level-input-${idx}`).removeClass("hidden");
                    $(`#student-level-${idx}`).addClass("hidden");
                    $(`#activity-name-${idx}`).addClass("hidden");
                    $(`#session-name-${idx}`).addClass("hidden");
                } else {
                    $button.text("Edit");
                    $button.data("state", "edit");
                    $(`#session-selector-wrap-${idx}`).addClass("hidden");
                    $(`#activity-selector-wrap-${idx}`).addClass("hidden");
                    $(`#student-level-input-${idx}`).addClass("hidden");
                    $(`#activity-student-level-input-${idx}`).addClass("hidden");
                    $(`#student-level-${idx}`).removeClass("hidden");
                    $(`#activity-name-${idx}`).removeClass("hidden");
                    $(`#session-name-${idx}`).removeClass("hidden");
                }
            });
            return container;
        }

        function createBalanceDetails(data, idx) {
            const container = document.createElement('div');
            container.className = 'balance-details-wrap';
            container.innerHTML = `
                <div class="credit-wrap balance-field">
                    <label>Credit:</label>
                    <span id="credit-amt-${idx}">${data.registration_credit}</span>
                    <input id="credit-amt-input-${idx}" class="hidden">
                </div>
                <div class="debit-wrap balance-field">
                    <label for="debit-amt-${idx}">Debit:</label>
                    <span id="debit-amt-${idx}">${data.registration_debit}</span>
                    <input id="debit-amt-input-${idx}" class="hidden">
                </div>
                <div class="total-wrap">
                    <span class="total-label">Total:
                        <span id="total-amt-${idx}"></span>
                    </span>
                </div>
                <div class="activity-actions">
                    <button id="edit-balance-${idx}" class="button" data-state="edit">Edit</button>
                </div>
            `;

            container.querySelector(`#edit-balance-${idx}`).addEventListener('click', () => {
                const $button = $(`#edit-balance-${idx}`);
                const state = $button.data("state");
                if(state == "edit") {
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

        function createActionPanel(data) {
            const container = document.createElement('div');
            container.className = 'action-panel';

            container.innerHTML = `
                <button class="action-btn btn-credit" id="btn-credit-${data.registration_id}">
                    Add Credit
                </button>
                <button class="action-btn btn-txn" id="btn-txn-${data.registration_id}">
                    Add Payment
                </button>
            `;

            // Add Credit Logic
            container.querySelector(`#btn-credit-${data.registration_id}`).addEventListener('click', () => {
                console.log(`Opening Credit Modal for Reg ID: ${data.registration_id}`);
                // Your logic here, e.g., openCreditModal(data.registration_id);
            });

            // Add Transaction Logic
            container.querySelector(`#btn-txn-${data.registration_id}`).addEventListener('click', () => {
                console.log(`Opening Transaction Modal for Reg ID: ${data.registration_id}`);
                // Your logic here, e.g., openTxnModal(data.registration_id);
            });

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
        });

        function load_registration_history() {
            historyTable.ajax.reload();
            $('#history-container').removeClass('hidden');
        }

        if (preloadedData.student) {
            load_registration_history(preloadedData.student);
        }
    });
})(jQuery);
