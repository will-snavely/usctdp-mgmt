(function ($) {
    "use strict";

    $(document).ready(function () {
        var preloadedData = {};
        var newRegistrations = null;

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

        function renderStudentDetails(data, idx) {
            const container = document.createElement('div');
            var newRegBadge = '';
            if (newRegistrations) {
                const registrationId = parseInt(data.registration_id);
                newRegBadge = newRegistrations.has(registrationId) ? '<span class="new-registration">New!</span>' : '';
            }
            container.className = 'student-details-wrap';
            container.innerHTML = `
                <div class="basic-info">
                    <div class="student-name-wrap">
                        <span class="student-name">${data.student_first} ${data.student_last}</span>
                    </div>
                    <div class="student-age-wrap">
                        <span class="student-age">Age: ${data.student_age}</span>
                    </div>
                    <div class="new-registration-badge">
                        ${newRegBadge}
                    </div>
                </div>
                <div class="registration-actions">
                    <button id="edit-activity-${idx}" class="button edit-activity" data-state="edit">Edit Registration</button>
                    <a id="view-order-${idx}" class="button view-order" href="${data.view_order}">View Order</a>
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
                    target: 'activity',
                    filter: function () {
                        return {
                            session_id: $('#' + sessionSelectId).val()
                        }
                    }
                })
            );
        }

        async function saveRegistrationFields(id, fields) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.save_registration_fields_action,
                    security: usctdp_mgmt_admin.save_registration_fields_nonce,
                    registration_id: id,
                    ...fields
                }
            });
            return response;
        }

        function activityDisplayName(name) {
            const replacements = [
                [/^Adult/, ""],
                [/Monday,/, "Mon"],
                [/Tuesday,/, "Tues"],
                [/Wednesday,/, "Wed"],
                [/Thursday,/, "Thurs"],
                [/Friday,/, "Fri"],
                [/Saturday,/, "Sat"],
                [/Sunday,/, "Sun"],
            ];
            return USCTDP_Admin.applyReplacements(name, replacements);
        }

        function renderActivityDetails(data, idx) {
            const {
                sessionName,
                sessionId,
                activityName,
                activityId,
                level,
                debit,
                credit,
                notes
            } = data;
            const total = debit - credit;
            const totalClass = total > 0 ? "balance-red" : "balance-green";
            const sessionSelectId = `session-selector-${idx}`;
            const activitySelectId = `activity-selector-${idx}`;
            return `
              <div class="activity-fields fields-disabled">
                <div class="fields-row">
                    <div class="session-selector-wrap activity-field">
                        <label>Session</label>
                        <div id="session-selector-wrap-${idx}">
                            <select id="${sessionSelectId}" class="session-select" data-orig-value="${sessionId}"
                                data-orig-text="${sessionName}" data-activity-selector-id="${activitySelectId}" disabled>
                                <option value="${sessionId}" selected>${sessionName}</option>
                            </select>
                        </div>
                    </div>
                    <div class="activity-selector-wrap activity-field">
                        <label>Activity</label>
                        <div id="activity-selector-wrap-${idx}">
                            <select id="${activitySelectId}" class="activity-select" data-orig-value="${activityId}"
                                data-orig-text="${activityName}" data-session-selector-id="${sessionSelectId}" disabled>
                                <option value="${activityId}" selected>${activityName}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="fields-row">
                    <div class="short-field-block">
                        <div class="level-wrap activity-field">
                            <label>Level</label>
                            <input id="level-input-${idx}" class="level-input" value="${level}" readonly>
                        </div>
                        <div class="debit-wrap activity-field">
                            <label>Debit</label>
                            <input id="debit-input-${idx}" class="debit-input" value="${debit}" readonly>
                        </div>
                        <div class="credit-wrap activity-field">
                            <label>Credit</label>
                            <input id="credit-input-${idx}" class="credit-input" value="${credit}" readonly>
                        </div>
                        <div class="total-wrap activity-field">
                            <label>Total</label>
                            <span id="total-amt-${idx}" class="total-amt ${totalClass}">${total}</span>
                        </div>
                    </div>
                    <div class="notes-wrap activity-field">
                        <label>Notes</label>
                        <textarea readonly rows=3 id="notes-input-${idx}" class="notes-input">${notes}</textarea>
                    </div>
                </div>
            </div>`;
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
                    render: function (data, type, row, meta) {
                        if (type === 'display') {
                            return renderStudentDetails(row, meta.row);
                        }
                        return '';
                    }
                },
                {
                    data: 'id',
                    render: function (data, type, row, meta) {
                        if (type === 'display') {
                            try {
                                const activityData = {
                                    sessionName: row.session_name,
                                    sessionId: row.session_id,
                                    activityName: row.activity_name,
                                    activityId: row.activity_id,
                                    level: row.registration_student_level,
                                    debit: row.registration_debit,
                                    credit: row.registration_credit,
                                    notes: row.registration_notes
                                };
                                return renderActivityDetails(activityData, meta.row);
                            } catch (error) {
                                console.error(error);
                                return '';
                            }
                        }
                        return '';
                    }
                }
            ],
            autoWidth: false,
            columnDefs: [
                { width: "15%", targets: 0 }, // Student
                { width: "85%", targets: 1 }, // Activity
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
            },

            preDrawCallback: function (settings) {
                var api = this.api();
                $(api.table().body()).find('select').each(function () {
                    if ($(this).hasClass("select2-hidden-accessible")) {
                        $(this).select2('destroy');
                    }
                });
            },

            drawCallback: function (settings) {
                var api = this.api();
                $(api.table().body()).find('.session-select').each(function () {
                    initSessionSelector($(this));
                });
                $(api.table().body()).find('.activity-select').each(function () {
                    initActivitySelector($(this), $(this).data('session-selector-id'));
                });
            }
        });

        $('#student-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a student...",
                allowClear: true,
                target: 'student',
                filter: function () {
                    return {
                        family_id: $('#family-selector').val()
                    }
                }
            })
        );

        $('#session-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Search for a session...",
                allowClear: true,
                target: 'session'
            })
        );

        $('#history-table tbody').on('change', '.session-select', function () {
            const activitySelectId = $(this).data('activity-selector-id');
            $('#' + activitySelectId).val(null).trigger("change");
        });

        $('#history-table tbody').on('click', 'button.edit-activity', function (e) {
            const $row = $(this).closest('tr');
            const $button = $(this);
            const state = $button.data("state");
            var rowData = historyTable.row($row).data();

            if (state == "edit") {
                $button.text("Save");
                $button.data("state", "save");
                $button.addClass('save-btn');
                $row.find('.activity-fields').removeClass('fields-disabled');
                $row.find('.activity-fields').addClass('fields-enabled');
                $row.find('select').prop('disabled', false);
                $row.find('input').prop('readonly', false);
                $row.find('textarea').prop('readonly', false);
            } else {
                $button.text("Edit");
                $button.data("state", "edit");
                $button.removeClass('save-btn');
                $row.find('select').prop('disabled', true);
                $row.find('input').prop('readonly', true);
                $row.find('textarea').prop('readonly', true);
                $button.prop('disabled', true);

                var update = {
                    activity_id: $row.find('.activity-select').first().val(),
                    student_level: $row.find('.level-input').first().val(),
                    debit: $row.find('.debit-input').first().val(),
                    credit: $row.find('.credit-input').first().val(),
                    notes: $row.find('.notes-input').first().val()
                }

                saveRegistrationFields(rowData.registration_id, update)
                    .catch((error) => {
                        alert("Update failed! " + error);
                    })
                    .finally(() => {
                        $button.text("Edit");
                        $button.data("state", "edit");
                        $button.prop('disabled', false);
                        historyTable.ajax.reload();
                    });
            }
        });

        $('#history-table tbody').on('click', 'button.view-order', function (e) {
            const $row = $(this).closest('tr');
            const rowData = historyTable.row($row).data();
            console.log(rowData);
        });

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
            if (value) {
                $('#session-filter').val(null).trigger('change');
                var studentId = null;
                var title = $('#family-selector').find('option:selected').text();
                if (preloadedData['student-selector']) {
                    studentId = preloadedData['student-selector']["id"];
                    title = preloadedData['student-selector']["text"];
                } else {
                    $('#student-filter').val(null).trigger('change');
                }
                load_registration_history(title, value, studentId);
            } else {
                $('#session-filter').val(null).trigger('change');
                $('#student-filter').val(null).trigger('change');
                $('#history-container').addClass("hidden");
            }
        });

        if (usctdp_mgmt_admin.new_registrations) {
            newRegistrations = new Set(usctdp_mgmt_admin.new_registrations)
        }

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
                    text: preloadedStudent.student_name
                }
                $('#student-filter').prop('disabled', true);
                $('#student-filter-section').addClass('hidden');
                $('#context-selectors').addClass('hidden');
            }
            selectHandler.applyData(preloadedData);
        }
    });
})(jQuery);
