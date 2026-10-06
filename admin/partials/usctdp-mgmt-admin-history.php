<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="main-content" class="flex-col gap-10">
        <dialog id="post-payment-modal">
            <h2>Post Payment</h2>
            <div id="registration-payment-table"></div>
            <div class="actions-footer">
                <button type="button" class="button" id="close-payment-modal">Cancel</button>
            </div>
        </dialog>

        <dialog id="post-refund-modal">
            <h2>Financial Adjustment & Refund</h2>
            <form id="refund-form">
                <div id="refund-action" class="flex-col gap-10">
                    <label for="refund-mode">Action Type</label>
                    <select id="refund-mode" name="refund-mode" required>
                        <option value="">Select Action</option>
                        <option value="standard">Refund (Adjustment + Payout)</option>
                        <option value="adjust_only">Adjustment Only</option>
                        <option value="payout_only">Payout Only</option>
                    </select>
                    <div class="field-help">
                        <span id="mode-description">Select an action to continue.</span>
                    </div>
                </div>
                <div id="refund-fields" class="modal_field_group hidden">
                    <div class="modal_field" id="direction-field-wrapper">
                        <label for="refund-direction">Adj. Type</label>
                        <select id="refund-direction" name="refund-direction">
                            <option value="">Select Direction</option>
                            <option value="decrease">Price Decrease</option>
                            <option value="increase">Price Increase</option>
                        </select>
                    </div>
                    <div class="modal_field">
                        <label for="refund-amount">Amount ($)</label>
                        <input type="number" id="refund-amount" name="refund-amount" step="0.01" min="0" required
                            placeholder="0.00">
                    </div>
                    <div class="modal_field" id="method-field-wrapper">
                        <label for="refund-method">Payout Method</label>
                        <select id="refund-method" name="refund-method" required>
                            <option value="">Select Method</option>
                            <option value="house_credit">House Credit</option>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="card">Card/PayPal</option>
                        </select>
                    </div>
                    <div class="modal_field hidden" id="check-number-field-wrapper">
                        <label for="refund-check-number">Check #</label>
                        <input type="text" id="refund-check-number" name="refund-check-number">
                    </div>
                    <div class="modal_field">
                        <label for="refund-reason">Reason / Internal Note</label>
                        <input type="text" id="refund-reason" name="refund-reason" required
                            placeholder="e.g., Injury, Class move, Sibling discount">
                    </div>
                </div>

                <div class="actions-footer">
                    <button type="submit" class="button button-primary" id="post-refund-btn">
                        Submit
                    </button>
                    <button type="button" class="button" id="close-refund-modal">Cancel</button>
                </div>
            </form>
        </dialog>

        <dialog id="modify-registration-modal" class="modify-registration-modal">
            <h2>Modify Registration</h2>
            <div id="modify-registration-selectors" class="flex-col gap-10">
                <!-- Forces the Family/Student/Level -> Session/Clinic -> Day
                     grouping regardless of how much horizontal room is
                     available - see the "order"/"flex-basis: 100%" rules on
                     .selector-row-break in usctdp-mgmt-admin-history.css.
                     CascasdingSelect appends its generated sections after
                     these two (DOM order doesn't matter for the "order"
                     property, only which row each one visually lands in). -->
                <div class="selector-row-break" id="modify-selectors-break-1"></div>
                <div class="selector-row-break" id="modify-selectors-break-2"></div>
                <div class="modify-registration-level-field">
                    <label for="modify-registration-level">Level</label>
                    <input type="text" id="modify-registration-level">
                </div>
            </div>
            <!-- Any price/discount impact from an activity change is
                 reviewed separately, in #confirm-registration-update-modal -
                 same modal + reviewPriceChange()/updateRegistration() flow
                 the tournament/legacy inline edit already uses (see
                 usctdp-mgmt-admin-history.js), including its rule that
                 nothing is shown at all when the new activity's base price
                 matches what's already on file. -->
            <div class="actions-footer">
                <button type="button" class="button button-primary" id="save-modify-registration-btn">
                    Save
                </button>
                <button type="button" class="button" id="cancel-modify-registration-btn">Cancel</button>
            </div>
        </dialog>

        <!-- Camp's variant of the modal above (see openModifyCampRegistrationModal()
             in usctdp-mgmt-admin-history.js) - Family/Student/Level work the
             same way, but there's no Clinic/Day step (a camp session always
             resolves to its one activity, same as the register page's
             tournament/camp handling) - Session is instead followed
             directly by a day-picker, reusing the exact same table/
             checkbox markup+styling the register page's own picker uses
             (USCTDP_Admin.renderCampDayPicker() in usctdp-mgmt-admin.js). -->
        <dialog id="modify-camp-registration-modal" class="modify-registration-modal">
            <h2>Modify Camp Registration</h2>
            <div id="modify-camp-registration-selectors" class="flex-col gap-10">
                <div class="selector-row-break" id="modify-camp-selectors-break-1"></div>
                <div class="modify-registration-level-field">
                    <label for="modify-camp-registration-level">Level</label>
                    <input type="text" id="modify-camp-registration-level">
                </div>
            </div>
            <div id="modify-camp-days-field" class="flex-col gap-5">
                <label class="upper-heavy">Days</label>
                <div id="modify-camp-days-wrap"></div>
                <p id="modify-camp-days-note" class="camp-days-modal-note"></p>
            </div>
            <div class="actions-footer">
                <button type="button" class="button button-primary" id="save-modify-camp-registration-btn">
                    Save
                </button>
                <button type="button" class="button" id="cancel-modify-camp-registration-btn">Cancel</button>
            </div>
        </dialog>

        <!-- Travel Team's variant of the modal above (see
             openModifyTravelTeamRegistrationModal() in
             usctdp-mgmt-admin-history.js) - Family/Student/Level/Days work
             the same way as the camp modal, but Session is followed by two
             more real CascasdingSelect-managed levels (Package, then Camp -
             reusing the register page's own 'travel_package'/
             'travel_camp_option' select2 targets) instead of going
             straight to the day-picker, since a Travel Team session
             doesn't resolve to one camp activity on its own the way a
             plain camp session does. -->
        <dialog id="modify-travel-team-registration-modal" class="modify-registration-modal">
            <h2>Modify Travel Team Registration</h2>
            <div id="modify-travel-team-registration-selectors" class="flex-col gap-10">
                <div class="selector-row-break" id="modify-travel-team-selectors-break-1"></div>
                <div class="modify-registration-level-field">
                    <label for="modify-travel-team-registration-level">Level</label>
                    <input type="text" id="modify-travel-team-registration-level">
                </div>
                <div class="selector-row-break" id="modify-travel-team-selectors-break-2"></div>
            </div>
            <div id="modify-travel-team-days-field" class="flex-col gap-5">
                <label class="upper-heavy">Days</label>
                <div id="modify-travel-team-days-wrap"></div>
                <p id="modify-travel-team-days-note" class="camp-days-modal-note"></p>
            </div>
            <div class="actions-footer">
                <button type="button" class="button button-primary" id="save-modify-travel-team-registration-btn">
                    Save
                </button>
                <button type="button" class="button" id="cancel-modify-travel-team-registration-btn">Cancel</button>
            </div>
        </dialog>

        <dialog id="confirm-registration-update-modal">
            <h2>Confirm Registration Update</h2>
            <div class="registration-update-columns">
                <div class="registration-update-column update-column-current">
                    <h3>Current</h3>
                    <div class="registration-update-base-price">
                        <label>Base Price</label>
                        <span id="current-base-price-display"></span>
                    </div>
                    <div id="current-discounts-list" class="registration-update-discounts-list"></div>
                    <div class="registration-update-net-price">
                        <label>Net Price</label>
                        <span id="current-net-price-display"></span>
                    </div>
                </div>
                <div class="registration-update-column update-column-new">
                    <h3>New</h3>
                    <div class="registration-update-base-price">
                        <label>Base Price</label>
                        <span id="new-base-price-display"></span>
                    </div>
                    <div id="new-discounts-list" class="registration-update-discounts-list"></div>
                    <div id="add-discount-wrap" class="flex-row gap-5 align-center">
                        <select id="new-discount-type">
                            <option value="">Add Discount...</option>
                            <option value="second_day">Addl. Day</option>
                            <option value="sibling_10">Sibling (10%)</option>
                            <option value="sibling_20">Sibling (20%)</option>
                            <option value="custom_flat">Custom Flat</option>
                            <option value="custom_percent">Custom Percent</option>
                        </select>
                        <input type="number" id="new-discount-value" step="0.01" min="0" class="hidden"
                            placeholder="Value">
                        <input type="text" id="new-discount-reason" class="hidden" placeholder="Reason">
                        <button type="button" class="button button-small" id="add-new-discount-btn">Add</button>
                    </div>
                    <div class="registration-update-net-price">
                        <label>Net Price</label>
                        <span id="new-net-price-display"></span>
                    </div>
                    <div id="override-net-price-wrap">
                        <label>
                            <input type="checkbox" id="override-net-price-checkbox">
                            Override net price
                        </label>
                        <input type="number" id="override-net-price-value" step="0.01" min="0" class="hidden"
                            placeholder="Net Price">
                    </div>
                </div>
            </div>
            <div id="registration-update-delta" class="registration-update-delta"></div>
            <div id="house-credit-option-wrap" class="hidden">
                <label>
                    <input type="checkbox" id="issue-house-credit-checkbox">
                    Issue <span id="house-credit-amount-display"></span> as house credit
                    (instead of an unresolved credit balance)
                </label>
            </div>
            <div class="actions-footer">
                <button type="button" class="button button-primary" id="confirm-registration-update-btn">
                    Confirm
                </button>
                <button type="button" class="button" id="cancel-registration-update-btn">Cancel</button>
            </div>
        </dialog>

        <div id="payment-history-modal-container"></div>

        <div id="history-container" class="w-100">
            <h2>Purchase History<span id="family-name-wrap" class="hidden"> for <span id="family-name"></span></span></h2>
            <div id="family-balance-section" class="hidden flex-row gap-10">
                <div class="family-financial-summary">
                    <label>Balance</label>
                    <span id="family-total-balance" class="balance-amt"></span>
                </div>
                <div class="family-financial-summary">
                    <label>Credit</label>
                    <span id="family-total-house-credit" class="balance-amt green-bg"></span>
                </div>
            </div>
            <div id="history-table-wrap w-100">
                <div id="table-filters">
                    <div class="filter-row">
                        <div id="family-filter-section" class="filter-item">
                            <select id="family-filter" class="table-filter"></select>
                        </div>
                        <div id="student-filter-section" class="filter-item">
                            <select id="student-filter" class="table-filter" disabled></select>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div id="session-filter-section" class="filter-item">
                            <select id="session-filter" class="table-filter"></select>
                        </div>
                        <div id="type-filter-section" class="filter-item">
                            <select id="type-filter" class="table-filter">
                                <option value=""></option>
                                <option value="registration">Registration</option>
                                <option value="merchandise">Merchandise</option>
                            </select>
                        </div>
                        <div id="status-filter-section" class="filter-item">
                            <select id="status-filter" class="table-filter">
                                <option value=""></option>
                                <option value="active">Active</option>
                                <option value="void">Void</option>
                            </select>
                        </div>
                        <div id="owes-filter-section" class="filter-item flex-row gap-5 align-center">
                            <label for="owes-filter">Owes Money:</label>
                            <input type="checkbox" id="owes-filter" name="owes-filter" value="1" class="table-filter">
                        </div>
                    </div>
                    <div class="filter-row">
                        <div id="date-from-filter-section" class="filter-item flex-row gap-5 align-center">
                            <label for="date-from-filter" class="table-filter-label">From</label>
                            <input type="date" id="date-from-filter" class="table-filter" name="date-from-filter">
                        </div>
                        <div id="date-to-filter-section" class="filter-item flex-row gap-5 align-center">
                            <label for="date-to-filter" class="table-filter-label">To</label>
                            <input type="date" id="date-to-filter" class="table-filter" name="date-to-filter">
                        </div>
                    </div>
                    <div class="filter-row">
                        <!-- Exports every row matching the filters above,
                             not just the current page - see exportHistory()
                             in usctdp-mgmt-admin-history.js. -->
                        <div id="export-section" class="filter-item flex-row gap-5 align-center">
                            <button type="button" id="export-csv-btn" class="button">Export CSV</button>
                            <button type="button" id="export-print-btn" class="button">Print / PDF</button>
                        </div>
                    </div>
                </div>

                <table id="history-table" class="w-100">
                    <thead>
                        <tr>
                            <th>
                                <div class="table-header-controls">
                                    <div class="flex-row gap-5 align-center">
                                        <input id="cb-select-all" type="checkbox" class="cb-select-all">
                                        <label for="cb-select-all">Select All Visible</label>
                                    </div>
                                    <div class="bulk-actions flex-row gap-5 align-center flex-wrap">
                                        <select id="bulk-action-selector">
                                            <option value=""></option>
                                            <option value="post-payments">Post Payment</option>
                                            <option value="generate-statement">Generate Statement</option>
                                        </select>
                                        <button id="apply-bulk-btn" class="button action" disabled>
                                            Apply
                                        </button>
                                        <span id="selection-status" class="count-badge hidden">
                                            <span id="selected-count">0</span> item(s) selected
                                        </span>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>