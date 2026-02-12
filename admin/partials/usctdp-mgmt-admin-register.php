<?php
$page_title = get_admin_page_title();
$post_handler = Usctdp_Mgmt_Admin::$post_handlers["registration"];
$submit_hook = $post_handler["submit_hook"];
$nonce_name = $post_handler["nonce_name"];
$nonce_action = $post_handler["nonce_action"];

$registration_fields = [
    'student-level' => 'Student Level',
    'payment-amount-outstanding' => 'Outstanding Balance',
    'payment-amount-paid' => 'Amount Paid',
    'payment-method' => 'Payment Method',
    'check-number' => 'Check Number',
    'notes' => 'Notes'
];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="registration-container">
        <form
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            method="post"
            id="register-form">
            <div id="registration-form-content">
                <div id="context-selection-section">
                    <div id="context-selectors"></div>
                    <div id="notifications-section"></div>
                </div>

                <div id="registration-details-section" class="hidden">
                    <h2> Registration Details</h2>
                    <div>
                        <strong>Current Enrollment:</strong>
                        <span id="class-current-size"></span>
                        out of
                        <span id="class-max-size"></span>
                    </div>
                    <div id="registration-fields-section">
                        <div id="student-level-field" class="form-field">
                            <label for="student-level">Student Level</label>
                            <input type="number" name="student-level" id="student-level">
                        </div>
                        <div id="payment-amount-outstanding-field" class="form-field">
                            <label for="payment-amount-outstanding">Outstanding Balance</label>
                            <input type="number" name="amount-outstanding" id="payment-amount-outstanding">
                        </div>
                        <div id="payment-amount-paid-field" class="form-field">
                            <label for="payment-amount-paid">Amount Paid</label>
                            <input type="number" name="amount-paid" id="payment-amount-paid">
                        </div>
                        <div id="payment-method-field" class="form-field">
                            <label for="payment-method">Payment Method</label>
                            <select name="payment-method" id="payment-method">
                                <option value="">Select...</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="card">Card (PayPal) </option>
                            </select>
                        </div>
                        <div id="check-fields" class="hidden">
                            <div id="check-number-field" class="form-field">
                                <label for="check-number">Check Number</label>
                                <input type="text" name="check-number" id="check-number">
                            </div>
                            <div id="check-received-date-field" class="form-field">
                                <label for="check-received-date">Date Received</label>
                                <input type="date" name="check-received-date" id="check-received-date">
                            </div>
                            <div id="check-cleared-date-field" class="form-field">
                                <label for="check-cleared-date">Date Cleared</label>
                                <input type="date" name="check-cleared-date" id="check-cleared-date">
                            </div>
                        </div>
                        <div id="card-fields" class="hidden">
                            <div id="card-transaction-id-field" class="form-field">
                                <label for="card-transaction-id">PayPal Transaction ID</label>
                                <input type="text" name="card-transaction-id" id="card-transaction-id">
                            </div>
                            <div id="card-charged-date-field" class="form-field">
                                <label for="card-charged-date">Date Charged</label>
                                <input type="date" name="card-charged-date" id="card-charged-date">
                            </div>
                        </div>
                        <div id="notes-section">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="5"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="action" value="<?php echo esc_attr($submit_hook); ?>">
                    <?php wp_nonce_field($nonce_action, $nonce_name); ?>
                    <div id="registration-submit-button-wrap">
                        <?php submit_button('Register Student', 'primary', 'registration_submit', false); ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div id="registration-related-data-section">
        <div id="registration-history-section" class="hidden">
            <h2> Registration History for <span id="student-name-history"></span></h2>
            <div id="registration-history-table-wrap">
                <table id="registration-history-table" class="usctdp-custom-post-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Session</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="class-roster-section" class="hidden">
            <h2> Roster for <span id="class-roster-name"></span></h2>
            <div id="class-roster-table-wrap">
                <table id="class-roster-table" class="usctdp-custom-post-table">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>