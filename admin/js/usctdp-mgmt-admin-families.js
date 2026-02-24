(function ($) {
    "use strict";
    $(document).ready(function () {
        var preloadedData = null;
        var edit_mode = false;
        const newStudentModal = document.querySelector('#new-student-modal');
        const newFamilyModal = document.querySelector('#new-family-modal');

        const fields = {
            'family-email': {
                column: 'email',
                transform: function (value) { return value.trim(); },
                compare: function (newVal, oldVal) { return newVal === oldVal; },
                update: updateFamilyTextField
            },
            'family-address': {
                column: 'address',
                transform: function (value) { return value.trim(); },
                compare: function (newVal, oldVal) { return newVal === oldVal; },
                update: updateFamilyTextField
            },
            'family-city': {
                column: 'city',
                transform: function (value) { return value.trim(); },
                compare: function (newVal, oldVal) { return newVal === oldVal; },
                update: updateFamilyTextField
            },
            'family-state': {
                column: 'state',
                transform: function (value) { return value.trim(); },
                compare: function (newVal, oldVal) { return newVal === oldVal; },
                update: updateFamilyTextField
            },
            'family-zip': {
                column: 'zip',
                transform: function (value) { return value.trim(); },
                compare: function (newVal, oldVal) { return newVal === oldVal; },
                update: updateFamilyTextField
            },
            'family-phone': {
                column: 'phone_numbers',
                transform: function (value) {
                    return value.split("\n")
                        .map(function (item) { return item.trim(); })
                        .filter(function (item) { return item !== ''; });
                },
                compare: function (newVal, oldVal) {
                    if (newVal.length !== oldVal.length) {
                        return false;
                    }
                    return newVal.every((value, index) => value === oldVal[index]);
                },
                update: updateFamilyPhoneField
            }
        };

        $('#family-selector').select2({
            placeholder: "Search for a family...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        action: usctdp_mgmt_admin.select2_family_search_action,
                        security: usctdp_mgmt_admin.select2_family_search_nonce,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

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
                    var familyFilterValue = $('#family-selector').val();
                    d.action = usctdp_mgmt_admin.student_datatable_action;
                    d.security = usctdp_mgmt_admin.student_datatable_nonce;
                    d.family_id = familyFilterValue;
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
                            var cell = '<div class="family-actions">'
                            cell += '<div class="action-item">'
                            cell += '<a href="' + registerUrl + '" class="button button-small">Register</a> ';
                            cell += '<a href="' + historyUrl + '" class="button button-small">History</a> ';
                            cell += '</div>';
                            cell += '</div>';
                            return cell;
                        }
                        return '';
                    }
                }
            ]
        });

        function updateFamilyTextField(id, value) {
            $('#' + id + ' .view-mode-text').text(value);
            $('#' + id + ' .editor').val(value);
            $('#' + id).data('orig-value', value);
        }

        function updateFamilyPhoneField(id, values) {
            $('#' + id + ' .view-mode').children().remove();
            if (values && values.length > 0) {
                values.forEach(function (value) {
                    $('#' + id + ' .view-mode').append('<span>' + value + '</span>');
                });
                $('#' + id + ' .editor').val(values.join('\n'));
            } else {
                $('#' + id + ' .view-mode').append('<span>Not available</span>');
                $('#' + id + ' .editor').val('');
            }
            $('#' + id).data('orig-value', values);
        }

        $('#family-selector').on('change', function () {
            $("#save-notes-error").addClass("hidden");
            $("#save-notes-success").addClass("hidden");
            $('#save-notes-text').text('Save Notes');
            $('#save-notes-button').removeClass('is-loading');
            $('#family-section').addClass('hidden');

            const selectedValue = this.value;
            var data = preloadedData ? preloadedData : $(this).select2('data')[0];
            if (selectedValue && selectedValue !== '') {
                $('#family-section').removeClass('hidden');
                $('#family-title').text(data.title);
                updateFamilyTextField('family-email', data.email);
                updateFamilyTextField('family-address', data.address);
                updateFamilyTextField('family-city', data.city);
                updateFamilyTextField('family-state', data.state);
                updateFamilyTextField('family-zip', data.zip);
                updateFamilyPhoneField('family-phone', data.phone_numbers);
                $('#family-notes').val(data.notes);
                const historyHref = 'admin.php?page=usctdp-admin-history&family_id=' + selectedValue;
                $('#family-registration-history-link').attr('href', historyHref);
                membersTable.ajax.reload();
            }
        });

        function saveFamilyFields() {
            const changedData = {};
            Object.entries(fields).forEach(([divId, field]) => {
                const curValue = field.transform($('#' + divId + ' .editor').val());
                const origValue = $('#' + divId).data('orig-value');
                if (!field.compare(curValue, origValue)) {
                    if (curValue.length === 0) {
                        changedData[field.column] = null;
                    } else {
                        changedData[field.column] = curValue;
                    }
                }
            });
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.save_family_fields_action,
                    security: usctdp_mgmt_admin.save_family_fields_nonce,
                    id: $('#family-selector').val(),
                    ...changedData
                },
                success: function (responseData) {
                    Object.entries(fields).forEach(([divId, field]) => {
                        if (field.column in changedData) {
                            field.update(divId, changedData[field.column]);
                            if (preloadedData) {
                                preloadedData[field.column] = changedData[field.column];
                            } else {
                                var data = $('#family-selector').select2('data')[0];
                                data[field.column] = changedData[field.column];
                                $('#family-selector').select2('data', data);
                            }
                        }
                    });
                },
                error: function (xhr, status, error) {
                    alert("Update failed!");
                },
                complete: function () {
                    $('#edit-family-button').text('Edit');
                    $('#edit-family-button').removeClass('is-loading');
                    $("#family-info-list .family-field").each(function () {
                        $(this).find('.view-mode').removeClass('hidden');
                        $(this).find('.edit-mode').addClass('hidden');
                        $(this).find('.edit-mode').prop('disabled', false);
                    });
                    edit_mode = false;
                }
            });
        }

        $('#edit-family-button').on('click', function () {
            if (edit_mode) {
                $("#family-info-list .family-field").each(function () {
                    $(this).find('.edit-mode').prop('disabled', true);
                });
                $('#edit-family-button').text('Working...');
                $('#edit-family-button').addClass('is-loading');
                saveFamilyFields();
            } else {
                $(this).text('Save');
                $("#family-info-list .family-field").each(function () {
                    $(this).find('.view-mode').addClass('hidden');
                    $(this).find('.edit-mode').removeClass('hidden');
                });
                edit_mode = true;
            }
        });

        $("#save-notes-button").on("click", function () {
            $("#save-notes-error").addClass("hidden");
            $("#save-notes-success").addClass("hidden");
            $('#save-notes-text').text('Working...');
            $("#save-notes-button").addClass("is-loading");
            $('#family-selector').attr('disabled', true);
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.save_family_fields_action,
                    id: $('#family-selector').val(),
                    notes: $('#family-notes').val(),
                    security: usctdp_mgmt_admin.save_family_fields_nonce,
                },
                success: function (responseData) {
                    $("#save-notes-error").addClass("hidden");
                    $("#save-notes-success").removeClass("hidden");

                    // Update the local cache of the notes
                    var data = preloadedData ? preloadedData : $('#family-selector').select2('data')[0];
                    data.notes = $('#family-notes').val();
                    const message = document.getElementById('save-notes-success');
                    setTimeout(() => {
                        message.classList.add('hidden');
                    }, 3000);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    $("#save-notes-error").removeClass("hidden");
                    $("#save-notes-success").addClass("hidden");
                    const message = document.getElementById('save-notes-error');
                    setTimeout(() => {
                        message.classList.add('hidden');
                    }, 3000);
                },
                complete: function () {
                    $('#save-notes-text').text('Save Notes');
                    $('#save-notes-button').removeClass('is-loading');
                    $('#family-selector').attr('disabled', false);
                }
            });
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

        if (usctdp_mgmt_admin.preload && usctdp_mgmt_admin.preload.family_id) {
            preloadedData = Object.values(usctdp_mgmt_admin.preload.family_id)[0];
            const newOption = new Option(
                preloadedData.title,
                preloadedData.id,
                true,
                true
            );
            $('#family-selector').append(newOption);
            $('#family-selector').val(preloadedData.id);
            $('#family-selector').trigger('change');
            $('#family-selector').prop('disabled', true);
            $('#selection-section').addClass('hidden');
        }
    });
})(jQuery);

