(function ($) {
    "use strict";

    $(document).ready(function () {
        class PurchaseCard {
            constructor(data, idx, isNew = false) {
                this.data = data;
                this.idx = idx;
                this.isNew = isNew;
            }

            render() {
                const createdDate = new Date(this.data.purchase_created_at).toLocaleString()
                return `
                    <div class="purchase-card edit-disabled ${this._card_classes()}" data-idx="${this.idx}">
                        <div class="flex-row gap-10 align-center w-100 flex-wrap">
                            <div class="checkbox-wrap">
                                <input type="checkbox" class="row-check" value="${this.data.registration_id || this.data.purchase_id}">
                            </div>
                            
                            ${this._renderStudentInfo()}

                            <div class="border-left">
                                <div class="flex-row gap-10 align-center">
                                    ${this._renderBadges()} 
                                    ${this._renderHeaderActions()}
                                </div>
                            </div>

                            <div class="border-left">
                                <div class="created-date flex-row gap-5 align-center">
                                    <label class="upper-heavy">Created At</label>
                                    <span class="created-date-value">${createdDate}</span>
                                </div>
                            </div>

                            <div class="border-left">
                                <div class="purchase-id flex-row gap-5 align-center">
                                    <label class="upper-heavy">Purchase ID</label>
                                    <span class="purchase-id-value">${this.data.purchase_id}</span>
                                </div>
                            </div>
                        </div>

                        <div class="card-middle-content flex-row gap-10 align-center w-100">
                            ${this._renderMiddleSection()}
                        </div>

                        <div class="card-bottom-content flex-row gap-10 align-center w-100">
                            ${this._renderFinancialSection()}
                            ${this._renderNotesSection()}
                        </div>
                    </div>`;
            }

            _renderBadges() {
                return `
                    ${this._renderTypeBadge()} 
                    ${this._renderNewBadge()}
                    ${this._renderAdditionalBadges()}`;
            }

            _renderNewBadge() {
                if (this.isNew) {
                    return `<div class="new-purchase-badge"><span class="new-purchase">New!</span></div>`;
                }
                return '';
            }

            _renderStudentInfo() {
                return `
                    <div class="student-name-wrap">
                        <span class="student-name">${this.data.student_first} ${this.data.student_last}</span>
                    </div>
                    <div class="student-age-wrap">
                        <span class="student-age">Age: ${this.data.student_age ?? "--"}</span>
                    </div>`;
            }

            _renderAmountBadge(label, value, classes = []) {
                return `
                    <div class="flex-col gap-5 align-center">
                        <label class="upper-heavy">${label}</label>
                        <span class="badge ${classes.join(' ')}">${value}</span>
                    </div>`;
            }

            _renderFinancialSection() {
                const adjustments = USCTDP_Admin.safeParseFloat(this.data.total_adjustments);
                const fees = USCTDP_Admin.safeParseFloat(this.data.total_fees);
                const payments = USCTDP_Admin.safeParseFloat(this.data.total_payments);
                const refunds = USCTDP_Admin.safeParseFloat(this.data.total_refunds);
                const houseCredits = USCTDP_Admin.safeParseFloat(this.data.total_house_credits);

                const netFees = fees - adjustments;
                const netPayments = payments - (refunds + houseCredits);
                const owed = netFees - netPayments;

                const format = (val) => USCTDP_Admin.formatUsd(val);
                return `
                    <div class="financial-section flex-col gap-10">
                        <div class="payment-info">
                            ${this._renderAmountBadge('Fees', format(netFees), ['red-bg'])}
                            ${this._renderAmountBadge('Paid', format(netPayments), ['green-bg'])}
                            ${this._renderAmountBadge('Refunds', format(refunds), ['blue-bg'])}
                            <div class="mobile-break"></div>
                            ${this._renderAmountBadge('House Cr.', format(houseCredits), ['blue-bg'])}
                            ${this._renderAmountBadge('Owed', format(owed), ['red-bg'])}
                        </div>
                        <div class="flex-row gap-10 align-center">
                            <button id="payment-history-${this.idx}" class="button payment-history">History</button>
                            <select id="payment-action-${this.idx}" class="payment-action-select">
                                <option value=""></option>
                                <option value="post-payment">Record Payment</option>
                                <option value="post-refund">Record Refund/Adj.</option>
                            </select>
                            <button id="ledger-action-${this.idx}" class="button ledger-action" disabled>Go</button>
                        </div>
                    </div>`;
            }

            _renderNotesSection() {
                return `
                    <div class="notes-section flex-col gap-5">
                        <div class="flex-row gap-10 align-center">
                            <label class="upper-heavy">Notes</label>
                            <button id="save-notes-${this.idx}" class="button button-small save-notes-btn" disabled>Save</button>
                        </div>
                        <textarea rows=3 id="notes-input-${this.idx}" class="notes-input">${this.data.purchase_notes || ''}</textarea>
                    </div>`;
            }

            _renderTypeBadge() { return ''; }
            _renderAdditionalBadges() { return ''; }
            _renderHeaderActions() { return ''; }
            _renderMiddleSection() { return ''; }
            _card_classes() { return ''; }
        }

        class RegistrationCard extends PurchaseCard {
            _card_classes() {
                if (this.data.registration_status === 'void') {
                    return 'void-registration';
                }
                return '';
            }

            _renderTypeBadge() {
                return `<span class="purchase-badge blue-bg upper-heavy">Registration</span>`;
            }

            _renderAdditionalBadges() {
                if (this.data.registration_status === 'void') {
                    return `<span class="purchase-badge red-bg upper-heavy">Void</span>`;
                }
                return '';
            }

            _renderHeaderActions() {
                if (this.data.registration_status === 'void') {
                    return `
                        <button id="restore-registration-${this.idx}" class="button button-small restore-registration-btn" data-state="edit">
                            Restore
                        </button>`;
                }
                return `
                    <button id="edit-registration-${this.idx}" class="button button-small edit-registration-btn" data-state="edit">
                        Modify
                    </button>
                    <button id="save-registration-${this.idx}" class="button button-small save-registration-btn hidden" data-state="edit">
                        Save
                    </button>
                    <button id="void-registration-${this.idx}" class="button button-small void-registration-btn" data-state="edit">
                        Void
                    </button>`;
            }

            _renderMiddleSection() {
                const sessionSelectId = `session-selector-${this.idx}`;
                const activitySelectId = `activity-selector-${this.idx}`;
                return `
                    <div class="registration-fields flex-row gap-10 w-100">
                        <div class="session-selector-wrap flex-col gap-5 selector-wrap">
                            <label class="upper-heavy">Session</label>
                            <div id="session-selector-wrap-${this.idx}" class="w-100">
                                <select id="${sessionSelectId}" class="session-select" data-width="100%" data-activity-selector-id="${activitySelectId}" disabled>
                                    <option value="${this.data.session_id}" selected>${this.data.session_name}</option>
                                </select>
                            </div>
                        </div>
                        <div class="activity-selector-wrap flex-col gap-5 selector-wrap">
                            <label class="upper-heavy">Activity</label>
                            <div id="activity-selector-wrap-${this.idx}" class="w-100">
                                <select id="${activitySelectId}" class="activity-select" data-width="100%" data-session-selector-id="${sessionSelectId}" disabled>
                                    <option value="${this.data.activity_id}" selected>${this.data.activity_name}</option>
                                </select>
                            </div>
                        </div>
                        <div class="level-wrap flex-col gap-5">
                            <label class="upper-heavy">Level</label>
                            <input id="level-input-${this.idx}" class="level-input" value="${this.data.registration_student_level}" readonly>
                        </div>
                    </div>`;
            }
        }

        class MerchandiseCard extends PurchaseCard {
            _renderTypeBadge() {
                return `<span class="purchase-badge green-bg upper-heavy">Merchandise</span>`;
            }

            _renderAdditionalBadges() {
                return `<span class="activity-name product-name">${this.data.product_name}</span>`;
            }
        }

        var newPurchases = null;
        const paymentHistoryModal = new USCTDP_Admin.PaymentHistoryModal('payment-history-modal-container');
        const confirmRegistrationUpdateModal = document.querySelector('#confirm-registration-update-modal');
        const postPaymentModal = document.querySelector('#post-payment-modal');
        const postRefundModal = document.querySelector('#post-refund-modal');
        const paymentSettings = {
            checkoutButton: false,
            allowPayLater: false,
            paymentMode: "update",
            manageDiscounts: false,
            redirectOnComplete: false,
        };
        const paymentTableId = "registration-payment-table";
        const paymentTable = new USCTDP_Admin.RegistrationPaymentTable(paymentTableId, paymentSettings);

        function refreshFamilyBalance() {
            const family_id = $('#family-filter').val();
            if (!family_id) {
                // "All families" view - there is no single balance to show.
                return;
            }
            $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                data: {
                    action: usctdp_mgmt_admin.get_family_balance_action,
                    security: usctdp_mgmt_admin.get_family_balance_nonce,
                    family_id: family_id
                },
                success: function (response) {
                    $('#family-total-balance').text(USCTDP_Admin.formatUsd(response.data.balance));
                    $('#family-total-balance').toggleClass('red-bg', response.data.balance > 0);
                    $('#family-total-balance').toggleClass('green-bg', response.data.balance <= 0);
                    $('#family-total-house-credit').text(USCTDP_Admin.formatUsd(response.data.house_credit));
                }
            });
        }

        // Feeds the editable Session field on a registration row (see
        // .session-select in _renderMiddleSection()) - this is picking a
        // session to MOVE an existing registration into, not just browsing,
        // so (unlike #session-filter below) archived sessions are excluded.
        // active:1 excludes only 'archived', not 'scheduled' (see
        // search_sessions() in Usctdp_Mgmt_Session_Query).
        function initSessionSelector($selectElem) {
            $selectElem.select2(
                USCTDP_Admin.select2Options({
                    placeholder: "Search for a session...",
                    allowClear: true,
                    target: 'session',
                    filter: function () {
                        return { active: 1 };
                    }
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

        function initPaymentActionSelect($selectElem) {
            $selectElem.select2({
                placeholder: "Payment action...",
                allowClear: true,
            });
        }

        // Rebuilds a USCTDP_Admin.Discount instance from a stored discount
        // record ({code, value, reason, amount} - the same flattened shape
        // usctdp_purchase.discounts and the payment table's cart items use
        // throughout this plugin) so its amount() can be re-derived against
        // a new base price. Sibling discounts encode their percent in the
        // code itself (SiblingDiscount's constructor: code = 'sibling_' +
        // percent - see usctdp-mgmt-admin.js), which is why that one's
        // matched by pattern rather than an exact code.
        function rebuildDiscount(record) {
            const siblingMatch = /^sibling_(\d+(\.\d+)?)$/.exec(record.code || '');
            if (record.code === 'second_day') {
                return new USCTDP_Admin.AdditionalDayDiscount(record.value);
            } else if (siblingMatch) {
                return new USCTDP_Admin.SiblingDiscount(parseFloat(siblingMatch[1]));
            } else if (record.code === 'custom_percent') {
                return new USCTDP_Admin.CustomPercentDiscount(record.value, record.reason);
            }
            return null;
        }

        // Re-derives one discount's dollar amount for the new activity.
        // Only 'second_day' (tied to the new activity's own two-day pricing
        // tier - see get_price_change() server-side) and percent-based
        // discounts (sibling_*, custom_percent - a % of base price) can be
        // recomputed automatically; flat/tier-based discounts (early_signup,
        // with_clinic, custom_flat) and anything unrecognized have no
        // reliable new amount to derive, so they're carried forward
        // unchanged - the review modal still lets the admin edit or remove
        // them if they no longer apply.
        function recomputeDiscount(record, newBasePrice, newAdditionalDayDiscount) {
            if (record.code === 'second_day') {
                if (newAdditionalDayDiscount === null || newAdditionalDayDiscount === undefined) {
                    // The new activity has no two-day pricing tier at all
                    // (get_price_change() returns null in that case) - there's
                    // no same-shape amount to re-derive, so leave this one for
                    // the admin to review and edit/remove manually.
                    return { ...record };
                }
                const discount = new USCTDP_Admin.AdditionalDayDiscount(newAdditionalDayDiscount);
                return { code: discount.code, value: discount.value, amount: discount.amount(newBasePrice), reason: discount.reason };
            }
            const discount = rebuildDiscount(record);
            if (discount) {
                return { code: discount.code, value: discount.value, amount: discount.amount(newBasePrice), reason: discount.reason };
            }
            return { ...record };
        }

        function parseStoredDiscounts(raw) {
            if (!raw) {
                return [];
            }
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function sumDiscounts(discounts) {
            return discounts.reduce((sum, d) => sum + USCTDP_Admin.safeParseFloat(d.amount), 0);
        }

        // Same formula PurchaseCard._renderFinancialSection() uses for the
        // "Owed" badge - the amount currently outstanding (or, if negative,
        // already overpaid) *before* this registration change is applied.
        function computeCurrentOwed(purchaseRow) {
            if (!purchaseRow) return 0;
            const fees = USCTDP_Admin.safeParseFloat(purchaseRow.total_fees);
            const adjustments = USCTDP_Admin.safeParseFloat(purchaseRow.total_adjustments);
            const payments = USCTDP_Admin.safeParseFloat(purchaseRow.total_payments);
            const refunds = USCTDP_Admin.safeParseFloat(purchaseRow.total_refunds);
            const houseCredits = USCTDP_Admin.safeParseFloat(purchaseRow.total_house_credits);
            const netFees = fees - adjustments;
            const netPayments = payments - (refunds + houseCredits);
            return netFees - netPayments;
        }

        function discountLabel(discount) {
            const siblingMatch = /^sibling_(\d+(\.\d+)?)$/.exec(discount.code || '');
            if (discount.code === 'second_day') return 'Second Day';
            if (discount.code === 'early_signup') return 'Early Signup';
            if (discount.code === 'with_clinic') return 'With Clinic';
            if (discount.code === 'custom_flat') return discount.reason || 'Custom Flat';
            if (discount.code === 'custom_percent') return (discount.reason || 'Custom') + ` (${discount.value}%)`;
            if (siblingMatch) return `Sibling (${siblingMatch[1]}%)`;
            return discount.reason || discount.code || 'Discount';
        }

        // Holds the in-progress review's data while #confirm-registration-
        // update-modal is open - null otherwise. The modal's controls are
        // bound once (below) rather than per-open, so they read/write
        // through this instead of closures.
        var registrationUpdateState = null;

        function renderDiscountList($list, discounts, editable) {
            $list.empty();
            if (discounts.length === 0) {
                $list.append('<p class="empty-msg">No discounts.</p>');
                return;
            }
            discounts.forEach((d, idx) => {
                const removeBtn = editable
                    ? `<button type="button" class="remove-new-discount-btn usctdp-remove-btn" data-index="${idx}">&times;</button>`
                    : '';
                $list.append(`
                    <div class="discount-item">
                        <span><strong>${discountLabel(d)}:</strong> ${USCTDP_Admin.formatUsd(d.amount)}</span>
                        ${removeBtn}
                    </div>
                `);
            });
        }

        function updateRegistrationUpdateModalUI() {
            if (!registrationUpdateState) return;
            const { oldBasePrice, oldDiscounts, oldNetPrice, currentOwed, newBasePrice, newDiscounts } = registrationUpdateState;

            $('#current-base-price-display').text(USCTDP_Admin.formatUsd(oldBasePrice));
            renderDiscountList($('#current-discounts-list'), oldDiscounts, false);
            $('#current-net-price-display').text(USCTDP_Admin.formatUsd(oldNetPrice));

            $('#new-base-price-display').text(USCTDP_Admin.formatUsd(newBasePrice));
            renderDiscountList($('#new-discounts-list'), newDiscounts, true);
            const newNetPrice = Math.max(0, newBasePrice - sumDiscounts(newDiscounts));
            $('#new-net-price-display').text(USCTDP_Admin.formatUsd(newNetPrice));

            const delta = Math.round((newNetPrice - oldNetPrice) * 100) / 100;
            const $delta = $('#registration-update-delta');
            const $houseCreditWrap = $('#house-credit-option-wrap');

            // Only the portion of a price decrease that exceeds what's still
            // outstanding is a genuine overpayment - the rest just zeroes
            // out a balance that was already owed, with no money to hand
            // back. E.g. owed $30 today, price drops by $50: $30 of that
            // just cancels the existing balance, only the remaining $20 is
            // actually owed back to the family.
            registrationUpdateState.creditDue = 0;
            if (Math.abs(delta) < 0.01) {
                $delta.text('No change in the amount owed.');
                $houseCreditWrap.addClass('hidden');
            } else if (delta > 0) {
                $delta.text(`The family will owe an additional ${USCTDP_Admin.formatUsd(delta)}.`);
                $houseCreditWrap.addClass('hidden');
            } else {
                const absoluteDelta = -delta;
                $delta.text(`The family will be credited ${USCTDP_Admin.formatUsd(absoluteDelta)}.`);
                const creditDue = Math.max(0, Math.round((absoluteDelta - currentOwed) * 100) / 100);
                registrationUpdateState.creditDue = creditDue;
                if (creditDue > 0) {
                    $('#house-credit-amount-display').text(USCTDP_Admin.formatUsd(creditDue));
                    $houseCreditWrap.removeClass('hidden');
                } else {
                    $houseCreditWrap.addClass('hidden');
                    $('#issue-house-credit-checkbox').prop('checked', false);
                }
            }
        }

        $('#new-discount-type').on('change', function () {
            const code = $(this).val();
            const needsInput = code === 'custom_flat' || code === 'custom_percent';
            $('#new-discount-value').toggleClass('hidden', !needsInput);
            $('#new-discount-reason').toggleClass('hidden', !needsInput);
        });

        $('#add-new-discount-btn').on('click', function () {
            if (!registrationUpdateState) return;
            const code = $('#new-discount-type').val();
            const value = USCTDP_Admin.safeParseFloat($('#new-discount-value').val());
            const reason = $('#new-discount-reason').val();
            const { newBasePrice, newAdditionalDayDiscount } = registrationUpdateState;

            var discount = null;
            if (code === 'second_day') {
                if (newAdditionalDayDiscount === null || newAdditionalDayDiscount === undefined) {
                    window.Swal.fire("Not Available", "The new activity has no two-day pricing tier.", "warning");
                    return;
                }
                discount = new USCTDP_Admin.AdditionalDayDiscount(newAdditionalDayDiscount);
            } else if (code === 'sibling_10') {
                discount = new USCTDP_Admin.SiblingDiscount(10);
            } else if (code === 'sibling_20') {
                discount = new USCTDP_Admin.SiblingDiscount(20);
            } else if (code === 'custom_flat') {
                discount = new USCTDP_Admin.CustomFlatDiscount(value, reason);
            } else if (code === 'custom_percent') {
                discount = new USCTDP_Admin.CustomPercentDiscount(value, reason);
            } else {
                return;
            }

            registrationUpdateState.newDiscounts.push({
                code: discount.code,
                value: discount.value,
                amount: discount.amount(newBasePrice),
                reason: discount.reason
            });
            $('#new-discount-type').val('');
            $('#new-discount-value').val('').addClass('hidden');
            $('#new-discount-reason').val('').addClass('hidden');
            updateRegistrationUpdateModalUI();
        });

        $('#new-discounts-list').on('click', '.remove-new-discount-btn', function () {
            if (!registrationUpdateState) return;
            registrationUpdateState.newDiscounts.splice($(this).data('index'), 1);
            updateRegistrationUpdateModalUI();
        });

        $('#confirm-registration-update-btn').on('click', function () {
            if (!registrationUpdateState) return;
            const { resolve, newDiscounts, creditDue } = registrationUpdateState;
            const issueHouseCredit = creditDue > 0 && $('#issue-house-credit-checkbox').is(':checked');
            registrationUpdateState = null;
            confirmRegistrationUpdateModal.close();
            resolve({ applied: true, discounts: newDiscounts, issueHouseCredit, creditDue });
        });

        $('#cancel-registration-update-btn').on('click', function () {
            confirmRegistrationUpdateModal.close();
        });

        // Fires for the Cancel button's .close() call above AND for native
        // dismissal (Escape) - both are "backed out without confirming".
        // The Confirm handler already nulled registrationUpdateState and
        // resolved before its own .close() call, so this only resolves
        // when a review is still actually pending.
        confirmRegistrationUpdateModal.addEventListener('close', function () {
            if (registrationUpdateState) {
                const { resolve } = registrationUpdateState;
                registrationUpdateState = null;
                resolve({ applied: false });
            }
        });

        // Shows "Confirm Registration Update" pre-populated with the old
        // (read-only) and recomputed-new (editable) price/discount picture,
        // and resolves once the admin either confirms (with whatever final
        // discount list they left the New column in - additions/removals
        // included) or backs out. Never rejects, so a skip can't surface as
        // an unhandled error to the caller.
        function reviewRegistrationUpdate(oldBasePrice, oldDiscounts, oldNetPrice, currentOwed, newBasePrice, newAdditionalDayDiscount, initialNewDiscounts) {
            return new Promise((resolve) => {
                registrationUpdateState = {
                    oldBasePrice,
                    oldDiscounts,
                    oldNetPrice,
                    currentOwed,
                    newBasePrice,
                    newAdditionalDayDiscount,
                    newDiscounts: initialNewDiscounts.map((d) => ({ ...d })),
                    creditDue: 0,
                    resolve
                };
                $('#issue-house-credit-checkbox').prop('checked', false);
                updateRegistrationUpdateModalUI();
                confirmRegistrationUpdateModal.showModal();
            });
        }

        // Previews the price/discount impact of moving `rowData`'s
        // registration to `newActivityId` (via the read-only
        // ajax_preview_registration_activity_change() - nothing is saved by
        // this call) and, if there's anything worth reviewing, shows the
        // confirm modal. Nothing about the registration or the ledger is
        // written here - the caller (updateRegistration()) does both, and
        // only after a confirmed, non-empty result, so declining the review
        // is a true no-op. Returns:
        //   - null                                nothing to review -
        //                                          caller should just save
        //                                          as normal, but still
        //                                          persist `discounts` (see
        //                                          updateRegistration()) -
        //                                          the auto-recomputed
        //                                          amounts can differ from
        //                                          what's on file even when
        //                                          the *net* owed doesn't.
        //   - { cancelled: true }                  admin backed out.
        //   - { cancelled: false, reviewed: true,
        //       ledgerEntries, discounts }          admin confirmed -
        //                                          ledgerEntries may be []
        //                                          if the reviewed price
        //                                          ended up matching what
        //                                          was already owed.
        async function reviewPriceChange(rowData, newActivityId) {
            const previewResponse = await USCTDP_Admin.ajax_previewRegistrationActivityChange(rowData.registration_id, newActivityId);
            if (!previewResponse.success) {
                throw Error("Failed to preview registration change.");
            }

            const priceChange = previewResponse.data.price_change;
            if (!priceChange) {
                return null;
            }

            const oldBasePrice = parseFloat(priceChange.old_price);
            const newBasePrice = parseFloat(priceChange.new_price);
            // Nullable - the new activity may have no two-day pricing tier
            // at all (see get_price_change()/get_additional_day_discount()
            // server-side), which is meaningfully different from a $0
            // discount, so this is deliberately not coerced through
            // safeParseFloat().
            const newAdditionalDayDiscount = priceChange.new_additional_day_discount;

            const purchaseData = previewResponse.data.purchase_data;
            const purchaseRow = purchaseData && purchaseData.data && purchaseData.data[0];
            const oldDiscounts = purchaseRow ? parseStoredDiscounts(purchaseRow.purchase_discounts) : [];
            const oldNetPrice = purchaseRow
                ? USCTDP_Admin.safeParseFloat(purchaseRow.total_fees) - USCTDP_Admin.safeParseFloat(purchaseRow.total_adjustments)
                : oldBasePrice;
            const currentOwed = computeCurrentOwed(purchaseRow);

            const recomputedDiscounts = oldDiscounts.map((d) => recomputeDiscount(d, newBasePrice, newAdditionalDayDiscount));
            const recomputedNetPrice = Math.max(0, newBasePrice - sumDiscounts(recomputedDiscounts));

            if (Math.abs(recomputedNetPrice - oldNetPrice) < 0.01) {
                // Base price and every discount that could be recomputed net
                // back out to the same amount already owed - nothing to
                // review or adjust, but the individual discount amounts may
                // still have shifted (e.g. a second_day discount re-derived
                // for the new activity), so still hand back the recomputed
                // list for updateRegistration() to persist.
                return { cancelled: false, reviewed: false, ledgerEntries: [], discounts: recomputedDiscounts };
            }

            const result = await reviewRegistrationUpdate(
                oldBasePrice, oldDiscounts, oldNetPrice, currentOwed,
                newBasePrice, newAdditionalDayDiscount, recomputedDiscounts
            );
            if (!result.applied) {
                return { cancelled: true };
            }

            const finalNetPrice = Math.max(0, newBasePrice - sumDiscounts(result.discounts));
            const absoluteDelta = Math.round(Math.abs(finalNetPrice - oldNetPrice) * 100) / 100;
            if (absoluteDelta === 0) {
                return { cancelled: false, reviewed: true, ledgerEntries: [], discounts: result.discounts };
            }

            const direction = finalNetPrice < oldNetPrice ? "decrease" : "increase";
            const timestampSeconds = Math.floor(Date.now() / 1000);
            const ledgerEntries = USCTDP_Admin.createAdjustmentLedger({
                event_id: "adjustment_" + timestampSeconds,
                event: "Registration Change",
                family_id: rowData.family_id,
                student_id: rowData.student_id,
                purchase_id: rowData.purchase_id,
                amount: absoluteDelta,
                reason: "Registration Change",
                purchase_type: rowData.purchase_type,
                direction: direction
            });

            // Only the portion of a decrease beyond what was already owed is
            // a real overpayment - see the comment in
            // updateRegistrationUpdateModalUI() for why that's not just
            // absoluteDelta itself.
            if (direction === "decrease" && result.issueHouseCredit && result.creditDue > 0) {
                const payoutEntries = USCTDP_Admin.createPayoutLedger({
                    event_id: "adjustment_" + timestampSeconds,
                    event: "Registration Change",
                    family_id: rowData.family_id,
                    student_id: rowData.student_id,
                    purchase_id: rowData.purchase_id,
                    amount: result.creditDue,
                    method: "house_credit",
                    reason: "Registration Change - Overpayment",
                    purchase_type: rowData.purchase_type
                });
                ledgerEntries.push(...payoutEntries);
            }

            return { cancelled: false, reviewed: true, ledgerEntries, discounts: result.discounts };
        }

        async function updateRegistration(rowData, fields) {
            const isActivityChange = fields.activity_id
                && parseInt(fields.activity_id, 10) !== parseInt(rowData.activity_id, 10);

            var review = null;
            if (isActivityChange) {
                review = await reviewPriceChange(rowData, fields.activity_id);
                if (review && review.cancelled) {
                    // Nothing has been saved yet - true no-op.
                    window.Swal.fire("Cancelled", "The registration was not changed.", "info");
                    return;
                }
            }

            const saveResponse = await USCTDP_Admin.ajax_saveRegistrationFields(rowData.registration_id, fields);
            if (!saveResponse.success) {
                throw Error("Failed to update registration.");
            }

            if (review) {
                // Keeps usctdp_purchase.discounts in sync with whatever
                // discount list actually applies now - otherwise the next
                // edit's "Current" column in Confirm Registration Update
                // would keep reading the stale, original-purchase snapshot
                // (see ajax_update_purchase()'s 'discounts' field). An empty
                // array serializes to no key at all in $.ajax's POST body,
                // so it'd never reach the server and the stale list would
                // silently survive - send '' instead, same convention
                // handleBatchSave() uses for phone_numbers/emails in
                // usctdp-mgmt-admin-families.js.
                await savePurchaseFields(rowData.purchase_id, {
                    discounts: review.discounts.length === 0 ? '' : review.discounts
                });
            }

            if (review && review.ledgerEntries.length > 0) {
                await USCTDP_Admin.ajax_submitLedgerEntries(review.ledgerEntries);
                window.Swal.fire("Saved!", "Price adjustment applied.", "success");
            } else if (review && review.reviewed) {
                // Reviewed and confirmed, but the final numbers matched what
                // was already owed - nothing to charge/credit.
                window.Swal.fire("No Change", "The reviewed price matches what was already charged - nothing to adjust.", "info");
            }
        }

        async function savePurchaseFields(id, fields) {
            const response = await $.ajax({
                url: usctdp_mgmt_admin.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: usctdp_mgmt_admin.update_purchase_action,
                    security: usctdp_mgmt_admin.update_purchase_nonce,
                    purchase_id: id,
                    ...fields
                }
            });
            return response;
        }

        var historyTable = $('#history-table').DataTable({
            processing: true,
            responsive: true,
            autoWidth: false,
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
                    d.action = usctdp_mgmt_admin.purchase_history_datatable_action;
                    d.security = usctdp_mgmt_admin.purchase_history_datatable_nonce;

                    var familyFilterValue = $('#family-filter').val();
                    if (familyFilterValue) {
                        d.family_id = familyFilterValue;
                    }

                    var studentFilterValue = $('#student-filter').val();
                    if (studentFilterValue) {
                        d.student_id = studentFilterValue;
                    }

                    var sessionFilterValue = $('#session-filter').val();
                    if (sessionFilterValue) {
                        d.session_id = sessionFilterValue;
                    }

                    var typeFilterValue = $('#type-filter').val();
                    if (typeFilterValue) {
                        d.type = typeFilterValue;
                    }

                    var statusFilterValue = $('#status-filter').val();
                    if (statusFilterValue) {
                        d.status = statusFilterValue;
                    } else {
                        d.status = "active";
                    }

                    if ($('#owes-filter').is(':checked')) {
                        d.owes = 1;
                    } else {
                        d.owes = 0;
                    }

                    // Purchases are stored in UTC; the date inputs are plain
                    // Y-m-d values interpreted as Eastern-time calendar days
                    // server-side (see ajax_purchase_history_datatable).
                    var dateFromValue = $('#date-from-filter').val();
                    if (dateFromValue) {
                        d.date_from = dateFromValue;
                    }

                    var dateToValue = $('#date-to-filter').val();
                    if (dateToValue) {
                        d.date_to = dateToValue;
                    }
                }
            },
            columns: [
                {
                    data: 'id',
                    render: function (data, type, row, meta) {
                        if (type === 'display') {
                            try {
                                if (row.purchase_type === 'registration') {
                                    return new RegistrationCard(row, meta.row, false).render();
                                } else if (row.purchase_type == 'merchandise') {
                                    return new MerchandiseCard(row, meta.row, false).render();
                                }
                            } catch (error) {
                                console.error(error);
                                return '';
                            }
                        }
                        return '';
                    }
                }
            ],
            initComplete: function () {
                if ($("#table-filter-row").length === 0) {
                    var $table_controls = $('#history-table_wrapper');
                    var $first_row = $table_controls.find("div.dt-layout-row").first();
                    var filter_row = "<div id='table-filter-row' class='dt-layout-row'></div>";
                    $first_row.after(filter_row);
                    $('#table-filters').appendTo('#table-filter-row');
                    $('.table-filter').on('change', function () {
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
                $('#cb-select-all').prop('checked', false);
                $(api.table().body()).find('.session-select').each(function () {
                    initSessionSelector($(this));
                });
                $(api.table().body()).find('.activity-select').each(function () {
                    initActivitySelector($(this), $(this).data('session-selector-id'));
                });
                $(api.table().body()).find('.payment-action-select').each(function () {
                    initPaymentActionSelect($(this));
                });

                updateBulkUI();
            }
        });

        function updateBulkUI() {
            const count = $('.row-check:checked').length;
            const $btn = $('#apply-bulk-btn');
            const $countText = $('#selected-count');
            const $selector = $('#bulk-action-selector');
            if (count > 0) {
                $countText.text(count);
                $('#selection-status').removeClass("hidden");
                if ($selector.val()) {
                    $btn.prop('disabled', false);
                } else {
                    $btn.prop('disabled', true);
                }
            } else {
                $btn.prop('disabled', true);
                $('#selection-status').addClass("hidden");
            }
        }

        function openPostPaymentModal(purchases) {
            paymentTable.clear();
            let count = 0;
            for (const purchase of purchases) {
                const adjustments = USCTDP_Admin.safeParseFloat(purchase.total_adjustments);
                const fees = USCTDP_Admin.safeParseFloat(purchase.total_fees);
                const payments = USCTDP_Admin.safeParseFloat(purchase.total_payments);
                const refunds = USCTDP_Admin.safeParseFloat(purchase.total_refunds);
                const houseCredits = USCTDP_Admin.safeParseFloat(purchase.total_house_credits);
                const netFees = fees - adjustments;
                const netPayments = payments - (refunds + houseCredits);
                const owed = netFees - netPayments;

                if (owed > 0) {
                    if (purchase.purchase_type === 'registration') {
                        paymentTable.addExistingRegistration(purchase);
                        count++;
                    } else if (purchase.purchase_type === 'merchandise') {
                        paymentTable.addExistingMerchandise(purchase);
                        count++;
                    }
                }
            }
            if (count > 0) {
                postPaymentModal.showModal();
            } else {
                alert("The selected registration(s) are already paid in full!");
            }
        }

        function generateBulkStatement(purchases) {
            if (purchases.length === 0) {
                return;
            }
            const familyId = purchases[0].family_id;
            const purchaseIds = purchases.map((purchase) => purchase.purchase_id);
            const $btn = $('#apply-bulk-btn');
            const $spinner = $('<span class="spinner is-active"></span>');
            $btn.prop('disabled', true);
            $btn.text('Working...');
            $btn.after($spinner);
            USCTDP_Admin.ajax_generateStatement(familyId, purchaseIds)
                .then((response) => {
                    window.open(response.doc_url, '_blank');
                })
                .catch((error) => {
                    window.Swal.fire({
                        title: "Error",
                        text: "Failed to generate statement. Inform a developer.",
                        icon: "error"
                    });
                })
                .finally(() => {
                    $btn.prop('disabled', false);
                    $btn.text('Apply');
                    $spinner.remove();
                });
        }

        const refundMode = $('#refund-mode');
        const methodWrapper = $('#method-field-wrapper');
        const modeDesc = $('#mode-description');
        const methodSelect = $('#refund-method');
        const refundFields = $('#refund-fields');
        const directionWrapper = $('#direction-field-wrapper');
        const directionSelect = $('#refund-direction');

        refundMode.on('change', (e) => {
            const val = e.target.value;
            methodSelect.val('').trigger('change');
            if (val === 'adjust_only') {
                directionWrapper.removeClass('hidden');
                refundFields.removeClass('hidden');
                methodWrapper.addClass('hidden');
                methodSelect.prop('required', false);
                directionSelect.prop('required', true);
                modeDesc.text("Adjusts the price, but does not record any transfer of funds.");
            } else if (val === 'payout_only') {
                directionWrapper.addClass('hidden');
                refundFields.removeClass('hidden');
                methodWrapper.removeClass('hidden');
                methodSelect.prop('required', true);
                directionSelect.prop('required', false);
                modeDesc.text("Records the transfer of funds for an already adjusted price.");
            } else if (val === 'standard') {
                directionWrapper.addClass('hidden');
                refundFields.removeClass('hidden');
                methodWrapper.removeClass('hidden');
                methodSelect.prop('required', true);
                directionSelect.prop('required', false);
                modeDesc.text("Adjusts the price and records the transfer of funds.");
            } else {
                directionWrapper.addClass('hidden');
                refundFields.addClass('hidden');
                methodWrapper.addClass('hidden');
                methodSelect.prop('required', false);
                directionSelect.prop('required', false);
                modeDesc.text("Select an action to continue.");
            }
        });

        methodSelect.on('change', (e) => {
            const val = e.target.value;
            if (val === 'check') {
                $('#check-number-field-wrapper').removeClass('hidden');
                $('#refund-check-number').prop('required', true);
            } else {
                $('#check-number-field-wrapper').addClass('hidden');
                $('#refund-check-number').prop('required', false);
            }
        });

        function openPostRefundModal(row) {
            refundFields.addClass('hidden');
            methodWrapper.addClass('hidden');
            methodSelect.prop('required', false);
            modeDesc.text("Select an action to continue.");
            $('#refund-form input').val('');
            $('#refund-form select').val('');
            $('#refund-form').data("purchaseId", row.purchase_id);
            $('#refund-form').data("purchaseType", row.purchase_type);
            $('#refund-form').data("studentId", row.student_id);
            $('#refund-form').data("familyId", row.family_id);
            postRefundModal.showModal();
        }

        $('#refund-form').on('submit', function (e) {
            const form = $('#refund-form')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            e.preventDefault();
            const action = refundMode.val();
            const amount = $('#refund-amount').val();
            const method = $('#refund-method').val();
            const reason = $('#refund-reason').val();
            const checkNumber = $('#refund-check-number').val();
            const direction = $('#refund-direction').val();
            const purchaseId = $('#refund-form').data("purchaseId");
            const purchaseType = $('#refund-form').data("purchaseType");
            const studentId = $('#refund-form').data("studentId");
            const familyId = $('#refund-form').data("familyId");
            const timestampSeconds = Math.floor(Date.now() / 1000);
            let entries = [];

            if (action === "adjust_only") {
                entries = USCTDP_Admin.createAdjustmentLedger({
                    event_id: "adjustment_" + timestampSeconds,
                    event: "Price Adjustment",
                    amount: amount,
                    reason: reason,
                    purchase_id: purchaseId,
                    purchase_type: purchaseType,
                    student_id: studentId,
                    family_id: familyId,
                    direction: direction
                });
            } else if (action === "payout_only") {
                entries = USCTDP_Admin.createPayoutLedger({
                    event_id: "payout_" + timestampSeconds,
                    event: "Refund Payout",
                    amount: amount,
                    method: method,
                    reason: reason,
                    purchase_id: purchaseId,
                    purchase_type: purchaseType,
                    student_id: studentId,
                    family_id: familyId,
                    check_number: checkNumber
                });
            } else if (action === "standard") {
                entries = USCTDP_Admin.createRefundLedger({
                    event_id: "refund_" + timestampSeconds,
                    event: "Refund",
                    amount: amount,
                    method: method,
                    reason: reason,
                    purchase_id: purchaseId,
                    purchase_type: purchaseType,
                    student_id: studentId,
                    family_id: familyId,
                    check_number: checkNumber
                });
            }

            USCTDP_Admin.ajax_submitLedgerEntries(entries)
                .then(() => {
                    postRefundModal.close();
                    historyTable.ajax.reload();
                    refreshFamilyBalance();
                })
                .catch((error) => {
                    alert("Failed to post refund: " + error.message);
                });
        });

        $('#close-refund-modal').on('click', () => {
            postRefundModal.close();
        });

        $('#bulk-action-selector').on('change', function () {
            updateBulkUI();
        });

        // Select All Click
        $('#cb-select-all').on('click', function () {
            var isChecked = $(this).prop('checked');
            $('#history-table tbody .row-check').prop('checked', isChecked);
            $('#history-table tbody tr .purchase-card').toggleClass('selected', isChecked);
            updateBulkUI();
        });

        // Individual Row Click
        $('#history-table tbody').on('change', '.row-check', function () {
            $(this).closest('.purchase-card').toggleClass('selected', this.checked);
            if (!this.checked) {
                $('#cb-select-all').prop('checked', false);
            }
            var totalOnPage = $('#history-table tbody .row-check').length;
            var totalChecked = $('#history-table tbody .row-check:checked').length;
            if (totalOnPage === totalChecked) {
                $('#cb-select-all').prop('checked', true);
            }
            updateBulkUI();
        });

        $('#history-table tbody').on('input', '.notes-input', function () {
            const $row = $(this).closest('tr');
            $row.find('.notes-section').addClass('is-dirty');
            $row.find('.save-notes-btn').prop('disabled', false);
        });

        $('#history-table tbody').on('click', '.save-notes-btn', function () {
            const $row = $(this).closest('tr');
            $row.find('.save-notes-btn').prop('disabled', true);
            var rowData = historyTable.row($row).data();
            var update = {
                notes: $row.find('.notes-input').first().val()
            }
            savePurchaseFields(rowData.purchase_id, update)
                .then(() => {
                    $row.find('.notes-section').removeClass('is-dirty');
                })
                .catch((error) => {
                    alert("Saving notes failed! " + error);
                });
        });

        $('#bulk-action-selector').select2({
            placeholder: "Select a bulk action...",
            allowClear: true,
            minimumResultsForSearch: Infinity
        });

        $('#apply-bulk-btn').on('click', function () {
            const action = $('#bulk-action-selector').val();
            const purchases = $('.row-check:checked').map(function () {
                const $row = $(this).closest("tr");
                return historyTable.row($row).data();
            }).get();

            if (action === 'post-payments') {
                openPostPaymentModal(purchases);
            } else if (action === 'generate-statement') {
                generateBulkStatement(purchases);
            }
        });

        $('#type-filter').select2({
            placeholder: "Filter by type...",
            allowClear: true
        });

        $('#status-filter').select2({
            placeholder: "Filter by status...",
            allowClear: true
        });

        $('#family-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Filter by family...",
                allowClear: true,
                target: 'family'
            })
        );

        $('#family-filter').on('change', function () {
            const familyId = $(this).val();
            $('#session-filter').val(null).trigger('change');
            $('#student-filter').val(null).trigger('change');
            if (familyId) {
                const title = $(this).find('option:selected').text();
                $('#family-name').text(title);
                $('#family-name-wrap').removeClass('hidden');
                $('#family-balance-section').removeClass('hidden');
                $('#student-filter').prop('disabled', false);
                refreshFamilyBalance();
            } else {
                $('#student-filter').prop('disabled', true);
                $('#family-name-wrap').addClass('hidden');
                $('#family-balance-section').addClass('hidden');
            }
        });

        $('#date-from-filter').on('change', function () {
            $('#date-to-filter').attr('min', $(this).val());
        });

        $('#date-to-filter').on('change', function () {
            $('#date-from-filter').attr('max', $(this).val());
        });

        $('#student-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Filter by student...",
                allowClear: true,
                target: 'student',
                filter: function () {
                    return {
                        family_id: $('#family-filter').val()
                    }
                }
            })
        );

        // Deliberately no active:1 filter here, unlike initSessionSelector()
        // above - this is browsing/searching past purchase history, where
        // archived sessions are exactly what staff need to still find.
        $('#session-filter').select2(
            USCTDP_Admin.select2Options({
                placeholder: "Filter by session...",
                allowClear: true,
                target: 'session'
            })
        );

        $('#close-payment-modal').on('click', () => {
            postPaymentModal.close();
        });

        $('#history-table tbody').on('change', '.payment-action-select', function () {
            const $row = $(this).closest('tr');
            const $select = $(this);
            const action = $select.val();
            if (action) {
                $row.find('.ledger-action').prop('disabled', false);
            } else {
                $row.find('.ledger-action').prop('disabled', true);
            }
        });

        $('#history-table tbody').on('click', '.ledger-action', function () {
            const $row = $(this).closest('tr');
            const rowData = historyTable.row($row).data();
            const $select = $row.find('.payment-action-select');
            const action = $select.val();
            if (action === 'post-payment') {
                openPostPaymentModal([rowData]);
            } else if (action === 'post-refund') {
                openPostRefundModal(rowData);
            }
        });

        $('#history-table tbody').on('change', '.session-select', function () {
            const activitySelectId = $(this).data('activity-selector-id');
            $('#' + activitySelectId).val(null).trigger("change");
        });

        $('#history-table tbody').on('click', 'button.edit-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            const $editButton = $(this);
            const saveButton = $row.find(".save-registration-btn");

            saveButton.removeClass("hidden");
            $editButton.addClass("hidden");
            $row.find('.purchase-card .registration-fields').addClass('editing');
            $row.find(".ledger-action").prop('disabled', true);
            $row.find('select').prop('disabled', false);
            $row.find('input').prop('readonly', false);
        });

        $('#history-table tbody').on('click', 'button.save-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            const $saveButton = $(this);
            const $editButton = $row.find(".edit-registration-btn");
            var rowData = historyTable.row($row).data();
            const activityId = $row.find('.activity-select').first().val();
            const studentLevel = $row.find('.level-input').first().val();

            if (!activityId) {
                window.Swal.fire({
                    icon: "error",
                    title: "Activity Required",
                    text: "Please select an activity before saving!",
                });
                return;
            }

            $row.find('.purchase-card .registration-fields').removeClass('editing');
            $row.find('select').prop('disabled', true);
            $row.find('input').prop('readonly', true);
            $row.find('textarea').prop('readonly', true);
            $saveButton.prop('disabled', true);

            var update = {
                activity_id: activityId,
                student_level: studentLevel
            }

            updateRegistration(rowData, update)
                .catch((error) => {
                    window.Swal.fire({
                        icon: "error",
                        title: "Error!",
                        text: "A server error occured. Please inform a developer. Details: " + error,
                    });
                })
                .finally(() => {
                    $saveButton.prop('disabled', false);
                    $saveButton.addClass('hidden');
                    $editButton.removeClass('hidden');
                    refreshFamilyBalance();
                    historyTable.ajax.reload();
                });
        });


        $('#history-table tbody').on('click', 'button.void-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            var rowData = historyTable.row($row).data();
            const studentName = `${rowData.student_first} ${rowData.student_last}`;

            window.Swal.fire({
                title: "Confirm Void Registration",
                html: `
                    Are you sure you want to void this registration? This will
                    remove student <b> ${studentName}</b> from the roster for:
                    <b> ${rowData.activity_name}</b>.
                `,
                showDenyButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `No`
            }).then((result) => {
                if (result.isConfirmed) {
                    USCTDP_Admin.ajax_setRegistrationStatus(rowData.registration_id, 'void')
                        .catch((error) => {
                            window.Swal.fire({
                                icon: "error",
                                title: "Error!",
                                text: "A server error occured. Please inform a developer. Details: " + error,
                            });
                        })
                        .finally(() => {
                            historyTable.ajax.reload();
                            refreshFamilyBalance();
                        });
                }
            });
        });

        $('#history-table tbody').on('click', 'button.restore-registration-btn', function (e) {
            const $row = $(this).closest('tr');
            var rowData = historyTable.row($row).data();
            const studentName = `${rowData.student_first} ${rowData.student_last}`;

            window.Swal.fire({
                title: "Confirm Restore Registration",
                html: `
                    Are you sure you want to restore this registration? This will
                    add student <b> ${studentName}</b> back to the roster for:
                    <b> ${rowData.activity_name}</b>.
                `,
                showDenyButton: true,
                confirmButtonText: "Yes",
                denyButtonText: `No`
            }).then((result) => {
                if (result.isConfirmed) {
                    USCTDP_Admin.ajax_setRegistrationStatus(rowData.registration_id, 'active')
                        .catch((error) => {
                            window.Swal.fire({
                                icon: "error",
                                title: "Error!",
                                text: "A server error occured. Please inform a developer. Details: " + error,
                            });
                        })
                        .finally(() => {
                            historyTable.ajax.reload();
                            refreshFamilyBalance();
                        });
                }
            });
        });

        $(`#${paymentTableId}`).on('payment:complete', function () {
            postPaymentModal.close();
            historyTable.ajax.reload();
            refreshFamilyBalance();
        });

        $('#history-table tbody').on('click', 'button.payment-history', function (e) {
            const $row = $(this).closest('tr');
            var rowData = historyTable.row($row).data();
            const purchaseId = rowData.purchase_id;
            const account = rowData.purchase_type + "_fees";
            const familyId = rowData.family_id;
            paymentHistoryModal.show(purchaseId, account, familyId);
        });

        if (usctdp_mgmt_admin.new_purchases) {
            newPurchases = new Set(usctdp_mgmt_admin.new_purchases)
        }

        if (usctdp_mgmt_admin.preload) {
            var preloadedFamilyId = null;
            var preloadedFamilyName = null;
            var preloadedStudent = null;

            if (usctdp_mgmt_admin.preload.family_id) {
                const preloadedFamily = Object.values(usctdp_mgmt_admin.preload.family_id)[0];
                preloadedFamilyId = preloadedFamily.id;
                preloadedFamilyName = preloadedFamily.title;
            }

            if (usctdp_mgmt_admin.preload.student_id) {
                preloadedStudent = Object.values(usctdp_mgmt_admin.preload.student_id)[0];
                preloadedFamilyId = preloadedStudent.family_id;
                preloadedFamilyName = preloadedStudent.family_name;
            }

            if (preloadedFamilyId) {
                // Populates and triggers 'change' on the family filter, which
                // loads the table scoped to this family and clears the
                // student filter (see the change handler above) - set before
                // the student option below so that doesn't get wiped out.
                // Left editable (unlike the old disabled context-selector)
                // so it can be cleared to view all purchases.
                const newFamilyOption = new Option(preloadedFamilyName, preloadedFamilyId, true, true);
                $('#family-filter').append(newFamilyOption).trigger('change');
            }

            if (preloadedStudent) {
                const newStudentOption = new Option(preloadedStudent.student_name, preloadedStudent.student_id, true, true);
                $('#student-filter').append(newStudentOption).trigger('change');
            }
        }

        // Nothing above triggered a load (no family/student preload) - load
        // the unfiltered "all purchases" view by default.
        if (!$('#family-filter').val()) {
            historyTable.ajax.reload();
        }
    });
})(jQuery);
