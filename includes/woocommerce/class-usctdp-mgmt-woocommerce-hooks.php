<?php

class Usctdp_Mgmt_Woocommerce_Hooks
{
    private $hold_minutes = 10;
    public function __construct()
    {
    }

    /**
     * Register the "family" and "registrations" endpoints (My Account > My Family,
     * My Account > My Registrations) so /my-account/family/ and
     * /my-account/registrations/ resolve. Rewrite rules are cached, so a fresh
     * install/deploy of this needs one rewrite flush (see
     * Usctdp_Mgmt_Activator::activate()) before the URLs work.
     */
    public function register_family_account_endpoint()
    {
        add_rewrite_endpoint('family', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('registrations', EP_ROOT | EP_PAGES);
    }

    public function family_endpoint_title($title, $endpoint)
    {
        if ($endpoint === 'family') {
            return __('My Family', 'usctdp-mgmt');
        }
        if ($endpoint === 'registrations') {
            return __('My Registrations', 'usctdp-mgmt');
        }
        return $title;
    }

    /**
     * Insert a new tab into the account nav right after $after_key.
     * customer-logout is expected by WooCommerce to stay last, so this
     * inserts rather than appends; if $after_key isn't found, the new tab
     * goes just before whatever's last (customer-logout) rather than first.
     */
    private function insert_menu_item($items, $after_key, $new_key, $label)
    {
        $keys = array_keys($items);
        $index = array_search($after_key, $keys, true);
        $insert_at = $index === false ? max(count($items) - 1, 0) : $index + 1;
        $before = array_slice($items, 0, $insert_at, true);
        $after = array_slice($items, $insert_at, null, true);
        return $before + [$new_key => $label] + $after;
    }

    /**
     * Insert the My Family and My Registrations tabs right after Dashboard.
     */
    public function add_account_menu_items($items)
    {
        $items = $this->insert_menu_item($items, 'dashboard', 'family', __('My Family', 'usctdp-mgmt'));
        return $this->insert_menu_item($items, 'family', 'registrations', __('My Registrations', 'usctdp-mgmt'));
    }

    private function get_current_user_family()
    {
        $family_query = new Usctdp_Mgmt_Family_Query([
            'user_id' => get_current_user_id(),
            'number' => 1,
        ]);
        return $family_query->items[0] ?? null;
    }

    /**
     * Renders My Account > Family: the account's students plus an add-student
     * form. wc_get_template() here resolves to the theme's
     * woocommerce/myaccount/family.blade.php via generoi/sage-woocommerce, the
     * same mechanism the login/lost-password templates already use.
     */
    public function render_family_endpoint()
    {
        $family = $this->get_current_user_family();
        $students = [];
        if ($family) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'family_id' => $family->id,
                'orderby' => 'first',
                'order' => 'ASC',
            ]);
            $students = $student_query->items;
        }
        wc_get_template('myaccount/family.php', [
            'family' => $family,
            'students' => $students,
        ]);
    }

    /**
     * Renders My Account > Registrations: every activity registration across
     * the account's students. get_registration_data() already does the
     * student/activity/session join for the admin registrations datatable,
     * so this just reuses it scoped to the current family.
     */
    public function render_registrations_endpoint()
    {
        $family = $this->get_current_user_family();
        $registrations = [];
        if ($family) {
            $registration_query = new Usctdp_Mgmt_Registration_Query();
            $result = $registration_query->get_registration_data(['family_id' => $family->id]);
            $registrations = $result['data'];
        }
        wc_get_template('myaccount/registrations.php', [
            'family' => $family,
            'registrations' => $registrations,
        ]);
    }

    /**
     * Handles the "Add a Student" form on My Account > Family. Mirrors
     * WC_Form_Handler's own registration handler: validate, wc_add_notice()
     * on failure, redirect-on-success (PRG) to avoid resubmission.
     */
    public function handle_add_student()
    {
        if (empty($_POST['usctdp_add_student']) || !is_user_logged_in()) {
            return;
        }
        $nonce = isset($_POST['usctdp_add_student_nonce']) ? wp_unslash($_POST['usctdp_add_student_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'usctdp_add_student')) {
            wc_add_notice(__('Security check failed. Please try again.', 'usctdp-mgmt'), 'error');
            return;
        }

        $family = $this->get_current_user_family();
        if (!$family) {
            wc_add_notice(__('No family account was found for your login. Please contact the office.', 'usctdp-mgmt'), 'error');
            return;
        }

        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $birth_month = isset($_POST['birth_month']) ? sanitize_text_field(wp_unslash($_POST['birth_month'])) : '';
        $birth_day = isset($_POST['birth_day']) ? sanitize_text_field(wp_unslash($_POST['birth_day'])) : '';
        $birth_year = isset($_POST['birth_year']) ? sanitize_text_field(wp_unslash($_POST['birth_year'])) : '';
        $birth_date = $birth_year && $birth_month && $birth_day
            ? "$birth_year-$birth_month-$birth_day"
            : '';

        if (empty($first_name)) {
            wc_add_notice(__('Please enter the student\'s first name.', 'usctdp-mgmt'), 'error');
        }
        if (empty($last_name)) {
            wc_add_notice(__('Please enter the student\'s last name.', 'usctdp-mgmt'), 'error');
        }
        $current_year = (int) date('Y');
        $parsed_birth_date = empty($birth_date) ? false : DateTime::createFromFormat('Y-m-d', $birth_date);
        $year_in_range = ctype_digit($birth_year) && $birth_year >= $current_year - 100 && $birth_year <= $current_year;
        if (
            empty($birth_date) || !$parsed_birth_date || $parsed_birth_date->format('Y-m-d') !== $birth_date
            || !$year_in_range
        ) {
            wc_add_notice(__('Please enter a valid birth date.', 'usctdp-mgmt'), 'error');
        }

        if (wc_notice_count('error') > 0) {
            return;
        }

        $student_query = new Usctdp_Mgmt_Student_Query();
        $result = $student_query->create_student($first_name, $last_name, $family->id, $birth_date, '');
        if (!$result) {
            wc_add_notice(__('Something went wrong adding the student. Please try again.', 'usctdp-mgmt'), 'error');
            return;
        }

        wc_add_notice(sprintf(
            /* translators: %s: student's full name */
            __('%s was added successfully.', 'usctdp-mgmt'),
            $first_name . ' ' . $last_name
        ));
        wp_safe_redirect(wc_get_endpoint_url('family', '', wc_get_page_permalink('myaccount')));
        exit;
    }

    /**
     * Require first/last name and phone on the My Account registration form.
     * Runs before wc_create_new_customer(), so adding errors here blocks
     * account creation the same way WooCommerce's own email/password checks
     * do.
     */
    public function validate_registration_fields($validation_error, $username, $password, $email)
    {
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

        if (empty($first_name)) {
            $validation_error->add('registration-error-missing-first-name', __('Please enter your first name.', 'usctdp-mgmt'));
        }
        if (empty($last_name)) {
            $validation_error->add('registration-error-missing-last-name', __('Please enter your last name.', 'usctdp-mgmt'));
        }
        if (empty($phone)) {
            $validation_error->add('registration-error-missing-phone', __('Please enter your phone number.', 'usctdp-mgmt'));
        }

        return $validation_error;
    }

    /**
     * Carry the first/last name fields from the registration form into the
     * new user's wp_insert_user() args, so the WP account itself (not just
     * the usctdp_family row) has a proper name.
     */
    public function add_name_to_customer_data($customer_data)
    {
        if (!empty($_POST['first_name'])) {
            $customer_data['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name']));
        }
        if (!empty($_POST['last_name'])) {
            $customer_data['last_name'] = sanitize_text_field(wp_unslash($_POST['last_name']));
        }
        return $customer_data;
    }

    /**
     * Create the usctdp_family row for a newly self-registered customer.
     * Every other family lookup in this plugin keys off usctdp_family.user_id
     * (see create_purchase_and_ledger_entries(), Usctdp_Mgmt_Public::get_user_family()),
     * so a customer account without one is unable to register students or check out.
     */
    /**
     * A legacy import can be staged (Usctdp_Stage_Legacy_Families) for a
     * family with no WP account yet - that's deliberate, so a self-
     * registration before the invite email ever gets read doesn't hit
     * WooCommerce's "email already registered" error. This is the other
     * half of that: if the email just registered matches an unconfirmed,
     * not-yet-matched staged row, pull its historical students/address/notes
     * into the new account instead of leaving that data to rot unclaimed in
     * usctdp_import_pending.
     */
    private function find_matching_pending_import($email)
    {
        if (empty($email)) {
            return null;
        }
        $pending_query = new Usctdp_Mgmt_Import_Pending_Query(['number' => false]);
        foreach ($pending_query->items as $pending) {
            if (!empty($pending->confirmed_at) || $pending->matched_existing_user) {
                continue;
            }
            if (in_array($email, $pending->emails, true)) {
                return $pending;
            }
        }
        return null;
    }

    public function create_family_on_registration($customer_id, $new_customer_data, $password_generated)
    {
        try {
            $last_name = $new_customer_data['last_name'] ?? '';
            $email = $new_customer_data['user_email'] ?? '';
            $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
            $last_four = substr(trim($phone), -4);

            $title = trim($last_name . ' ' . $last_four);

            // The registration form only collects name/email/phone, so
            // address/city/state/zip/notes (when a staged match exists) can
            // only come from the legacy data - nothing here overwrites what
            // the customer just typed.
            $pending = $this->find_matching_pending_import($email);

            $family_query = new Usctdp_Mgmt_Family_Query();
            $family_id = $family_query->add_item([
                'user_id' => $customer_id,
                'title' => $title,
                'search_term' => Usctdp_Mgmt_Model::append_token_suffix($title),
                'last' => $last_name,
                'address' => $pending ? $pending->address : '',
                'city' => $pending ? $pending->city : '',
                'state' => $pending ? $pending->state : '',
                'zip' => $pending ? $pending->zip : '',
                'phone_numbers' => json_encode($phone ? [$phone] : []),
                'emails' => json_encode($email ? [$email] : []),
                'notes' => $pending ? $pending->notes : '',
            ]);

            if ($pending && $family_id) {
                $student_query = new Usctdp_Mgmt_Student_Query();
                foreach ($pending->students as $student) {
                    $student_query->create_student($student->first, $student->last, $family_id, $student->birth_date, '');
                }
                (new Usctdp_Mgmt_Import_Pending_Query())->update_item($pending->id, ['confirmed_at' => current_time('mysql')]);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('create_family_on_registration', $e);
        }
    }

    /**
     * Prevents WooCommerce's default post-registration auto-login
     * (wc_set_customer_auth_cookie() in WC_Form_Handler::process_registration())
     * so a new account isn't usable until the customer proves they control
     * the email address. WooCommerce still sets a real (randomly-generated)
     * password on account creation, but nobody is ever told what it is -
     * the customer can only log in after setting their own password via
     * the link in WooCommerce's own "Customer new account" email
     * (WC_Email_Customer_New_Account::generate_set_password_url(), which
     * already calls get_password_reset_key() and points at the correctly
     * themed my-account/lost-password page on every send - nothing extra
     * needed here). WP core's own password-reset flow is the verification
     * gate, not a separate "verified" meta flag to maintain.
     *
     * There used to be a second method here (send_set_password_email_on_registration)
     * that also called retrieve_password() to send our own reset-key email.
     * Don't reintroduce it: WC_Email_Customer_New_Account::trigger() calls
     * get_password_reset_key() unconditionally on every registration
     * regardless of whether our own hook also runs, and get_password_reset_key()
     * overwrites the single stored key each time it's called - so the two
     * emails raced to set the account's reset key, and whichever ran second
     * silently invalidated the link in the other (usually surfacing as
     * "Your password reset link appears to be invalid" on WordPress core's
     * bare wp-login.php?action=rp screen, which is where retrieve_password()'s
     * own email - unlike WooCommerce's - actually points).
     *
     * DEV_SKIP_REGISTRATION_LOGOUT lets this be skipped locally (e.g. to
     * create test accounts without a Mailpit round trip). Gated on WP_ENV
     * being exactly "development" in addition to the env var itself, so
     * this can't do anything even if that var somehow ended up set in a
     * non-dev environment - same class of mistake as WP_DEBUG leaking into
     * a prod template earlier in this project's history.
     */
    public function prevent_auto_login_on_registration($should_auth, $customer_id)
    {
        if (WP_ENV === 'development' && getenv('DEV_SKIP_REGISTRATION_LOGOUT')) {
            return $should_auth;
        }

        wc_clear_notices();
        wc_add_notice(__('Your account was created successfully. Check your email to set your password before logging in.', 'usctdp-mgmt'));
        return false;
    }

    public function display_before_single_product()
    {
        if (!is_user_logged_in()) {
            // Registration requires an account (to attach a student/family), so
            // swap out WooCommerce's own variation add-to-cart button for a
            // login prompt in the same slot. Must happen before
            // woocommerce_single_variation fires later in this same request.
            remove_action('woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20);
            add_action('woocommerce_single_variation', [$this, 'render_login_to_purchase_button'], 20);
        }
        ?>
        <dialog id="new-student-modal">
            <form id="new-student-form" method="dialog">
                <h2>Add New Student</h2>
                <div class="student_field">
                    <label for="modal_first_name">First Name</label>
                    <input type="text" id="modal_first_name" name="first_name" required>
                </div>

                <div class="student_field">
                    <label for="modal_last_name">Last Name</label>
                    <input type="text" id="modal_last_name" name="last_name" required>
                </div>

                <div class="student_field">
                    <label for="modal_birth_month">Birthday</label>
                    <div class="usctdp-birthdate-group">
                        <select id="modal_birth_month" required>
                            <option value="" disabled selected>Month</option>
                        </select>
                        <select id="modal_birth_day" required>
                            <option value="" disabled selected>Day</option>
                        </select>
                        <input type="number" id="modal_birth_year" inputmode="numeric" placeholder="Year"
                            min="<?php echo esc_attr(gmdate('Y') - 100); ?>" max="<?php echo esc_attr(gmdate('Y')); ?>"
                            required>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="button" id="close-modal">Cancel</button>
                    <button type="submit" class="button" id="save-student-modal">Save Student</button>
                </div>
            </form>
        </dialog>
        <?php
    }

    /**
     * Guest replacement for woocommerce_single_variation_add_to_cart_button().
     * Rendered in the same wrapper/slot so existing JS that shows/hides
     * .variations_button on variation selection still works.
     */
    public function render_login_to_purchase_button()
    {
        ?>
        <div class="woocommerce-variation-add-to-cart variations_button usctdp-login-to-purchase">
            <p>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Please log in</a> to register.
            </p>
        </div>
        <?php
    }

    /**
     * Silently suppress the "Please choose product options" error when no variation_id
     * is present. WooCommerce's add_to_cart_handler_variable() generates this notice
     * after the woocommerce_add_to_cart_validation filter, so returning false here
     * exits the handler before wc_add_notice() is ever called.
     *
     * This catches all callers (AJAX, PayPal, standard POST) that fire the variable
     * product handler without a valid variation — including PayPal's change-cart and
     * simulate-cart endpoints that serialize the product form but omit variation data.
     * JS already handles the client-side "select options" UX, so no server notice is needed.
     */
    public function suppress_missing_variation_notice($passed, $product_id, $quantity, $variation_id = 0)
    {
        if (!empty($variation_id)) {
            return $passed;
        }
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable')) {
            return false;
        }
        return $passed;
    }

    /**
     * Remove "Please choose product options" notices from the WC session.
     *
     * These accumulate when any AJAX endpoint (PayPal smart buttons, fragment refresh,
     * etc.) triggers add_to_cart_action without a variation_id. wc_print_notices() never
     * runs in those AJAX contexts, so the notices stay in the session and surface on
     * completely unrelated pages — cart, checkout, shop, wherever WooCommerce next
     * outputs notices.
     *
     * Registered on two hooks:
     *   shutdown  (priority 15, before WC saves the session at priority 20) — scrubs any
     *             notices generated during an AJAX request before they hit the DB.
     *   wp        (priority 100, before template rendering / notice output)  — scrubs any
     *             notices that were already persisted from a previous AJAX request so
     *             they never appear on page loads.
     */
    public function clear_stale_variation_notices()
    {
        if (!WC()->session) {
            return;
        }
        $notices = WC()->session->get('wc_notices', []);
        if (empty($notices['error'])) {
            return;
        }
        $target = 'Please choose product options';
        $filtered = array_values(array_filter($notices['error'], function ($notice) use ($target) {
            $text = is_array($notice) ? ($notice['notice'] ?? '') : $notice;
            return strpos(wp_strip_all_tags($text), $target) === false;
        }));
        if (count($filtered) === count($notices['error'])) {
            return;
        }
        $notices['error'] = $filtered;
        if (empty($notices['error'])) {
            unset($notices['error']);
        }
        WC()->session->set('wc_notices', $notices);
    }

    public function display_before_variations_form()
    {
    }

    /**
     * Hide the price range WooCommerce shows for variable products (clinics,
     * tournaments) on the single product page. That pricing is already broken
     * out per session in the session-info cards rendered above the variations
     * table, so the range here is redundant. Scoped to variable products only
     * so simple products (e.g. merch) keep their normal price display.
     *
     * Hooked at priority 1 on the same action woocommerce_template_single_price
     * uses (priority 10), so the removal is registered before that callback
     * would otherwise run.
     */
    public function hide_variable_product_price_range()
    {
        global $product;
        if ($product && $product->is_type('variable')) {
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        }
    }

    /**
     * Treat variable products (clinics, tournaments) as "sold individually"
     * so only one registration can be added to the cart at a time. This is
     * what WooCommerce itself uses to decide the quantity input's min/max;
     * when they're both 1, its own template renders a hidden qty=1 field
     * instead of the spinner, so the counter disappears as a side effect.
     * Simple products (e.g. merch) are unaffected.
     */
    public function force_sold_individually_for_variable_products($sold_individually, $product)
    {
        if ($product && $product->is_type('variable')) {
            return true;
        }
        return $sold_individually;
    }

    /**
     * Drop the "Additional information" tab from the single product page.
     *
     * Unsetting it here (rather than just removing the
     * woocommerce_product_additional_information content action) is what
     * actually removes the tab from the tab bar - WooCommerce still adds an
     * (empty) tab whenever $product->has_attributes() is true, which it is
     * for our variable clinic/tournament products (Session, Days Per Week
     * variation attributes).
     */
    public function remove_additional_information_tab($tabs)
    {
        unset($tabs['additional_information']);
        return $tabs;
    }

    public function display_before_variations_table()
    {
        global $product;
        if (!$product) {
            return;
        }

        $session_map = get_post_meta($product->get_id(), '_session_post_ids', true);
        if (empty($session_map) || !is_array($session_map)) {
            return;
        }

        $session_query = new Usctdp_Mgmt_Session_Query([
            'id__in' => array_values($session_map),
        ]);
        $sessions_by_id = [];
        foreach ($session_query->items as $session) {
            $sessions_by_id[$session->id] = $session;
        }

        $product_query = new Usctdp_Mgmt_Product_Query([
            'woocommerce_id' => $product->get_id(),
            'number' => 1,
        ]);
        $usctdp_product = $product_query->items[0] ?? null;
        ?>
        <?php $is_single_session = count($session_map) === 1; ?>
        <div id="usctdp-session-info-list">
            <?php foreach ($session_map as $session_name => $session_id):
                $session = $sessions_by_id[$session_id] ?? null;
                if (!$session) {
                    continue;
                }
                $meta = json_decode($session->meta, true) ?: [];
                $note = $meta['note'] ?? '';
                $price_lines = $usctdp_product
                    ? $this->get_session_price_lines($usctdp_product, $session)
                    : [];
                ?>
                <div class="usctdp-session-info" data-session="<?php echo esc_attr($session_name); ?>">
                    <?php if (!$is_single_session): ?>
                        <span class="usctdp-session-info-badge">Selected</span>
                    <?php endif; ?>
                    <p class="usctdp-session-info-title"><?php echo esc_html($session_name); ?></p>
                    <p class="usctdp-session-info-dates">
                        <?php echo esc_html($session->start_date->format('M j, Y')); ?>
                        &ndash;
                        <?php echo esc_html($session->end_date->format('M j, Y')); ?>
                    </p>
                    <?php if (!empty($note)): ?>
                        <p class="usctdp-session-info-note"><?php echo esc_html($note); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($price_lines)): ?>
                        <p class="usctdp-session-info-price">
                            <?php foreach ($price_lines as $line): ?>
                                <span class="usctdp-session-info-price-line"><?php echo $line; ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Look up the (session, product) pricing row and format it for display
     * on the session card. Clinics price by days-per-week ('One'/'Two');
     * tournaments have a flat enrollment fee ('base'). Returns an array of
     * pre-formatted HTML strings, one per price line, or [] if there's
     * nothing to show (no pricing row, or an unpriced product type).
     */
    private function get_session_price_lines($usctdp_product, $session)
    {
        $pricing_query = new Usctdp_Mgmt_Pricing_Query([
            'session_id' => $session->id,
            'product_id' => $usctdp_product->id,
            'number' => 1,
        ]);
        if (empty($pricing_query->items)) {
            return [];
        }
        $pricing = $pricing_query->items[0]->pricing;

        if ($usctdp_product->type === 'tournament') {
            if (empty($pricing['base'])) {
                return [];
            }
            return [wc_price($pricing['base']) . ' to enroll'];
        }

        $lines = [];
        if (!empty($pricing['One'])) {
            $lines[] = wc_price($pricing['One']) . ' &middot; 1 day/week';
        }
        if (!empty($pricing['Two'])) {
            $lines[] = wc_price($pricing['Two']) . ' &middot; 2 days/week';
        }
        return $lines;
    }

    public function display_after_variations_table()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $current_user_id = get_current_user_id();
        if (current_user_can('register_student')) {
            $this->render_admin_shop_options();
        } else {
            $family_query = new Usctdp_Mgmt_Family_Query([
                "user_id" => $current_user_id,
                "number" => 1
            ]);
            if (!empty($family_query->items)) {
                $this->render_user_shop_options($family_query->items[0]);
            }
        }
    }

    private function render_admin_shop_options()
    {
    }

    private function render_user_shop_options($family)
    {
        ?>
        <div id="usctdp-woocommerce-extra" class="force-hidden">
            <div id="usctdp-student-selector">
                <div id="select_name_or_new">
                    <div id="student_label">
                        <label for="student_select">Student</label>
                    </div>
                    <div id="student_select_wrapper">
                        <select name="student_id" id="student_select" required></select>
                    </div>
                    <div id="new_student_button_wrapper">
                        <button id="new-student-button" class="button">Add New...</button>
                    </div>
                </div>
            </div>
            <div id="usctdp-day-selectors"></div>
        </div>
        <?php
    }

    public function display_before_cart_button()
    {
    }

    public function display_after_cart_button()
    {
    }

    public function display_after_variations_form()
    {
    }
    private function int_to_day($day_of_week)
    {
        $days = [
            1 => "Monday",
            2 => "Tuesday",
            3 => "Wednesday",
            4 => "Thursday",
            5 => "Friday",
            6 => "Saturday",
            7 => "Sunday",
        ];
        return $days[$day_of_week->value];
    }

    private function get_clinic_display($activity_id)
    {
        $clinic_query = new Usctdp_Mgmt_Clinic_Query([
            'id' => $activity_id,
            'number' => 1,
        ]);
        $clinic = $clinic_query->items[0];
        return $this->int_to_day($clinic->day_of_week) . " at " . $clinic->start_time->format('g:i A');
    }

    private function get_tournament_display($activity_id)
    {
        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'id' => $activity_id,
            'number' => 1,
        ]);
        $activity = $activity_query->items[0] ?? null;
        return $activity ? $activity->title : '';
    }

    /**
     * Resolve the single usctdp_activity for a tournament variation. Unlike
     * clinics (day_of_week_1/2 posted from a picker built from several
     * candidate activities), a tournament session has exactly one activity,
     * so it's looked up server-side from the posted product/variation
     * instead of relying on a client-submitted activity id. Returns null for
     * non-tournament products (so callers can invoke it unconditionally) or
     * if the variation's session can't be resolved to an activity.
     */
    private function resolve_tournament_activity($product_id, $variation_id)
    {
        if (!$variation_id) {
            return null;
        }
        $product_query = new Usctdp_Mgmt_Product_Query([
            'woocommerce_id' => $product_id,
            'number' => 1,
        ]);
        $usctdp_product = $product_query->items[0] ?? null;
        if (!$usctdp_product || $usctdp_product->type !== 'tournament') {
            return null;
        }

        $session_name = get_post_meta($variation_id, 'attribute_session', true);
        $session_map = get_post_meta($product_id, '_session_post_ids', true);
        if (empty($session_name) || empty($session_map[$session_name])) {
            return null;
        }

        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'session_id' => $session_map[$session_name],
            'product_id' => $usctdp_product->id,
            'number' => 1,
        ]);
        return $activity_query->items[0] ?? null;
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity)
    {
        $activities = [];
        if (isset($_POST['student_id'])) {
            $cart_item_data['student_id'] = intval($_POST['student_id']);
        }
        if (isset($_POST['day_of_week_1'])) {
            $id = intval($_POST['day_of_week_1']);
            $activities[] = $id;
            $cart_item_data['day_of_week_1'] = $id;
        }
        if (isset($_POST['day_of_week_2'])) {
            $id = intval($_POST['day_of_week_2']);
            $activities[] = $id;
            $cart_item_data['day_of_week_2'] = $id;
        }
        if (empty($activities)) {
            $tournament_activity = $this->resolve_tournament_activity($product_id, $variation_id);
            if ($tournament_activity) {
                $activities[] = $tournament_activity->id;
                $cart_item_data['tournament_activity_id'] = $tournament_activity->id;
            }
        }
        $cart_item_data['activities'] = $activities;
        $cart_item_data['tracking_id'] = uniqid("usctdp_", true);
        return $cart_item_data;
    }

    public function get_item_data($item_data, $cart_item)
    {
        if (isset($cart_item['student_id'])) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $cart_item['student_id'],
                'number' => 1,
            ]);
            $student = $student_query->items[0];
            $item_data[] = array(
                'key' => 'Student Name',
                'value' => $student->id,
                'display' => $student->title,
            );
        }
        if (isset($cart_item['day_of_week_1'])) {
            $clinic_id = intval($cart_item['day_of_week_1']);
            $item_data[] = array(
                'key' => 'Day 1',
                'value' => $clinic_id,
                'display' => $this->get_clinic_display($clinic_id)
            );
        }
        if (isset($cart_item['day_of_week_2'])) {
            $clinic_id = intval($cart_item['day_of_week_2']);
            $item_data[] = array(
                'key' => 'Day 2',
                'value' => $clinic_id,
                'display' => $this->get_clinic_display($clinic_id)
            );
        }
        if (isset($cart_item['tournament_activity_id'])) {
            $activity_id = intval($cart_item['tournament_activity_id']);
            $item_data[] = array(
                'key' => 'Activity',
                'value' => $activity_id,
                'display' => $this->get_tournament_display($activity_id)
            );
        }
        return $item_data;
    }

    private function parse_cart_data($errors)
    {
        $registrations = [];
        $all_activities = [];
        $all_students = [];
        $cart_data_valid = true;

        foreach (WC()->cart->get_cart() as $item) {
            $tracking_id = $item['tracking_id'];
            $student_id = $item['student_id'];
            $item_activities = $item['activities'];

            if (!isset($all_students[$student_id])) {
                $student_query = new Usctdp_Mgmt_Student_Query([
                    'id' => $student_id,
                    'number' => 1,
                ]);
                if (empty($student_query->items)) {
                    $errors->add('invalid_student', "$student_id is not a valid student id.");
                    $cart_data_valid = false;
                    continue;
                }
                $all_students[$student_id] = $student_query->items[0];
            }

            foreach ($item_activities as $activity_id) {
                if (empty($activity_id)) {
                    continue;
                }
                if (!isset($all_activities[$activity_id])) {
                    $activity_query = new Usctdp_Mgmt_Activity_Query([
                        'id' => $activity_id,
                        'number' => 1,
                    ]);
                    if (empty($activity_query->items)) {
                        $errors->add('invalid_class', "$activity_id is not a valid activity id.");
                        $cart_data_valid = false;
                        continue;
                    }
                    $all_activities[$activity_id] = $activity_query->items[0];
                }

                $registrations[] = [
                    "student_id" => $student_id,
                    "activity_id" => $activity_id,
                    "tracking_id" => $tracking_id,
                    "cart_item" => $item
                ];
            }
        }

        return [
            "result" => $cart_data_valid,
            "registrations" => $registrations,
            "students" => $all_students,
            "activities" => $all_activities
        ];
    }

    public function after_checkout_validation($data, $errors)
    {
        global $wpdb;
        $registration_table = $wpdb->prefix . 'usctdp_registration';
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $count_query_template = "
            SELECT COUNT(*) FROM $registration_table 
            WHERE activity_id = %d
            AND (status = %s
            OR (status = %d AND created_at > NOW() - INTERVAL %d MINUTE))";
        $activity_lock_template = "SELECT * FROM $activity_table WHERE id=%d FOR UPDATE";
        $txn_started = false;
        $txn_commited = false;

        try {
            $parsed_cart = $this->parse_cart_data($errors);
            if (!$parsed_cart["result"]) {
                return;
            }

            $registrations = $parsed_cart["registrations"];
            $activities = $parsed_cart["activities"];
            $students = $parsed_cart["students"];

            // We need to lock the activities in order to prevent race conditions
            ksort($activities);

            $wpdb->query('START TRANSACTION');
            $txn_started = true;

            foreach ($activities as $activity_id => $activity) {
                $activity_lock = $wpdb->prepare($activity_lock_template, $activity_id);
                $wpdb->get_row($activity_lock);
            }

            foreach ($registrations as $reg) {
                $cart_item = $reg["cart_item"];
                $student = $students[$reg["student_id"]];
                $activity = $activities[$reg["activity_id"]];
                $already_reserved = false;

                $reg_query = new Usctdp_Mgmt_Registration_Query([
                    'student_id' => $reg["student_id"],
                    'activity_id' => $reg["activity_id"],
                    'number' => 1
                ]);
                if (!empty($reg_query->items)) {
                    $existing_reg = $reg_query->items[0];
                    $active_statuses = [
                        "active",
                        "pending",
                    ];
                    if (in_array($existing_reg->status, $active_statuses)) {
                        if ($existing_reg->tracking_id !== $reg["tracking_id"]) {
                            $name = $student->title;
                            $class = $activity->title;
                            $msg = "$name is already enrolled in '$class'.";
                            throw new Usctdp_Checkout_Exception($msg, 'already_enrolled');
                        } else {
                            $already_reserved = true;
                        }
                    }
                }

                if ($already_reserved) {
                    continue;
                }

                $max_capacity = $activity->capacity;
                $count_query = $wpdb->prepare(
                    $count_query_template,
                    $reg["activity_id"],
                    "active",
                    "pending",
                    $this->hold_minutes
                );
                $current_count = $wpdb->get_var($count_query);
                if ($current_count >= $max_capacity) {
                    $msg = $activity->title . " is currently full.";
                    throw new Usctdp_Checkout_Exception($msg, 'out_of_stock');
                }

                $current_time = current_time('mysql');
                $reg_query = new Usctdp_Mgmt_Registration_Query();
                $result = $reg_query->add_item([
                    'activity_id' => $reg["activity_id"],
                    'student_id' => $reg["student_id"],
                    'tracking_id' => $reg["tracking_id"],
                    'student_level' => $student->level,
                    'credit' => 0,
                    'debit' => 0,
                    'status' => 'pending',
                    'created_at' => $current_time,
                    'created_by' => get_current_user_id(),
                    'last_modified_at' => $current_time,
                    'last_modified_by' => get_current_user_id(),
                    'notes' => '',
                ]);
                if (!$result) {
                    $msg = "An error occurred while creating the reservation ";
                    $msg .= " for " . $activity->title . ".";
                    $msg .= " Try again or contact the office.";
                    throw new Usctdp_Checkout_Exception($msg, 'reservation_failed');
                }
            }
            $wpdb->query('COMMIT');
            $txn_commited = true;
        } catch (Usctdp_Checkout_Exception $ce) {
            $errors->add($ce->getSlug(), $ce->getMessage());
            Usctdp_Mgmt::logger()->log_error(
                'USCTDP: Error validating and reserving capacity: ' . $ce->getMessage()
            );
        } catch (Throwable $e) {
            $msg = 'A system error occurred while checking out. Please contact the office.';
            $errors->add('system-error', $msg);
            Usctdp_Mgmt::logger()->log_exception('Checkout error', $e);
        } finally {
            if ($txn_started && !$txn_commited) {
                $wpdb->query('ROLLBACK');
            }
        }
    }

    /**
     * WooCommerce itself already puts the session name on the order line
     * item - WC_Order_Item_Product::set_variation() (called from
     * WC_Checkout::create_order_line_items() before this hook fires) adds
     * the variation's attribute_session value as meta key 'session', which
     * order templates display under the "Session" label (from the product's
     * attribute name). Session titles/names repeat across years (e.g. a
     * "Winter Junior Open" every year), so this appends the session's start
     * year to that value for display, e.g. "Winter Junior Open 2026".
     */
    private function append_session_year($item, $values)
    {
        $session_name = $item->get_meta('session');
        if (empty($session_name) || empty($values['activities'])) {
            return;
        }

        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'id' => reset($values['activities']),
            'number' => 1,
        ]);
        $activity = $activity_query->items[0] ?? null;
        if (!$activity) {
            return;
        }

        $session_query = new Usctdp_Mgmt_Session_Query([
            'id' => $activity->session_id,
            'number' => 1,
        ]);
        $session = $session_query->items[0] ?? null;
        if (!$session) {
            return;
        }

        $item->update_meta_data('session', $session_name . ' ' . $session->start_date->format('Y'));
    }

    public function checkout_create_order_line_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['student_id'])) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $values['student_id'],
                'number' => 1,
            ]);
            $student = $student_query->items[0];
            $item->add_meta_data('_student_id', $values['student_id']);
            $item->add_meta_data('Student Name', $student->title);
        }
        if (isset($values['day_of_week_1'])) {
            $item->add_meta_data('_day_1_id', $values['day_of_week_1']);
            $item->add_meta_data('Day 1', $this->get_clinic_display($values['day_of_week_1']));
        }
        if (isset($values['day_of_week_2'])) {
            $item->add_meta_data('_day_2_id', $values['day_of_week_2']);
            $item->add_meta_data('Day 2', $this->get_clinic_display($values['day_of_week_2']));
        }
        if (isset($values['tournament_activity_id'])) {
            $item->add_meta_data('_tournament_activity_id', $values['tournament_activity_id']);
            $item->add_meta_data('Activity', $this->get_tournament_display($values['tournament_activity_id']));
        }
        $this->append_session_year($item, $values);
        $item->add_meta_data('_activities', $values['activities']);
        $item->add_meta_data('_tracking_id', $values['tracking_id']);
    }

    public function checkout_order_processed($order_id, $data, $order)
    {
        foreach ($order->get_items() as $item_id => $item) {
            $student_id = $item->get_meta('_student_id');
            $tracking_id = $item->get_meta('_tracking_id');
            $activities = $item->get_meta('_activities');
            foreach ($activities as $activity_id) {
                $query = new Usctdp_Mgmt_Registration_Query([
                    'student_id' => $student_id,
                    'activity_id' => $activity_id,
                    'tracking_id' => $tracking_id,
                    'status' => 'pending',
                ]);
                if (!empty($query->items)) {
                    $query->update_item($query->items[0]->id, [
                        'order_id' => $order_id,
                    ]);
                } else {
                    Usctdp_Mgmt::logger()->log_error(
                        "USCTDP: No pending registration found for order $order_id, " .
                        "student $student_id, activity $activity_id, tracking $tracking_id"
                    );
                }
            }
        }
    }

    public function confirm_registration($order_id)
    {
        $query = new Usctdp_Mgmt_Registration_Query([
            'order_id' => $order_id,
            'status' => 'pending'
        ]);
        foreach ($query->items as $item) {
            $query->update_item($item->id, [
                "status" => "active",
                "last_modified_at" => current_time('mysql'),
                "last_modified_by" => get_current_user_id(),
            ]);
        }
    }

    public function create_purchase_and_ledger_entries($order_id)
    {
        global $wpdb;
        $txn_started = false;
        $txn_committed = false;

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                Usctdp_Mgmt::logger()->log_error("USCTDP: Order $order_id not found for purchase/ledger creation.");
                return;
            }

            $user_id = $order->get_customer_id();
            $family_query = new Usctdp_Mgmt_Family_Query(['user_id' => $user_id, 'number' => 1]);
            if (empty($family_query->items)) {
                Usctdp_Mgmt::logger()->log_error("USCTDP: No family found for user $user_id on order $order_id.");
                return;
            }
            $family_id = $family_query->items[0]->id;
            $payment_method = $order->get_payment_method();
            $reference_id = $order->get_transaction_id() ?: (string) $order_id;
            $created_at = current_time('mysql');
            $created_by = get_current_user_id();

            $wpdb->query('START TRANSACTION');
            $txn_started = true;

            foreach ($order->get_items() as $item) {
                $student_id = $item->get_meta('_student_id');
                if (!$student_id) {
                    continue;
                }

                $tracking_id = $item->get_meta('_tracking_id');
                $item_total = floatval($item->get_total());

                // '_activities' already carries every activity id for the item
                // (one for a tournament, one or two for a clinic's day picks),
                // in the same order they were added in add_cart_item_data().
                $activity_ids = array_map('intval', (array) $item->get_meta('_activities'));
                if (empty($activity_ids)) {
                    continue;
                }

                $wc_product_id = $item->get_product_id();
                $product_query = new Usctdp_Mgmt_Product_Query(['woocommerce_id' => $wc_product_id, 'number' => 1]);
                if (empty($product_query->items)) {
                    throw new Exception("USCTDP Product not found for WC product $wc_product_id on order $order_id.");
                }
                $product = $product_query->items[0];

                $activities = [];
                foreach ($activity_ids as $activity_id) {
                    $aq = new Usctdp_Mgmt_Activity_Query(['id' => $activity_id, 'number' => 1]);
                    if (empty($aq->items)) {
                        throw new Exception("Activity $activity_id not found for order $order_id.");
                    }
                    $activities[$activity_id] = $aq->items[0];
                }

                if (count($activity_ids) === 1) {
                    $activity_prices = [$activity_ids[0] => $item_total];
                } else {
                    $pricing = Usctdp_Mgmt_Model::get_activity_pricing($activities[$activity_ids[0]]);
                    if (!$pricing) {
                        throw new Exception("Pricing not found for activity {$activity_ids[0]} on order $order_id.");
                    }
                    $pricing_data = $pricing->pricing;
                    $day1_price = floatval($pricing_data['One']);
                    $activity_prices = [
                        $activity_ids[0] => $day1_price,
                        $activity_ids[1] => $item_total - $day1_price,
                    ];
                }

                $ledger_query = new Usctdp_Mgmt_Ledger_Query();
                foreach ($activity_ids as $activity_id) {
                    $reg_query = new Usctdp_Mgmt_Registration_Query([
                        'order_id' => $order_id,
                        'student_id' => $student_id,
                        'activity_id' => $activity_id,
                        'number' => 1,
                    ]);
                    if (empty($reg_query->items)) {
                        throw new Exception("Registration not found for order $order_id, student $student_id, activity $activity_id.");
                    }
                    $registration = $reg_query->items[0];
                    if (!empty($registration->purchase_id)) {
                        continue;
                    }

                    $purchase_query = new Usctdp_Mgmt_Purchase_Query();
                    $purchase_id = $purchase_query->add_item([
                        'product_id' => $product->id,
                        'family_id' => $family_id,
                        'student_id' => $student_id,
                        'tracking_id' => $tracking_id,
                        'type' => 'registration',
                        'created_at' => $created_at,
                        'created_by' => $created_by,
                    ]);
                    if (!$purchase_id) {
                        throw new Exception("Failed to create purchase for order $order_id, activity $activity_id.");
                    }

                    $reg_query->update_item($registration->id, ['purchase_id' => $purchase_id]);
                    $price = $activity_prices[$activity_id];
                    $activity_title = $activities[$activity_id]->title;

                    $ledger_query->add_item([
                        'purchase_id' => $purchase_id,
                        'family_id' => $family_id,
                        'order_id' => $order_id,
                        'event_id' => 'wc_order' . $order_id,
                        'event' => 'WooCommerce Order ' . $order_id,
                        'account' => 'registration_fees',
                        'entry_type' => 'charge',
                        'description' => 'Order placed in online store.',
                        'payment_method' => $payment_method,
                        'reference_id' => $reference_id,
                        'debit' => $price,
                        'credit' => 0,
                        'created_at' => $created_at,
                        'created_by' => $created_by,
                    ]);

                    $ledger_query->add_item([
                        'purchase_id' => $purchase_id,
                        'family_id' => $family_id,
                        'order_id' => $order_id,
                        'event_id' => 'wc_order' . $order_id,
                        'event' => 'WooCommerce Order ' . $order_id,
                        'account' => 'registration_fees',
                        'entry_type' => 'payment',
                        'description' => 'Order paid in online store.',
                        'payment_method' => $payment_method,
                        'reference_id' => $reference_id,
                        'debit' => 0,
                        'credit' => $price,
                        'created_at' => $created_at,
                        'created_by' => $created_by,
                    ]);
                }
            }

            $wpdb->query('COMMIT');
            $txn_committed = true;
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('USCTDP: create_purchase_and_ledger_entries', $e);
        } finally {
            if ($txn_started && !$txn_committed) {
                $wpdb->query('ROLLBACK');
            }
        }
    }

    /**
     * Records the payment side of the ledger for purchases created via the admin
     * invoicing flow (Usctdp_Mgmt_Woocommerce::create_woocommerce_order()).
     *
     * That flow books the charge immediately but, for card payments, defers the
     * actual payment entry until the customer completes payment through the
     * WooCommerce order-pay link - the card may fail or the customer may not pay
     * right away. This fills in that payment entry once WooCommerce confirms the
     * order is paid. It's a no-op for order items with no '_purchase_id' meta
     * (self-checkout orders, handled by create_purchase_and_ledger_entries) and
     * for purchases that already have a payment entry recorded (cash/check orders,
     * which record their payment entry client-side at invoice creation).
     */
    public function record_deferred_payment($order_id)
    {
        global $wpdb;
        $txn_started = false;
        $txn_committed = false;

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                Usctdp_Mgmt::logger()->log_error("USCTDP: Order $order_id not found for deferred payment recording.");
                return;
            }

            $payment_method = $order->get_payment_method();
            $reference_id = $order->get_transaction_id() ?: (string) $order_id;
            $created_at = current_time('mysql');
            $created_by = get_current_user_id();
            $event_id = 'wc_order' . $order_id;
            $event = 'WooCommerce Order ' . $order_id;

            $wpdb->query('START TRANSACTION');
            $txn_started = true;

            foreach ($order->get_items() as $item) {
                $purchase_id = intval($item->get_meta('_purchase_id'));
                if (!$purchase_id) {
                    continue;
                }

                $existing_payment = new Usctdp_Mgmt_Ledger_Query([
                    'purchase_id' => $purchase_id,
                    'entry_type' => 'payment',
                    'number' => 1,
                ]);
                if (!empty($existing_payment->items)) {
                    continue;
                }
                $purchase_query = new Usctdp_Mgmt_Purchase_Query(['id' => $purchase_id, 'number' => 1]);
                if (empty($purchase_query->items)) {
                    throw new Exception("USCTDP Purchase $purchase_id not found for order $order_id.");
                }
                $purchase = $purchase_query->items[0];
                $price = floatval($item->get_total());

                $ledger_query = new Usctdp_Mgmt_Ledger_Query();
                $ledger_query->add_item([
                    'purchase_id' => $purchase_id,
                    'family_id' => $purchase->family_id,
                    'order_id' => $order_id,
                    'event_id' => $event_id,
                    'event' => $event,
                    'account' => 'payment_' . $payment_method,
                    'entry_type' => 'payment',
                    'description' => 'Payment received in online store.',
                    'payment_method' => $payment_method,
                    'reference_id' => $reference_id,
                    'debit' => $price,
                    'credit' => 0,
                    'created_at' => $created_at,
                    'created_by' => $created_by,
                ]);

                $ledger_query->add_item([
                    'purchase_id' => $purchase_id,
                    'family_id' => $purchase->family_id,
                    'order_id' => $order_id,
                    'event_id' => $event_id,
                    'event' => $event,
                    'account' => $purchase->type . '_fees',
                    'entry_type' => 'payment',
                    'description' => 'Payment received in online store.',
                    'payment_method' => $payment_method,
                    'reference_id' => $reference_id,
                    'debit' => 0,
                    'credit' => $price,
                    'created_at' => $created_at,
                    'created_by' => $created_by,
                ]);
            }

            $wpdb->query('COMMIT');
            $txn_committed = true;
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('USCTDP: record_deferred_payment', $e);
        } finally {
            if ($txn_started && !$txn_committed) {
                $wpdb->query('ROLLBACK');
            }
        }
    }
}
