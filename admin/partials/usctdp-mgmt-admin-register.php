<div class="wrap">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>
    <div id="registration-container" class="edit-order-mode">
        <dialog id="view-roster-modal">
            <h2>Roster For: <span id="roster-activity-name"></span></h2>
            <div id="roster-shared-capacity-note" class="edit-note hidden">
                <span>
                    This class shares its capacity with: <strong id="roster-shared-capacity-list"></strong>.
                    The roster below only lists students registered for this specific class, so it may not
                    add up to the capacity total shown.
                </span>
            </div>
            <div id="view-roster-table-wrap">
                <table id="view-roster-table" class="usctdp-datatable">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Age</th>
                            <th>Level</th>
                            <th>Class</th>
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

        <dialog id="view-waitlist-modal">
            <h2>Waitlist For: <span id="waitlist-activity-name"></span></h2>
            <div id="view-waitlist-table-wrap">
                <table id="view-waitlist-table" class="usctdp-datatable">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Added At</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div class="actions-footer">
                <button type="button" class="button" id="close-view-waitlist-modal">Close</button>
            </div>
        </dialog>

        <div id="registration-info" class="flex-col gap-20">
            <div id="preloaded-data" class="flex-col gap-10 hidden">
                <div id="preloaded-family" class="hidden preload-field">
                    <span class="preload-label upper-heavy">Family</span>
                    <span id="preloaded-family-name"></span>
                </div>
                <div id="preloaded-student" class="hidden preload-field">
                    <span class="preload-label upper-heavy">Student</span>
                    <span id="preloaded-student-name"></span>
                </div>
                <div id="preloaded-session" class="hidden preload-field">
                    <span class="preload-label upper-heavy">Session</span>
                    <span id="preloaded-session-name"></span>
                </div>
                <div id="preloaded-activity" class="hidden preload-field">
                    <span class="preload-label upper-heavy">Activity</span>
                    <span id="preloaded-activity-name"></span>
                </div>
            </div>
            <div id="context-selection">
                <div id="context-selection-header" class="section-header">
                    <h2>Select Item</h2>
                </div>
                <div id="context-selectors" class="flex-col gap-10"></div>
            </div>
            <div id="notifications-section" class="hidden flex-col gap-10">
                <div id="notifications-section-header" class="section-header">
                    <h2>Issues</h2>
                </div>
                <div id="notifications-list" class="flex-col gap-10"></div>
            </div>
            <div id="preorder-details" class="flex-col gap-10 hidden">
                <div id="preorder-details-header" class="section-header">
                    <h2>Item Details</h2>
                </div>
                <div id="activity-preorder" class="preorder-subtype flex-col gap-20">
                    <div id="activity-basic-info" class="flex-col gap-10">
                        <h3>Basic Info</h3>
                        <div id="class-enrollment-info">
                            <div id="activity-capacity">
                                <span class="activity-capacity-label">Capacity</span>
                                <span class="activity-capacity-value">
                                    <span id="activity-current-size" class="fw-700"></span>
                                    <span class="activity-capacity-separator">out of</span>
                                    <span id="activity-max-size" class="fw-700"></span>
                                </span>
                                <div id="view-roster-wrap">
                                    <button id="view-roster-btn" class="button button-secondary">
                                        View Roster
                                    </button>
                                </div>
                            </div>
                            <div id="activity-waitlist">
                                <span class="activity-capacity-label">Waitlist</span>
                                <span class="activity-capacity-value blue-bg">
                                    <span id="activity-waitlist-size" class="fw-700"></span>
                                    <span class="activity-capacity-waiting">waiting</span>
                                </span>
                                <div id="view-waitlist-wrap">
                                    <button id="view-waitlist-btn" class="button button-secondary">
                                        View Waitlist
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="activity-shared-capacity-note" class="edit-note hidden">
                            <span>Capacity is shared with: <strong id="activity-shared-capacity-list"></strong></span>
                        </div>
                        <div id="activity-preorder-fields" class="flex-col">
                            <div id="student-level-field" class="field-row">
                                <label for="student-level">Student Level</label>
                                <input type="text" name="student-level" id="student-level">
                            </div>
                        </div>
                    </div>

                    <div id="price-setting" class="flex-col gap-10">
                        <h3>Pricing</h3>
                        <div id="activity-base-price-field" class="field-row">
                            <label for="activity_base_price">Base Price</label>
                            <input type="number" name="activity_base_price" id="activity_base_price">
                        </div>
                        <div id="activity-discounts" class="flex-col gap-5">
                            <h4>Discounts</h4>
                            <div id="clinic-only-discounts" class="type-only-discounts hidden flex-col gap-5">
                                <div class="field-row discount-field">
                                    <input type="checkbox" name="discount-additional-day" id="discount-additional-day">
                                    <label for="discount-additional-day">
                                        Second Day
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
                            </div>
                            <div id="tournament-only-discounts" class="type-only-discounts hidden flex-col gap-5">
                                <div class="field-row discount-field">
                                    <input type="checkbox" name="discount-early-signup" id="discount-early-signup">
                                    <label for="discount-early-signup">
                                        Early Signup
                                        <span id="discount-early-signup-value"></span>
                                    </label>
                                </div>
                                <div class="field-row discount-field">
                                    <input type="checkbox" name="discount-with-clinic" id="discount-with-clinic">
                                    <label for="discount-with-clinic">
                                        With Clinic
                                        <span id="discount-with-clinic-value"></span>
                                    </label>
                                </div>
                            </div>

                            <div id="sale-price-wrap" class="flex-row gap-10 align-center">
                                <span class="sale-price-label">Sale Price:</span>
                                <span id="sale-price-value"></span>
                            </div>
                        </div>
                        <div id="activity-addons" class="flex-col gap-5">
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
                    <div id="activity-notes-section" class="notes-section flex-col gap-5">
                        <h3>Notes</h3>
                        <textarea name="activity-notes" id="activity-notes" rows="3" class="notes"></textarea>
                    </div>
                    <div class="add-item-wrap">
                        <button id="add-activity-registration" class="button button-primary">
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