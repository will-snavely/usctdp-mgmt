<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="registration-container" class="edit-order-mode">
        <div id="registration-info">
            <div id="context-selection">
                <div id="context-selection-header">
                    <h2>Select Item</h2>
                </div>
                <div id="context-selectors"></div>
                <div id="notifications-section" class="hidden"></div>
            </div>
            <div id="preorder-details" class="hidden">
                <div id="preorder-details-header">
                    <h2>Item Details</h2>
                </div>
                <div id="clinic-preorder" class="preorder-subtype">
                    <div id="clinic-info-capacity" class="clinic-info-item">
                        <div id="capacity-header">
                            <h3>Capacity</h3>
                        </div>
                        <div id="capacity-body">
                            <span class="clinic-info-value">
                                <span id="clinic-current-size"></span>
                                <span class="clinic-capacity-separator">out of</span>
                                <span id="clinic-max-size"></span>
                            </span>
                            <div id="view-roster-wrap">
                                <button id="view-roster" class="button button-secondary">
                                    View Roster
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="clinic-preorder-fields" class="preorder-fields">
                        <div id="student-level-field" class="registration-field">
                            <label for="student-level">Student Level</label>
                            <input type="text" name="student-level" id="student-level">
                        </div>
                        <div id="price-setting">
                            <div id="sale-price-field">
                                <label for="sale_price">Sale Price</label>
                                <input type="number" name="sale_price" id="sale_price">
                            </div>
                            <div id="clinic-discounts">
                                <h2>Discounts</h2>
                                <div class="discount-field">
                                    <input type="checkbox" name="discount-additional-day" id="discount-additional-day">
                                    <label for="discount-additional-day">Additional Day</label>
                                </div>
                                <div class="discount-field">
                                    <input type="checkbox" name="discount-sibling" id="discount-sibling">
                                    <label for="discount-sibling">Sibling Discount</label>
                                    <input type="number" name="discount-sibling-percent" id="discount-sibling-percent">
                                </div>
                            </div>
                            <div id="clinic-addons">
                                <h2>Add-ons</h2>
                                <div id="add-racket-field">
                                    <input type="checkbox" name="add_racket" id="add_racket">
                                    <label for="add_racket">Add Racket</label>
                                </div>
                                <div id="add-tshirt-field">
                                    <input type="checkbox" name="add_tshirt" id="add_tshirt">
                                    <label for="add_tshirt">Add T-Shirt</label>
                                </div>
                            </div>
                        </div>
                        <div id="clinic-notes-section" class="notes-section">
                            <label for="clinic-notes">Notes</label>
                            <textarea name="clinic-notes" id="clinic-notes" rows="3" class="notes"></textarea>
                        </div>
                    </div>
                    <div id="add-item-wrap">
                        <button id="add-clinic-registration" class="button button-primary">
                            Add Registration
                        </button>
                    </div>
                </div>
                <div id="new-session-preorder" class="preorder-subtype">
                    <div id="new-session-preorder-fields" class="preorder-fields">
                        <div id="new-session-name-field" class="registration-field">
                            <label for="new-session-name">Session Name</label>
                            <input type="text" name="new-session-name" id="new-session-name">
                        </div>
                        <div id="new-session-price-field" class="registration-field">
                            <label for="new-session-price">Session Price</label>
                            <input type="number" name="new-session-price" id="new-session-price">
                        </div>
                        <div id="new-session-start-date-field" class="registration-field">
                            <label for="new-session-start-date">Start Date</label>
                            <input type="date" name="new-session-start-date" id="new-session-start-date">
                        </div>
                        <div id="new-session-end-date-field" class="registration-field">
                            <label for="new-session-end-date">End Date</label>
                            <input type="date" name="new-session-end-date" id="new-session-end-date">
                        </div>
                    </div>
                    <div id="add-item-wrap">
                        <button id="add-new-session-registration" class="button button-primary">
                            Create Session and Register
                        </button>
                    </div>
                </div>
                <div id="merch-preorder" class="preorder-subtype">
                    <div id="add-item-wrap">
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