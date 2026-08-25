(function ($) {
    "use strict";

    $(document).ready(function () {

        // Matches the 'status' entry in Usctdp_Mgmt_Session_Schema and every
        // server-side query keyed off it exactly - these are real values
        // sent to the server (see renderStatusSelect()'s <option value>
        // below), not just display labels.
        var STATUSES = ['scheduled', 'on_sale', 'archived'];
        var STATUS_LABELS = {
            scheduled: 'Scheduled',
            on_sale: 'On Sale',
            archived: 'Archived'
        };

        function renderStatusSelect(status, sessionId) {
            var $select = $('<select class="session-status-select"></select>');
            $select.addClass('status-' + status);
            $select.attr('data-session-id', sessionId);
            STATUSES.forEach(function (value) {
                var $option = $('<option></option>').attr('value', value).text(STATUS_LABELS[value]);
                if (value === status) {
                    $option.attr('selected', 'selected');
                }
                $select.append($option);
            });
            return $select;
        }

        var sessionsTable = $('#sessions-table').DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            searching: true,
            paging: true,
            info: true,

            ajax: {
                url: usctdp_mgmt_admin.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = usctdp_mgmt_admin.sessions_datatable_action;
                    d.security = usctdp_mgmt_admin.sessions_datatable_nonce;
                    d.status = $('#sessions-status-filter').val();
                }
            },
            autoWidth: false,
            columnDefs: [
                { width: "60%", targets: 0 },
                { width: "20%", targets: 1 },
                { width: "20%", targets: 2 },
            ],
            columns: [
                { data: 'title' },
                {
                    data: 'status',
                    className: 'status-column',
                    render: function (data, type, row) {
                        if (type !== 'display') {
                            return data;
                        }
                        return renderStatusSelect(data, row.id).get(0);
                    },
                    defaultContent: '',
                },
                {
                    data: 'id',
                    className: 'actions-column',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return '<button type="button" class="button button-small open-pricing-btn">Pricing</button>';
                        }
                        return '';
                    },
                    defaultContent: '',
                }
            ]
        });

        $('#sessions-status-filter').on('change', function () {
            sessionsTable.ajax.reload();
        });

        // Fires immediately on change rather than waiting for a Save button -
        // this is the only field on the row, so there's nothing to batch it
        // with. Reverts the select on failure rather than reloading the
        // whole table, so a filtered view doesn't yank the row out from
        // under the user mid-change.
        $('#sessions-table').on('change', '.session-status-select', function () {
            var $select = $(this);
            var sessionId = $select.data('session-id');
            var newStatus = $select.val();
            var previousStatus = STATUSES.filter(function (s) {
                return $select.hasClass('status-' + s);
            })[0] || newStatus;

            $select.prop('disabled', true);
            USCTDP_Admin.ajax_updateSessionStatus(sessionId, newStatus)
                .then(function (result) {
                    // The status write always succeeds independently of the
                    // schedule rebuild / WooCommerce sync (see
                    // ajax_update_session_status()) - so this branch still
                    // reflects the saved status even when sync_failed and/or
                    // schedule_rebuild_failed is set, it just also warns
                    // that one or both of those side effects may be stale.
                    $select.removeClass('status-' + previousStatus).addClass('status-' + newStatus);
                    var statusFilter = $('#sessions-status-filter').val();
                    var stillMatchesFilter =
                        statusFilter === '' ||
                        statusFilter === newStatus ||
                        (statusFilter === 'not_archived' && newStatus !== 'archived');
                    if (!stillMatchesFilter) {
                        sessionsTable.ajax.reload(null, false);
                    }
                    if (result && (result.sync_failed || result.schedule_rebuild_failed)) {
                        var problems = [];
                        if (result.schedule_rebuild_failed) {
                            problems.push('the public program schedule didn\'t rebuild');
                        }
                        if (result.sync_failed) {
                            problems.push('WooCommerce pricing/variations didn\'t sync');
                        }
                        window.Swal.fire({
                            title: 'Status Saved, Sync Failed',
                            text: 'The session status was updated, but ' + problems.join(' and ') + '. Please inform a developer.',
                            icon: 'warning'
                        });
                    }
                })
                .catch(function (error) {
                    $select.val(previousStatus);
                    window.Swal.fire({
                        title: 'Error',
                        text: error.message || 'Failed to update session status. Please inform a developer.',
                        icon: 'error'
                    });
                })
                .finally(function () {
                    $select.prop('disabled', false);
                });
        });

        /* ---------------------------------------------------------------
         * Pricing modal
         * ------------------------------------------------------------- */

        const pricingModal = document.getElementById('session-pricing-modal');

        // One field set per product type - keys match what's actually
        // stored in usctdp_pricing.pricing (see import_clinic_prices()/
        // import_tournament_pricing() in class-usctdp-import-session-data.php
        // and ajax_update_pricing()'s allow-list), not just display labels.
        var PRICING_FIELDS = {
            clinic: [
                { key: 'One', label: 'One Day Price' },
                { key: 'Two', label: 'Two Day Price' }
            ],
            tournament: [
                { key: 'base', label: 'Base Price' },
                { key: 'early_signup', label: 'Early Signup Price' },
                { key: 'with_clinic', label: 'With Clinic Price' }
            ]
        };

        function renderPricingCard(item) {
            var $card = $('<div class="pricing-product-card"></div>');
            $card.attr('data-pricing-id', item.pricing_id);
            $card.append($('<h3 class="pricing-product-title"></h3>').text(item.product_title));

            var $fields = $('<div class="pricing-fields flex-row gap-10 flex-wrap"></div>');
            // 'tournament' is the only type priced base/early_signup/
            // with_clinic-style - everything else (clinic, and 'cardio' for
            // Cardio Tennis, which is priced/scheduled exactly like a
            // clinic despite its own product type) is One/Two day priced.
            // Matches the === 'tournament' branching convention
            // get_session_price_lines() already uses server-side.
            var fieldDefs = item.product_type === 'tournament' ? PRICING_FIELDS.tournament : PRICING_FIELDS.clinic;
            fieldDefs.forEach(function (field) {
                var pricing = item.pricing || {};
                var value = pricing[field.key] != null ? pricing[field.key] : '';
                var $field = $('<div class="modal_field"></div>');
                $field.append($('<label></label>').text(field.label));
                var $input = $('<input type="number" min="0" step="0.01">');
                $input.addClass('pricing-input');
                $input.attr('data-key', field.key);
                $input.val(value);
                $field.append($input);
                $fields.append($field);
            });

            $card.append($fields);
            return $card;
        }

        function openPricingModal(sessionId, sessionTitle) {
            $('#pricing-modal-session-title').text(sessionTitle);
            $('#pricing-modal-products').empty();
            $('#pricing-modal-empty').addClass('hidden');
            $('#save-pricing-btn').prop('disabled', true);
            pricingModal.showModal();

            USCTDP_Admin.ajax_getSessionPricing(sessionId)
                .then(function (items) {
                    if (!items || items.length === 0) {
                        $('#pricing-modal-empty').removeClass('hidden');
                        return;
                    }
                    items.forEach(function (item) {
                        $('#pricing-modal-products').append(renderPricingCard(item));
                    });
                })
                .catch(function (error) {
                    pricingModal.close();
                    window.Swal.fire({
                        title: 'Error',
                        text: error.message || 'Failed to load pricing. Please inform a developer.',
                        icon: 'error'
                    });
                })
                .finally(function () {
                    $('#save-pricing-btn').prop('disabled', false);
                });
        }

        $('#sessions-table').on('click', '.open-pricing-btn', function () {
            var $tr = $(this).closest('tr');
            var rowData = sessionsTable.row($tr).data();
            openPricingModal(rowData.id, rowData.title);
        });

        $('#cancel-pricing-btn').on('click', function () {
            pricingModal.close();
        });

        $('#save-pricing-btn').on('click', function () {
            var $btn = $(this);
            var saves = [];
            $('#pricing-modal-products .pricing-product-card').each(function () {
                var $card = $(this);
                var pricingId = $card.data('pricing-id');
                var pricing = {};
                $card.find('.pricing-input').each(function () {
                    var key = $(this).data('key');
                    var val = $(this).val();
                    if (val !== '') {
                        pricing[key] = val;
                    }
                });
                saves.push(USCTDP_Admin.ajax_updatePricing(pricingId, pricing));
            });

            if (saves.length === 0) {
                pricingModal.close();
                return;
            }

            $btn.prop('disabled', true);
            Promise.all(saves)
                .then(function (results) {
                    pricingModal.close();
                    // The pricing write always succeeds independently of
                    // the WooCommerce sync (see ajax_update_pricing()), so
                    // this branch is reached even if one or more products'
                    // sync_failed came back true.
                    var anySyncFailed = results.some(function (r) { return r && r.sync_failed; });
                    if (anySyncFailed) {
                        window.Swal.fire({
                            title: 'Pricing Saved, Sync Failed',
                            text: 'Pricing was updated, but syncing WooCommerce for one or more products failed. Please inform a developer.',
                            icon: 'warning'
                        });
                    } else {
                        window.Swal.fire({
                            title: 'Success',
                            text: 'Pricing updated.',
                            icon: 'success'
                        });
                    }
                })
                .catch(function (error) {
                    window.Swal.fire({
                        title: 'Error',
                        text: error.message || 'Failed to save pricing. Please inform a developer.',
                        icon: 'error'
                    });
                })
                .finally(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})(jQuery);
