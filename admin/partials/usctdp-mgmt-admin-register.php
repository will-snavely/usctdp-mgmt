<?php
$page_title = get_admin_page_title();
$post_handler = Usctdp_Mgmt_Admin::$post_handlers["registration"];
$submit_hook = $post_handler["submit_hook"];
$nonce_name = $post_handler["nonce_name"];
$nonce_action = $post_handler["nonce_action"];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        method="post"
        id="register-form">
        <div id="context-selection-section">
            <div id="session-selection-section">
                <h2> Select a Session </h2>
                <select id="session-selector" name="session_id"></select>
            </div>
            <div id="class-selection-section">
                <h2> Select a Class </h2>
                <select id="class-selector" name="class_id"></select>
            </div>
            <div id="student-selection-section">
                <h2> Select a Student </h2>
                <select id="student-selector" name="student_id"></select>
            </div>
            <div id="notifications-section"></div>

            <div id="registration-section">
                <h2> Registration </h2>
                <div id="class-capacity-section">
                    <p>
                        <strong>Current Enrollment:</strong>
                        <span id="class-current-size"></span>
                        out of
                        <span id="class-max-size"></span>
                    </p>
                </div>

                <div id="registration-fields-section">
                    <div id="payment-status-section">
                        <label for="payment-status">Payment Status</label>
                        <div>
                            <input type="radio" id="paid" name="payment_status" value="paid" checked />
                            <label for="paid">Payment Received</label>
                        </div>
                        <div>
                            <input type="radio" id="not-paid" name="payment_status" value="not-paid" />
                            <label for="not-paid">Payment Pending</label>
                        </div>
                    </div>
                    <div id="existing-payment-info-section" class="field-list">
                        <div id=" payment-method-section" class="form-field">
                            <label for="payment-method">Payment Method</label>
                            <select id="payment-method">
                                <option value="">Select a Payment Method</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="credit-card">Credit Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="payment-amount">Payment Amount</label>
                            <input type="number" id="payment-amount-existing">
                        </div>
                        <div class="form-field">
                            <label for="payment-date">Payment Date</label>
                            <input type="date" id="payment-date">
                        </div>
                    </div>
                    <div id="payment-required-section" class="field-list">
                        <div class="form-field">
                            <label for="payment-amount-pending">Amount Pending</label>
                            <input type="number" id="payment-amount-pending">
                        </div>
                    </div>
                    <div id="notes-section">
                        <label for="notes">Notes</label>
                        <textarea id="notes" rows="5"></textarea>
                    </div>
                </div>

                <input type="hidden" name="action" value="<?php echo esc_attr($submit_hook); ?>">
                <?php wp_nonce_field($nonce_action, $nonce_name); ?>
                <?php submit_button('Register', 'primary', 'registration_submit'); ?>
            </div>
        </div>
    </form>
</div>