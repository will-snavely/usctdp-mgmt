<div class="wrap">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>
    <div id="registration-container" class="edit-order-mode">
        <dialog id="view-roster-modal">
            <h2>Roster For: <span id="roster-clinic-name"></span></h2>
            <div id="view-roster-table-wrap">
                <table id="view-roster-table" class="usctdp-datatable">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Age</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div class="actions-footer">
                <button type="button" class="button" id="close-view-roster-modal">Close</button>
            </div>
        </dialog>
        <div id="registration-info" class="flex-col gap-20">
            <div id="context-selection">
                <div id="context-selection-header" class="section-header">
                    <h2>Select Item</h2>
                </div>
                <div id="context-selectors" class="flex-col gap-10"></div>
                <div id="notifications-section" class="hidden flex-col gap-10"></div>
            </div>
            <div id="preorder-details" class="flex-col gap-10 hidden">
                <div id="preorder-details-header" class="section-header">
                    <h2>Item Details</h2>
                </div>
                <div id="clinic-preorder" class="preorder-subtype flex-col gap-20">
                    <div id="clinic-basic-info" class="flex-col gap-10">
                        <h3>Basic Info</h3>
                        <div id="clinic-capacity" class="flex-row gap-10 align-center">
                            <span class="clinic-capacity-label">Capacity</span>
                            <span class="clinic-capacity-value">
                                <span id="clinic-current-size"></span>
                                <span class="clinic-capacity-separator">out of</span>
                                <span id="clinic-max-size"></span>
                            </span>
                            <div id="view-roster-wrap">
                                <button id="view-roster-btn" class="button button-secondary">
                                    View Roster
                                </button>
                            </div>
                        </div>
                        <div id="clinic-preorder-fields" class="flex-col">
                            <div id="student-level-field" class="field-row">
                                <label for="student-level">Student Level</label>
                                <input type="text" name="student-level" id="student-level">
                            </div>
                        </div>
                    </div>

                    <div id="price-setting" class="flex-col gap-10">
                        <h3>Pricing</h3>
                        <div id="clinic-base-price-field" class="field-row">
                            <label for="clinic_base_price">Base Price</label>
                            <input type="number" name="clinic_base_price" id="clinic_base_price">
                        </div>
                        <div id="clinic-discounts" class="flex-col gap-5">
                            <h4>Discounts</h4>
                            <div class="field-row discount-field">
                                <input type="checkbox" name="discount-additional-day" id="discount-additional-day">
                                <label for="discount-additional-day">
                                    Additional Day
                                    <span id="discount-additional-day-value"></span>
                                </label>
                            </div>
                            <div class="field-row discount-field">
                                <input type="checkbox" name="discount-sibling" id="discount-sibling">
                                <label for="discount-sibling">Sibling Discount</label>
                                <select name="discount-sibling-percent" id="discount-sibling-percent" disabled>
                                    <option value="10">10%</option>
                                    <option value="20">20%</option>
                                </select>
                            </div>

                            <div id="sale-price-wrap" class="flex-row gap-10 align-center">
                                <span class="sale-price-label">Sale Price:</span>
                                <span id="sale-price-value"></span>
                            </div>
                        </div>
                        <div id="clinic-addons" class="flex-col gap-5">
                            <h4>Add-ons</h4>
                            <div id="add-racket-field" class="field-row addon-field">
                                <input type="checkbox" name="add_racket" id="add_racket">
                                <label for="add_racket">Add Racket</label>
                            </div>
                            <div id="add-tshirt-field" class="field-row addon-field">
                                <input type="checkbox" name="add_tshirt" id="add_tshirt">
                                <label for="add_tshirt">Add T-Shirt</label>
                            </div>
                        </div>
                    </div>
                    <div id="clinic-notes-section" class="notes-section flex-col gap-5">
                        <h3>Notes</h3>
                        <textarea name="clinic-notes" id="clinic-notes" rows="3" class="notes"></textarea>
                    </div>
                    <div class="add-item-wrap">
                        <button id="add-clinic-registration" class="button button-primary">
                            Add Registration
                        </button>
                    </div>
                </div>
                <div id="new-session-preorder" class="preorder-subtype">
                    <div id="new-session-preorder-fields" class="flex-col gap-10">
                        <div id="new-session-name-field" class="field-row">
                            <label for="new-session-name">Session Name</label>
                            <input type="text" name="new-session-name" id="new-session-name">
                        </div>
                        <div id="new-session-price-field" class="field-row">
                            <label for="new-session-price">Session Price</label>
                            <input type="number" name="new-session-price" id="new-session-price">
                        </div>
                        <div id="new-session-start-date-field" class="field-row">
                            <label for="new-session-start-date">Start Date</label>
                            <input type="date" name="new-session-start-date" id="new-session-start-date">
                        </div>
                        <div id="new-session-end-date-field" class="field-row">
                            <label for="new-session-end-date">End Date</label>
                            <input type="date" name="new-session-end-date" id="new-session-end-date">
                        </div>
                    </div>
                    <div class="add-item-wrap">
                        <button id="add-new-session-registration" class="button button-primary">
                            Create Session and Register
                        </button>
                    </div>
                </div>
                <div id="merch-preorder" class="preorder-subtype flex-col gap-20">
                    <div id="price-setting" class="flex-col gap-10">
                        <h3>Pricing</h3>
                        <div id="merch-base-price-field" class="field-row">
                            <label for="merch_base_price">Base Price</label>
                            <input type="number" name="merch_base_price" id="merch_base_price">
                        </div>
                    </div>
                    <div class="add-item-wrap">
                        <button id="add-merchandise" class="button button-primary">
                            Add Merchandise
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="payment-table-section" class="hidden"></div>
    </div>
</div>