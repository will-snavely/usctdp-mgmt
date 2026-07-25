<?php

/**
 * Handles the "confirm your legacy family data" flow: a customer clicks the
 * emailed link (?usctdp_login=...&usctdp_key=...), reviews/edits the data
 * staged in usctdp_import_pending by `wp usctdp stage_legacy_families`, and
 * on submit that data is written into the real usctdp_family/usctdp_student
 * tables for the first time - nothing before this point is visible anywhere
 * else in the plugin.
 *
 * The emailed key is always a real WP password-reset key
 * (get_password_reset_key()/check_password_reset_key()) - clicking it proves
 * mail ownership regardless of whether the account is a brand new
 * placeholder or one that already existed, which matters because a lapsed
 * customer opting back in may not remember their old password either. The
 * only thing matched_existing_user changes is whether the form asks them to
 * set a new password: for a pre-existing account we don't want to overwrite
 * a password they didn't ask to change, so that account keeps whatever
 * password it already had.
 */
class Usctdp_Mgmt_Import_Confirm_Hooks
{
    const NONCE_ACTION = 'usctdp_confirm_import';

    private function get_pending_for_login($login)
    {
        $user = get_user_by('login', $login);
        if (!$user) {
            return null;
        }
        $query = new Usctdp_Mgmt_Import_Pending_Query([
            'user_id' => $user->ID,
            'number' => 1,
        ]);
        return $query->items[0] ?? null;
    }

    /**
     * Builds the data the import-confirm page template renders. Called on
     * every request to that page (both the initial emailed link and a POST
     * re-display after a validation error from handle_confirm_submission()).
     */
    public function get_confirm_context()
    {
        $login = isset($_REQUEST['usctdp_login']) ? sanitize_user(wp_unslash($_REQUEST['usctdp_login'])) : '';
        $key = isset($_REQUEST['usctdp_key']) ? sanitize_text_field(wp_unslash($_REQUEST['usctdp_key'])) : '';

        if (empty($login) || empty($key)) {
            return ['state' => 'invalid'];
        }

        $pending = $this->get_pending_for_login($login);
        if (!$pending) {
            return ['state' => 'invalid'];
        }
        if ($pending->confirmed_at) {
            return ['state' => 'already_confirmed'];
        }

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            return ['state' => 'invalid'];
        }

        return [
            'state' => 'form',
            'requires_password' => !$pending->matched_existing_user,
            'pending' => $pending,
            'login' => $login,
            'key' => $key,
        ];
    }

    /**
     * Processes the confirm form. Hooked on wp_loaded (same as
     * handle_add_student()): errors use wc_add_notice() and let the page
     * re-render; success redirects to My Account > Family (PRG) so a refresh
     * doesn't resubmit.
     */
    public function handle_confirm_submission()
    {
        if (empty($_POST['usctdp_confirm_import'])) {
            return;
        }

        $nonce = isset($_POST['usctdp_confirm_import_nonce']) ? wp_unslash($_POST['usctdp_confirm_import_nonce']) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wc_add_notice(__('Security check failed. Please try again.', 'usctdp-mgmt'), 'error');
            return;
        }

        $login = isset($_POST['usctdp_login']) ? sanitize_user(wp_unslash($_POST['usctdp_login'])) : '';
        $key = isset($_POST['usctdp_key']) ? sanitize_text_field(wp_unslash($_POST['usctdp_key'])) : '';

        $pending = $this->get_pending_for_login($login);
        if (!$pending || $pending->confirmed_at) {
            wc_add_notice(__('This import link is no longer valid.', 'usctdp-mgmt'), 'error');
            return;
        }

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            wc_add_notice(__('This import link is invalid or has expired. Please contact the office for a new one.', 'usctdp-mgmt'), 'error');
            return;
        }

        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $address = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
        $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
        $zip = isset($_POST['zip']) ? sanitize_text_field(wp_unslash($_POST['zip'])) : '';
        $new_password = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';

        if (empty($last_name)) {
            wc_add_notice(__('Please enter your last name.', 'usctdp-mgmt'), 'error');
        }
        if (empty($phone)) {
            wc_add_notice(__('Please enter a phone number.', 'usctdp-mgmt'), 'error');
        }
        if (!$pending->matched_existing_user && strlen($new_password) < 8) {
            wc_add_notice(__('Please choose a password with at least 8 characters.', 'usctdp-mgmt'), 'error');
        }

        $students = [];
        foreach ($_POST['students'] ?? [] as $student_input) {
            $first = sanitize_text_field(wp_unslash($student_input['first'] ?? ''));
            if (empty($first)) {
                continue;
            }
            $students[] = [
                'first' => $first,
                'last' => sanitize_text_field(wp_unslash($student_input['last'] ?? $last_name)),
                'birth_date' => sanitize_text_field(wp_unslash($student_input['birth_date'] ?? '')),
            ];
        }

        if (wc_notice_count('error') > 0) {
            return;
        }

        try {
            $last_four = substr(trim($phone), -4);
            $title = trim($last_name . ' ' . $last_four);

            $family_query = new Usctdp_Mgmt_Family_Query();
            $family_id = $family_query->add_item([
                'user_id' => $user->ID,
                'title' => $title,
                'search_term' => Usctdp_Mgmt_Model::append_token_suffix($title),
                'last' => $last_name,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'phone_numbers' => json_encode([$phone]),
                'emails' => json_encode($email ? [$email] : []),
                'notes' => $pending->notes,
            ]);
            if (!$family_id) {
                throw new Exception('Failed to create family record from staged import.');
            }

            $student_query = new Usctdp_Mgmt_Student_Query();
            foreach ($students as $student) {
                $student_query->create_student($student['first'], $student['last'], $family_id, $student['birth_date'], '');
            }

            if (!$pending->matched_existing_user) {
                wp_set_password($new_password, $user->ID);
            }
            wc_set_customer_auth_cookie($user->ID);

            $pending_query = new Usctdp_Mgmt_Import_Pending_Query();
            $pending_query->update_item($pending->id, ['confirmed_at' => current_time('mysql')]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('handle_confirm_submission', $e);
            wc_add_notice(__('Something went wrong finishing your import. Please contact the office.', 'usctdp-mgmt'), 'error');
            return;
        }

        wp_safe_redirect(wc_get_endpoint_url('family', '', wc_get_page_permalink('myaccount')));
        exit;
    }
}
