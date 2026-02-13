(function ($) {
    "use strict";
    $(document).ready(function () {
        var preloadedData = null;
        var edit_mode = false;

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

        function updateFamilyField(id, value) {
            $('#' + id + ' .view-mode').text(value);
            $('#' + id + ' .edit-mode').val(value);
            $('#' + id).data('orig-value', value);
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
                console.log(data);
                updateFamilyField('family-email', data.email);
                updateFamilyField('family-address', data.address);
                updateFamilyField('family-city', data.city);
                updateFamilyField('family-state', data.state);
                updateFamilyField('family-zip', data.zip);
                if (data.phone_numbers && data.phone_numbers.length > 0) {
                    updateFamilyField('family-phone', data.phone_numbers.join(" | "));
                } else {
                    updateFamilyField('family-phone', 'Not available');
                }
                $('#family-notes').val(data.notes);
                const historyHref = 'admin.php?page=usctdp-admin-history&family_id=' + selectedValue;
                $('#family-registration-history-link').attr('href', historyHref);
                membersTable.ajax.reload();
            }
        });

        function saveFamilyFields() {
            const fields = {
                'family-email': 'email',
                'family-address': 'address',
                'family-city': 'city',
                'family-state': 'state',
                'family-zip': 'zip',
                'family-phone': 'phone'
            };
            const changedData = {};
            Object.entries(fields).forEach(([divId, fieldName]) => {
                const curValue = $('#' + divId + ' .edit-mode').val().trim();
                const origValue = $('#' + divId).data('orig-value').trim();
                if (curValue !== origValue) {
                    changedData[fieldName] = curValue;
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
                    Object.entries(fields).forEach(([divId, fieldName]) => {
                        if (fieldName in changedData) {
                            updateFamilyField(divId, changedData[fieldName]);
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
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    $("#save-notes-error").removeClass("hidden");
                    $("#save-notes-success").addClass("hidden");
                },
                complete: function () {
                    $('#save-notes-text').text('Save Notes');
                    $('#save-notes-button').removeClass('is-loading');
                    $('#family-selector').attr('disabled', false);
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

