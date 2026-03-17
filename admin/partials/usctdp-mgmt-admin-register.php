<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="registration-container">
        <div id="registration-info">
            <div id="context-selection">
                <div id="context-selectors"></div>
                <div id="notifications-section"></div>
            </div>
            <div id="preorder-details" class="hidden">
                <div id="clinic-preorder" class="preorder-subtype">
                    <div id="clinic-info">
                        <div id="clinic-info-capacity" class="clinic-info-item">
                            <label>Capacity</label>
                            <span class="clinic-info-value">
                                <span id="clinic-current-size"></span>
                                <span class="clinic-capacity-separator">out of</span>
                                <span id="clinic-max-size"></span>
                            </span>
                        </div>
                        <div id="clinic-info-one-day-price" class="clinic-info-item">
                            <label>One Day $</label>
                            <span class="clinic-info-value neutral" id="clinic-one-day-price"></span>
                        </div>
                        <div id="clinic-info-two-day-price" class="clinic-info-item">
                            <label>Two Day $</label>
                            <span class="clinic-info-value neutral" id="clinic-two-day-price"></span>
                        </div>
                    </div>
                    <div id="clinic-preorder-fields" class="preorder-fields">
                        <div id="student-level-field" class="registration-field">
                            <label for="student-level">Student Level</label>
                            <input type="text" name="student-level" id="student-level">
                        </div>
                        <div id="add-racket-field" class="registration-field">
                            <label for="add_racket">Add Racket</label>
                            <input type="checkbox" name="add_racket" id="add_racket">
                        </div>
                        <div id="racket-fee-field" class="registration-field hidden">
                            <label for="racket_fee">Racket Fee</label>
                            <input type="number" name="racket_fee" id="racket_fee">
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
                        <button id="add-merchandise-registration" class="button button-primary">
                            Add Merchandise
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="payment-table-section" class="hidden"></div>
    </div>
</div>