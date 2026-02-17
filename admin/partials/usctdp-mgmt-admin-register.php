<?php
$page_title = get_admin_page_title();
$post_handler = Usctdp_Mgmt_Admin::$post_handlers["registration_checkout"];
$submit_hook = $post_handler["submit_hook"];
$nonce_name = $post_handler["nonce_name"];
$nonce_action = $post_handler["nonce_action"];

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="registration-container">
        <div id="registration-info">
            <div id="context-selection">
                <div id="context-selectors"></div>
                <div id="notifications-section"></div>
            </div>

            <div id="additional-details" class="hidden">
                <div id="clinic-info">
                    <div id="clinic-info-capacity" class="clinic-info-item">
                        <label>Capacity:</label>
                        <span class="clinic-info-value">
                            <span id="clinic-current-size"></span>
                            <span class="clinic-capacity-separator">out of</span>
                            <span id="clinic-max-size"></span>
                        </span>
                    </div>
                    <div id="clinic-info-one-day-price" class="clinic-info-item ">
                        <label>One Day Price:</label>
                        <span class="clinic-info-value neutral" id="clinic-one-day-price"></span>
                    </div>
                    <div id="clinic-info-two-day-price" class="clinic-info-item ">
                        <label>Two Day Price:</label>
                        <span class="clinic-info-value neutral" id="clinic-two-day-price"></span>
                    </div>
                </div>
                <div id="registration-fields">
                    <div id="student-level-field" class="registration-field">
                        <label for="student-level">Student Level</label>
                        <input name="student-level" id="student-level">
                    </div>
                    <div id="notes-section">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" rows="5"></textarea>
                    </div>
                </div>
                <div id="registration-submit-button-wrap">
                    <button id="add-registration" class="button button-primary">
                        Add Registration
                    </button>
                </div>
            </div>
        </div>

        <div id="registration-order-section" class="hidden">
            <div id="registration-order-info">
                <div id="registration-order-table-wrap">
                    <table id="registration-order-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Session</th>
                                <th>Activity</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div id="registration-order-summary">
                    <div id="registration-order-summary-total">
                        <span>Total:</span>
                        <span id="registration-order-total-value"></span>
                    </div>
                    <button id="registration-checkout" class="button button-primary">
                        Checkout
                    </button>
                </div>
            </div>

            <div id="registration-checkout-section" class="hidden">
                <h2> Checkout </h2>
                <input type="hidden" name="user_id" value="">
                <div id="payment-method-field" class="checkout-field">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" autocomplete="off">
                        <option value="">Select...</option>
                        <option value="card">Card</option>
                        <option value="check">Check</option>
                        <option value="cash">Cash</option>
                        <option value="charge-later">Charge Later</option>
                    </select>
                </div>

                <div id="check-fields" class="hidden payment-option">
                    <div id="check-number-field" class="checkout-field">
                        <label for="check_number">Check Number</label>
                        <input type="text" name="check_number" id="check_number">
                    </div>
                    <div id="check-received-date-field" class="checkout-field">
                        <label for="check_received_date">Date Received</label>
                        <input type="date" name="check_received_date" id="check_received_date">
                    </div>
                </div>

                <div id="submit-registration-button" class="hidden">
                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"
                        id="submit-registration-form">
                        <input type="hidden" name="action" value="<?php echo esc_attr($submit_hook); ?>">
                        <input type="hidden" id="submit_user_id" name="user_id" value="">
                        <input type="hidden" id="submit_family_id" name="family_id" value="">
                        <input type="hidden" id="submit_payment_url" name="payment_url" value="">
                        <input type="hidden" id="submit_order_url" name="order_url" value="">
                        <input type="hidden" id="submit_pay_now" name="pay_now" value="">
                        <?php wp_nonce_field($nonce_action, $nonce_name); ?>
                        <div id="registration-submit-button-wrap">
                            <?php submit_button(
                                'Submit Registration(s)',
                                'primary',
                                'registration_submit',
                                false,
                                [
                                    'id' => 'submit-registration-btn',
                                ]
                            ); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>