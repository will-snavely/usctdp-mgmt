(function ($) {
    "use strict";
    $(document).ready(function () {
        $('#family-selector').select2({
            placeholder: "Search for a family...",
            allowClear: true,
            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                data: function (params) {
                    return {
                        q: params.term,
                        post_type: 'usctdp-family',
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
                            var cell = '<div class="family-actions">'
                            cell += '<div class="action-item">'
                            cell += '<a href="' + registerUrl + '" class="button button-small">Register</a> ';
                            cell += '</div>';
                            cell += '</div>';
                            return cell;
                        }
                        return '';
                    }
                }
            ]
        });

        $('#family-selector').on('change', function () {
            const selectedValue = this.value;
            var data = $(this).select2('data')[0];
            $("#save-notes-error").addClass("hidden");
            $("#save-notes-success").addClass("hidden");
            $('#save-notes-text').text('Save Notes');
            $('#save-notes-button').removeClass('is-loading');
            $('#family-display-section').hide();

            if (selectedValue && selectedValue !== '') {
                $('#family-display-section').show();
                $("#family-email").text(data.email);
                $("#family-notes").text(data.notes);
                if (data.phone_numbers && data.phone_numbers.length > 0) {
                    $("#family-phone").text(data.phone_numbers.join(" | "));
                } else {
                    $("#family-phone").text('Not available');
                }
                $("#family-address").text(data.address);
                $("#family-city").text(data.city);
                $("#family-state").text(data.state);
                $("#family-zip").text(data.zip);
                membersTable.ajax.reload();
            }
        });

        $("#save-notes-button").on("click", function () {
            /*
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
                    action: usctdp_mgmt_admin.save_field_action,
                    post_id: $('#family-selector').val(),
                    field_name: 'field_usctdp_family_notes',
                    field_value: $('#family-notes').val(),
                    security: usctdp_mgmt_admin.save_field_nonce,
                },
                success: function (responseData) {
                    $("#save-notes-error").addClass("hidden");
                    $("#save-notes-success").removeClass("hidden");
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
            */
        });

        if (usctdp_mgmt_admin.preloaded_family_name) {
            const newOption = new Option(
                usctdp_mgmt_admin.preloaded_family_name,
                usctdp_mgmt_admin.preloaded_family_id,
                true,
                true
            );
            $('#family-selector').append(newOption);
            $('#family-selector').val(usctdp_mgmt_admin.preloaded_family_id);
            $('#family-selector').trigger('change');
            $('#family-selector').prop('disabled', true);
        }
    });
})(jQuery);

