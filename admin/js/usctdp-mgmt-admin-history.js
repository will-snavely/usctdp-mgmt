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
            if (usctdp_mgmt_admin.preload.student_id) {
                preloadedData.student = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                contextData['family-selector'] = preloadedData.student.family_id;
                contextData['student-selector'] = preloadedData.student.student_id;
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
                    options: [],
                    choose: function () {
                        return null;
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

                const student = $('#student-selector').val()
                if (student) {
                    load_registration_history(student)
                } else {
                    $('#history-container').addClass("hidden");
                }
            });
        }

        function createRegistrationCard(data) {
            const container = document.createElement('div');
            container.className = 'registration-card';

            // 1. Safety check for transactions
            // We grab the first transaction if it exists; otherwise, we return null
            const firstTxn = data.txns && data.txns.length > 0 ? data.txns[0] : null;

            // 2. Logic for Balance Display
            const balance = parseFloat(data.registration_balance || 0);
            const isPaid = balance <= 0;
            const statusText = isPaid ? 'Fully Paid' : `Balance: $${balance}`;

            // 3. Logic for Transaction ID Display
            // If firstTxn is null, display "N/A"
            const txnIdDisplay = firstTxn?.paypal_transaction_id || "No Payment Record";

            container.innerHTML = `
                <div class="card-header">
                    <span class="student-name">${data.student_first} ${data.student_last}</span>
                    <span class="student-meta"> Age: ${data.student_age} | Level ${data.registration_starting_level}</span>
                </div>
                
                <div class="class-info">
                    <span class="class-label">Enrolled Class</span>
                    <span class="class-name"><strong>${data.class_name}</strong></span>
                    <span class="session-name"><strong>${data.session_name}</strong></span>
                </div>

                <div class="payment-status">
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 0.7rem; color: #95a5a6; text-transform: uppercase;">Transactions</span>
                        <small style="color:#7f8c8d">${txnIdDisplay}</small>
                    </div>
                    <span class="${isPaid ? 'status-paid' : 'status-unpaid'}">${statusText}</span>
                </div>
            `;

            return container;
        }

        function createNotesEditor(data) {
            const container = document.createElement('div');
            container.className = 'notes-container';

            // Extract existing notes or provide an empty string
            const existingNotes = data.registration_notes || "";

            container.innerHTML = `
                <textarea 
                    rows=10,
                    id="note-field-${data.registration_id}" 
                    class="notes-textarea" 
                >${existingNotes}</textarea>
                <button class="button save-btn" id="save-btn-${data.registration_id}">
                    Save Notes
                </button>
            `;

            // Event Listener for the Save Button
            const btn = container.querySelector(`#save-btn-${data.registration_id}`);
            const textarea = container.querySelector(`#note-field-${data.registration_id}`);

            btn.addEventListener('click', () => {
                const updatedText = textarea.value;
                btn.innerText = "Saving...";
                btn.disabled = true;
                console.log(`Saving notes for ID ${data.registration_id}:`, updatedText);
                setTimeout(() => {
                    btn.innerText = "Saved!";
                    btn.style.background = "#27ae60";

                    // Reset button after 2 seconds
                    setTimeout(() => {
                        btn.innerText = "Save Notes";
                        btn.style.background = "#3498db";
                        btn.disabled = false;
                    }, 2000);
                }, 800);
            });

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
                    var studentId = $('#student-selector').val();
                    d.action = usctdp_mgmt_admin.registration_history_datatable_action;
                    d.security = usctdp_mgmt_admin.registration_history_datatable_nonce;
                    d.student_id = studentId;
                }
            },
            columns: [
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return createRegistrationCard(row);
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
                            return createActionPanel(row);
                        }
                        return '';
                    }
                }
            ],
        });

        function load_registration_history(student_id) {
            historyTable.ajax.reload();
            $('#history-container').removeClass('hidden');
        }

        if (preloadedData.student) {
            load_registration_history(preloadedData.student);
        }
    });
})(jQuery);
