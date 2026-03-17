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
            </div>
        </div>
        <div id="payment-table-section" class="hidden"></div>
    </div>
</div>