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
                        action: usctdp_mgmt_admin.select2_search_action,
                        security: usctdp_mgmt_admin.select2_search_nonce,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            }
        });

        var table = $('#family-members-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: true,
            paging: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    var familyFilterValue = $('#family-selector').val();
                    d.action = usctdp_mgmt_admin.datatable_search_action;
                    d.security = usctdp_mgmt_admin.datatable_search_nonce;
                    d.post_type = 'usctdp-student';
                    d['filter[family][value]'] = familyFilterValue;
                    d['filter[family][compare]'] = '=';
                    d['filter[family][type]'] = 'NUMERIC';
                }
            },
            columns: [
                { data: 'first_name' },
                { data: 'last_name' },
                {
                    data: 'birth_date',
                    render: function (data) {
                        if (data) {
                            const year = data.substring(0, 4);
                            const month = data.substring(4, 6);
                            const day = data.substring(6, 8);
                            return `${month}/${day}/${year}`;
                        }
                        return '';

                    }
                },
                {
                    data: 'birth_date',
                    render: function (data) {
                        if (data) {
                            const birthYear = parseInt(data.substring(0, 4), 10);
                            const birthMonth = parseInt(data.substring(4, 6), 10) - 1; // Month is 0-indexed
                            const birthDay = parseInt(data.substring(6, 8), 10);

                            const today = new Date();
                            const currentYear = today.getFullYear();
                            const currentMonth = today.getMonth();
                            const currentDay = today.getDate();

                            let age = currentYear - birthYear;
                            if (currentMonth < birthMonth || (currentMonth === birthMonth && currentDay < birthDay)) {
                                age--;
                            }
                            return age;
                        }
                        return '';
                    }
                },
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
            $('#family-display-section').hide();
            if (selectedValue && selectedValue !== '') {
                $('#family-display-section').show();
                table.ajax.reload();
                $.ajax({
                    url: usctdp_mgmt_admin.ajax_url,
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        action: usctdp_mgmt_admin.select2_search_action,
                        post_type: 'usctdp-family',
                        post_id: selectedValue,
                        security: usctdp_mgmt_admin.select2_search_nonce,
                        acf: 'true'
                    },
                    success: function (responseData) {
                        if (responseData.items.length > 0) {
                            var familyData = responseData.items[0];
                            for (var key in familyData.acf) {
                                var element_id = "#family-" + key;
                                if ($(element_id).length > 0) {
                                    $(element_id).text(familyData.acf[key]);
                                }
                            }
                            var user = familyData.acf["assigned_user"];
                            $("#family-email").text(user.user_email);
                        }

                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error:", textStatus, errorThrown);
                    }
                });
            }
        });

        $("#save-notes-button").on("click", function () {
            console.log("saving notes");
        });
    });

})(jQuery);

