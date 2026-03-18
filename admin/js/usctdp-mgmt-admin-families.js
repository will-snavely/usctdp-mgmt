(function ($) {
    "use strict";
    $(document).ready(function () {
        const UI_SCHEMA = [
            {
                section: 'address',
                fields: [
                    { key: 'address', label: 'Street', type: 'text' },
                    { key: 'city', label: 'City', type: 'text' },
                    { key: 'state', label: 'State', type: 'text' },
                    { key: 'zip', label: 'Zip', type: 'text' }
                ]
            },
            {
                section: 'contact',
                fields: [
                    { key: 'phone_numbers', label: 'Phone' },
                    { key: 'email', label: 'email', type: 'text' },
                ]
            },
            {
                section: 'notes',
                fields: [
                    { key: 'notes', label: 'Notes', type: 'textarea' },
                ]
            }
        ];

        async function fetchFamilyFields(family_id) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.get_family_action,
                    security: usctdp_mgmt_admin.get_family_nonce,
                    family_id: family_id
                }
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Unknown error');
            }
        }

        async function updateFamilyFields(familyId, changedData) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.update_family_action,
                    security: usctdp_mgmt_admin.update_family_nonce,
                    family_id: familyId,
                    ...changedData
                },
            });
            if (response.success) {
                return response.data;
            } else {
                throw new Error(response.data || 'Unknown error');
            }
        }

        var pendingChanges = {};
        var currentId = null;
        const queryClient = new window.QueryClient({
            defaultOptions: {
                queries: {
                    queryFn: async ({ queryKey }) => {
                        const [scope, id] = queryKey;
                        if (scope === 'family' && id) {

                            return await fetchFamilyFields(id);
                        }
                        return null;
                    },
                },
            },
        });
        const familyObserver = new window.QueryObserver(queryClient, {
            queryKey: ['family', currentId],
        });

        familyObserver.subscribe(result => {
            if (result.data) {
                try {
                    renderUI(result.data);
                } catch (err) {
                    console.error("Critical Render Error:", err);
                }
            }
        });

        function renderUI(dbData) {
            const container = document.getElementById('fields-container');
            container.innerHTML = UI_SCHEMA.map(section => {
                const fields = section.fields.map(field => {
                    var value = pendingChanges[field.key] !== undefined
                        ? pendingChanges[field.key]
                        : dbData[field.key];
                    const isDirty = pendingChanges.hasOwnProperty(field.key);
                    var inputClasses = ['db-input', `field-${field.key}`];
                    var groupClasses = ['field-group']
                    if (isDirty) {
                        groupClasses.push('is-dirty');
                        inputClasses.push('is-dirty');
                    }

                    var tag = "";
                    if (field.key === 'phone_numbers') {
                        const currentValues = value || [];
                        const inputs = currentValues.map((val, index) => `
                            <div class="phone-row">
                                <input type="tel" class="phone-input" data-index="${index}" value="${val}">
                                <button type="button" class="remove-phone" data-index="${index}">&times;</button>
                            </div>
                        `).join('');

                        tag = `
                            <div class="phone-list">
                                <div id="phone-list">${inputs}</div>
                                <button type="button" id="add-phone">+ Add Number</button>
                            </div>
                        `;
                    } else if (field.type === "textarea") {
                        tag = `
                            <textarea 
                                rows=5
                                class="${inputClasses.join(' ')}" 
                                data-key="${field.key}">${value || ''}</textarea>`;
                    } else {
                        tag = ` 
                            <input
                                type="${field.type}" 
                                class="${inputClasses.join(' ')}" 
                                data-key="${field.key}" 
                                value="${value || ''}">`;
                    }
                    return `
                        <div class="${groupClasses.join(' ')}">
                            <label>${field.label}</label>
                            ${tag}
                        </div>
                    `;
                }).join('');

                return `
                    <div class="field-section">
                        <div class="field-list">
                            ${fields}
                        </div>
                    </div>
                `;
            }).join('');
        }

        $(document).on('input', '.phone-input', function () {
            const allPhones = $('.phone-input').map(function () {
                return $(this).val();
            }).get();
            pendingChanges['phone_numbers'] = allPhones;
            $(this).closest('.field-group').addClass('is-dirty');
            $('#save-btn')
                .prop('disabled', false)
                .text(`Save ${Object.keys(pendingChanges).length} Changes`);
        });

        $(document).on('click', '.remove-phone', function () {
            const indexToRemove = $(this).data('index');
            if (!pendingChanges.hasOwnProperty('phone_numbers')) {
                var phones = queryClient.getQueryData(['family', currentId]).phone_numbers;
                pendingChanges['phone_numbers'] = [...phones];
            }
            pendingChanges['phone_numbers'].splice(indexToRemove, 1);
            renderUI(queryClient.getQueryData(['family', currentId]));
            $('#save-btn')
                .prop('disabled', false)
                .text(`Save ${Object.keys(pendingChanges).length} Changes`);
        });

        $(document).on('input', '.db-input', function () {
            const key = $(this).data('key');
            const val = $(this).val();
            const $parent = $(this).parent().addClass('is-dirty');
            $(this).addClass('is-dirty');
            pendingChanges[key] = val;
            $(this).pprent
            $('#save-btn')
                .prop('disabled', false)
                .text(`Save ${Object.keys(pendingChanges).length} Changes`);
        });

        $(document).on('click', '#add-phone', function () {
            if (!pendingChanges.hasOwnProperty('phone_numbers')) {
                var phones = queryClient.getQueryData(['family', currentId]).phone_numbers;
                pendingChanges['phone_numbers'] = [...phones];

            }
            pendingChanges['phone_numbers'].push('');
            renderUI(queryClient.getQueryData(['family', currentId]));
        });

        // 4. The Batch Save Function
        async function handleBatchSave() {
            if (Object.keys(pendingChanges).length === 0) return;

            const observer = new window.TSMutationObserver(queryClient, {
                mutationFn: async (batch) => {
                    const response = await updateFamilyFields(currentId, batch);
                    if (!response) throw new Error("Server error");
                    return response;
                },
                onSuccess: (updatedFullRecord) => {
                    pendingChanges = {};
                    queryClient.setQueryData(['family', currentId], updatedFullRecord);
                    $('#save-btn').prop('disabled', 'true').text('Save Changes');
                    $('#fields-status-msg').removeClass("error");
                    $('#fields-status-msg').addClass("success");
                    $('#fields-status-msg').text("Update successful");
                    setTimeout(() => {
                        $(`#fields-status-msg`).text('');
                    }, 3000);
                },
                onError: (err) => {
                    $('#save-btn').prop('disabled', 'true').text('Save Changes');
                    $('#fields-status-msg').removeClass("success");
                    $('#fields-status-msg').addClass("error");
                    $('#fields-status-msg').text("Update failed");
                    setTimeout(() => {
                        $(`#fields-status-msg`).text('');
                    }, 3000);
                },
            });

            observer.mutate(pendingChanges);
        }

        $('#save-btn').on('click', handleBatchSave);

        var preloadedData = {};
        const newStudentModal = document.querySelector('#new-student-modal');
        const newFamilyModal = document.querySelector('#new-family-modal');

        var membersTable = $('#family-members-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: false,
            paging: false,
            info: false,
            deferLoading: 0,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.student_datatable_action;
                    d.security = usctdp_mgmt_admin.student_datatable_nonce;
                    d.family_id = currentId;
                }
            },
            columns: [
                { data: 'first' },
                { data: 'last' },
                { data: 'birth_date' },
                { data: 'age' },
                {
                    data: 'id',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            var registerUrl = 'admin.php?page=usctdp-admin-register&student_id=' + data;
                            var historyUrl = 'admin.php?page=usctdp-admin-history&student_id=' + data;
                            return `<div class="family-actions">
                                <div class="action-item">
                                    <a href="${registerUrl}" class="button button-small">Register</a> 
                                    <a href="${historyUrl}" class="button button-small">History</a> 
                                </div>
                            </div>`;
                        }
                        return '';
                    }
                }
            ]
        });

        $('#new-student-button').on('click', (e) => {
            e.preventDefault();
            newStudentModal.showModal();
        });

        $('#close-student-modal').on('click', () => {
            newStudentModal.close();
        });

        $('#save-student-modal').on('click', (e) => {
            const form = $('#new-student-form')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            e.preventDefault();

            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_student',
                    security: usctdp_mgmt_admin.create_student_nonce,
                    family_id: $('#family-selector').val(),
                    first: $('#student_modal_first_name').val(),
                    last: $('#student_modal_last_name').val(),
                    birth_date: $('#student_modal_birthdate').val(),
                    level: $('#student_modal_level').val(),
                },
                success: function (response) {
                    if (response.success) {
                        newStudentModal.close();
                        membersTable.ajax.reload();
                        alert("Student created successfully!");
                    }
                },
                error: function (response) {
                    const responseMessage = response.responseJSON.data;
                    var userMessage = "Failed to create student.\n\n" + responseMessage;
                    userMessage += "\n\nTry again or inform a developer.";
                    alert(userMessage);
                }
            });
        });

        $('#new-family-button').on('click', (e) => {
            e.preventDefault();
            newFamilyModal.showModal();
        });

        $('#close-family-modal').on('click', () => {
            newFamilyModal.close();
        });

        $('#save-family-modal').on('click', (e) => {
            const form = $('#new-family-form')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            e.preventDefault();

            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_family',
                    security: usctdp_mgmt_admin.create_family_nonce,
                    last: $('#family_modal_last_name').val(),
                    address: $('#family_modal_address').val(),
                    city: $('#family_modal_city').val(),
                    state: $('#family_modal_state').val(),
                    zip: $('#family_modal_zip').val(),
                    email: $('#family_modal_email').val(),
                    phone: $('#family_modal_phone').val(),
                },
                success: function (response) {
                    if (response.success) {
                        newFamilyModal.close();
                        alert("Family created successfully!");
                        const familyId = response.data.family_id;
                        const familyUrl = 'admin.php?page=usctdp-admin-families&family_id=' + familyId;
                        window.location.href = familyUrl;
                    }
                },
                error: function (response) {
                    const responseMessage = response.responseJSON.data;
                    var userMessage = "Failed to create family.\n\n" + responseMessage;
                    userMessage += "\n\nTry again or inform a developer.";
                    alert(userMessage);
                }
            });
        });

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
            const { selectorId, value, text, state } = e.detail;
            $('#family-section').addClass('hidden');
            currentId = value;
            pendingChanges = {};
            familyObserver.setOptions({
                queryKey: ['family', currentId],
            });
            if (value) {
                membersTable.ajax.reload();
                const historyHref = 'admin.php?page=usctdp-admin-history&family_id=' + value;
                $('#family-registration-history-link').attr('href', historyHref);
                $('#family-title').text(text);
                $('#family-section').removeClass('hidden');
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
                selectHandler.applyData(preloadedData);
                $("#selection-section").addClass("hidden");
            }
        }
    });
})(jQuery);

