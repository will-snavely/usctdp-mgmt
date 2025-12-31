<?php
$page_title = get_admin_page_title();
$post_handler = Usctdp_Mgmt_Admin::$post_handlers["registration"];
$submit_hook = $post_handler["submit_hook"];
$nonce_name = $post_handler["nonce_name"];
$nonce_action = $post_handler["nonce_action"];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="registration-container">
        <div id="registration-form-section">
            <form
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                method="post"
                id="register-form">
                <div id="context-selection-section"></div>
                <div id="notifications-section"></div>
                <div id="registration-details-section" class="hidden">
                    <h2> Registration Details</h2>
                    <div>
                        <strong>Current Enrollment:</strong>
                        <span id="class-current-size"></span>
                        out of
                        <span id="class-max-size"></span>
                    </div>
                    <div>
                        <strong>One-Day Price:</strong>
                        <span id="one-day-price"></span>
                    </div>
                    <div>
                        <strong>Two-Day Price:</strong>
                        <span id="two-day-price"></span>
                    </div>
                    <div>
                        <strong>Class Level:</strong>
                        <span id="class-level"></span>
                    </div>

                    <div id="registration-fields-section">
                        <div id="student-level-field" class="form-field">
                            <label for="student-level">Student Level</label>
                            <input type="number" id="student-level">
                        </div>
                        <div id="payment-amount-outstanding-field" class="form-field">
                            <label for="payment-amount-outstanding">Outstanding Balance</label>
                            <input type="number" id="payment-amount-outstanding">
                        </div>
                        <div id="payment-amount-paid-field" class="form-field">
                            <label for="payment-amount-paid">Amount Paid</label>
                            <input type="number" id="payment-amount-paid">
                        </div>
                        <div id="payment-method-field" class="form-field">
                            <label for="payment-method">Payment Method</label>
                            <select id="payment-method">
                                <option value="">Select...</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="credit-card">Credit Card</option>
                            </select>
                        </div>
                        <div id="check-number-field" class="form-field">
                            <label for="check-number">Check Number</label>
                            <input type="text" id="check-number">
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
</div>