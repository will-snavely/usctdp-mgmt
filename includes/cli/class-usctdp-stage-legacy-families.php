<?php

/**
 * Loads legacy family data (the same JSON shape Usctdp_Import_Family_Data
 * consumes) into usctdp_import_pending instead of writing directly to
 * usctdp_family/usctdp_student. Nothing here is visible to the rest of the
 * plugin until a customer confirms it (a later step) - this command only
 * prepares that data.
 *
 * No WP account is created here, even for families with no existing login -
 * only families that already have one (found by email) get linked to it
 * (matched_existing_user = true); everyone else stays account-less
 * (user_id = 0) until they either click the confirm-import email (which
 * creates the account then) or just register normally on the site, which a
 * separate hook detects and reconciles against this row. Creating a
 * placeholder account here instead would make WooCommerce's own "email
 * already registered" check block that normal registration outright.
 */
class Usctdp_Stage_Legacy_Families
{
    private $dry_run = false;

    public function stage($file_path, $dry_run = false)
    {
        $this->dry_run = $dry_run;

        if (!file_exists($file_path)) {
            WP_CLI::error(sprintf('File not found: %s', $file_path));
            return;
        }

        $json_content = file_get_contents($file_path);
        if ($json_content === false) {
            WP_CLI::error(sprintf('Could not read file: %s', $file_path));
            return;
        }

        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error(sprintf('Error decoding JSON from file %s: %s', $file_path, json_last_error_msg()));
            return;
        }

        $staged = 0;
        $matched_existing = 0;
        $skipped = 0;

        foreach ($data as $family) {
            $result = $this->stage_family($family);
            if ($result === 'staged_new') {
                $staged++;
            } elseif ($result === 'staged_matched') {
                $staged++;
                $matched_existing++;
            } else {
                $skipped++;
            }
        }

        $prefix = $this->dry_run ? '[DRY RUN] ' : '';
        WP_CLI::log(sprintf(
            "{$prefix}Staged %d families (%d matched to existing accounts), skipped %d.",
            $staged,
            $matched_existing,
            $skipped
        ));
        WP_CLI::success('Legacy family staging complete.');
    }

    /**
     * Returns 'staged_new', 'staged_matched', or 'skipped'.
     */
    private function stage_family($family)
    {
        $external_id = $family['id'] ?? '';
        $last_name = $family['last'] ?? '';
        $email = trim($family['email1'] ?? ($family['email2'] ?? ''));

        if (empty($last_name)) {
            WP_CLI::warning(sprintf('Skipping family "%s": missing last name.', $external_id));
            return 'skipped';
        }
        if (empty($email)) {
            WP_CLI::warning(sprintf('Skipping family "%s": no email on file to invite them with.', $external_id));
            return 'skipped';
        }

        $existing_pending = new Usctdp_Mgmt_Import_Pending_Query([
            'external_id' => $external_id,
            'number' => 1,
        ]);
        if (!empty($existing_pending->items)) {
            WP_CLI::warning(sprintf('Skipping family "%s": already staged.', $external_id));
            return 'skipped';
        }

        $existing_user = get_user_by('email', $email);
        $matched_existing_user = (bool) $existing_user;

        if ($this->dry_run) {
            WP_CLI::log(sprintf(
                'Would stage family "%s" (%s) - %s',
                $external_id,
                $email,
                $matched_existing_user ? 'matches existing account #' . $existing_user->ID : 'no account yet'
            ));
            return $matched_existing_user ? 'staged_matched' : 'staged_new';
        }

        $user_id = $existing_user ? $existing_user->ID : 0;

        $emails = array_values(array_filter([$family['email1'] ?? '', $family['email2'] ?? '']));
        $phone_numbers = is_array($family['phone'] ?? null) ? $family['phone'] : [];
        $students = $this->build_students($family['children'] ?? [], $last_name);

        $pending_query = new Usctdp_Mgmt_Import_Pending_Query();
        $pending_id = $pending_query->add_item([
            'user_id' => $user_id,
            'external_id' => $external_id,
            'matched_existing_user' => $matched_existing_user ? 1 : 0,
            'last' => $last_name,
            'address' => $family['addr_street'] ?? '',
            'city' => $family['addr_city'] ?? '',
            'state' => $family['addr_state'] ?? '',
            'zip' => $family['addr_zip'] ?? '',
            'phone_numbers' => json_encode($phone_numbers),
            'emails' => json_encode($emails),
            'notes' => $family['notes'] ?? '',
            'students' => json_encode($students),
            'created_at' => current_time('mysql'),
        ]);

        if (!$pending_id) {
            WP_CLI::warning(sprintf('Skipping family "%s": failed to write staging row.', $external_id));
            return 'skipped';
        }

        return $matched_existing_user ? 'staged_matched' : 'staged_new';
    }

    private function build_students($children, $family_last_name)
    {
        $students = [];
        foreach ($children as $child) {
            $birth_date_str = '';
            if (!empty($child['birthday'])) {
                $birth_date = DateTime::createFromFormat('m/d/y H:i:s', $child['birthday']);
                if ($birth_date) {
                    $birth_date_str = $birth_date->format('Y-m-d');
                }
            }
            $students[] = [
                'first' => $child['name'] ?? '',
                'last' => $family_last_name,
                'birth_date' => $birth_date_str,
            ];
        }
        return $students;
    }
}
