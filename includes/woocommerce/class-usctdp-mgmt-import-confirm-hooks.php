<?php

/**
 * Handles the "confirm your legacy family data" flow: a customer clicks the
 * emailed link (?usctdp_import=<pending id>&usctdp_key=...), reviews/edits
 * the data staged in usctdp_import_pending by
 * `wp usctdp stage_legacy_families`, and on submit that data is written into
 * the real usctdp_family/usctdp_student tables for the first time - nothing
 * before this point is visible anywhere else in the plugin.
 *
 * Two kinds of key, matching the two kinds of staged row:
 * - matched_existing_user rows already have a real WP account (found by
 *   email at staging time), so this reuses WordPress's own password-reset
 *   key (get_password_reset_key()/check_password_reset_key()). Only the
 *   review form is shown - their existing password is left untouched.
 * - Everyone else has no account at all yet - staging deliberately doesn't
 *   create one, so a legacy customer who registers normally before ever
 *   seeing this email doesn't hit a false "email already registered" error
 *   (see create_family_on_registration() for that reconciliation path).
 *   For these, the emailed key is a token this class generates itself
 *   (Usctdp_Send_Legacy_Import_Invites), and the account is created here,
 *   for the first time, only once the form is submitted with a password.
 */
class Usctdp_Mgmt_Import_Confirm_Hooks
{
    const NONCE_ACTION = 'usctdp_confirm_import';

    private function get_pending_by_id($pending_id)
    {
        if (empty($pending_id)) {
            return null;
        }
        $query = new Usctdp_Mgmt_Import_Pending_Query([
            'id' => $pending_id,
            'number' => 1,
        ]);
        return $query->items[0] ?? null;
    }

    private function verify_token($pending, $key)
    {
        if (empty($pending->confirm_token_hash) || empty($pending->confirm_token_expires_at)) {
            return false;
        }
        if (strtotime($pending->confirm_token_expires_at) < current_time('timestamp')) {
            return false;
        }
        return hash_equals($pending->confirm_token_hash, hash('sha256', $key));
    }

    /**
     * Validates a pending-row-id + key pair. Returns ['pending' => row,
     * 'user' => WP_User|null] on success - 'user' is null when the account
     * doesn't exist yet, which handle_confirm_submission() creates only
     * after every other validation has passed. Returns null on any failure
     * (unknown row, already confirmed, bad/expired key).
     */
    private function validate_confirm_request($pending_id, $key)
    {
        $pending = $this->get_pending_by_id($pending_id);
        if (!$pending || $pending->confirmed_at || empty($key)) {
            return null;
        }

        if ($pending->matched_existing_user) {
            $user = get_userdata($pending->user_id);
            if (!$user || is_wp_error(check_password_reset_key($key, $user->user_login))) {
                return null;
            }
            return ['pending' => $pending, 'user' => $user];
        }

        if (!$this->verify_token($pending, $key)) {
            return null;
        }
        return ['pending' => $pending, 'user' => null];
    }

    /**
     * Builds the data the import-confirm page template renders. Called on
     * every request to that page (both the initial emailed link and a POST
     * re-display after a validation error from handle_confirm_submission()).
     */
    public function get_confirm_context()
    {
        $pending_id = isset($_REQUEST['usctdp_import']) ? intval($_REQUEST['usctdp_import']) : 0;
        $key = isset($_REQUEST['usctdp_key']) ? sanitize_text_field(wp_unslash($_REQUEST['usctdp_key'])) : '';

        $pending_row = $this->get_pending_by_id($pending_id);
        if ($pending_row && $pending_row->confirmed_at) {
            return ['state' => 'already_confirmed'];
        }

        $result = $this->validate_confirm_request($pending_id, $key);
        if (!$result) {
            return ['state' => 'invalid'];
        }

        return [
            'state' => 'form',
            'requires_password' => !$result['pending']->matched_existing_user,
            'pending' => $result['pending'],
            'pending_id' => $pending_id,
            'key' => $key,
        ];
    }

    /**
     * Builds a unique username for a brand-new account from the staged
     * external id (falling back to last name), the same way
     * Usctdp_Import_Family_Data does for the bulk CLI importer.
     */
    private function generate_user_login($pending)
    {
        $base = sanitize_user(str_replace(' ', '', strtolower($pending->external_id ?: $pending->last)), true);
        $login = $base;
        $suffix = 1;
        while (username_exists($login)) {
            $suffix++;
            $login = $base . $suffix;
        }
        return $login;
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

        $pending_id = isset($_POST['usctdp_import']) ? intval($_POST['usctdp_import']) : 0;
        $key = isset($_POST['usctdp_key']) ? sanitize_text_field(wp_unslash($_POST['usctdp_key'])) : '';

        $result = $this->validate_confirm_request($pending_id, $key);
        if (!$result) {
            wc_add_notice(__('This import link is invalid, expired, or has already been used. Please contact the office for a new one.', 'usctdp-mgmt'), 'error');
            return;
        }
        $pending = $result['pending'];
        $user = $result['user'];

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
        if (!$user && strlen($new_password) < 8) {
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
            if (!$user) {
                $user_id = wp_insert_user([
                    'user_login' => $this->generate_user_login($pending),
                    'user_pass' => $new_password,
                    'user_email' => $email ?: ($pending->emails[0] ?? ''),
                    'first_name' => 'Family',
                    'last_name' => $last_name,
                    'display_name' => trim($last_name . ' ' . substr(trim($phone), -4)),
                    'role' => 'subscriber',
                ]);
                if (is_wp_error($user_id)) {
                    throw new Exception('Failed to create account: ' . $user_id->get_error_message());
                }
                $user = get_userdata($user_id);
            }

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
