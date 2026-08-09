<?php

/**
 * Permanently removes a family created for testing (or otherwise bad, e.g.
 * an obvious spam signup) - the usctdp_family row itself plus everything
 * that references it: its students, purchases, ledger entries,
 * registrations, waitlist entries, and its linked wp_user account.
 *
 * None of usctdp_student/usctdp_purchase/usctdp_ledger/usctdp_registration/
 * usctdp_waitlist have a real FOREIGN KEY back to usctdp_family (see e.g.
 * Usctdp_Mgmt_Student_Table::set_schema() - just a plain KEY index), so
 * nothing at the DB level stops a bare "delete the family row" from leaving
 * every one of those behind, still pointing at a family_id that no longer
 * exists. This walks the same relationships by hand instead, in dependency
 * order, inside one transaction per family.
 *
 * usctdp_merchandise is deliberately not touched - nothing in this codebase
 * ever writes to it (Usctdp_Mgmt_Merchandise_Query::add_item() has no
 * caller), so there's never anything there to clean up.
 *
 * Identification is always by family_id, explicitly passed in - never by a
 * search term or a "looks like test data" heuristic. Deciding whether an
 * account is actually safe to delete is a judgment call for whoever's
 * running the command, not something to guess at automatically.
 *
 * Report-only by default, same convention as Usctdp_Reconcile_Ledger /
 * Usctdp_Audit_Registrations / Usctdp_Void_Stale_Registrations - --fix
 * actually deletes. Unlike those (which only correct or void records),
 * deleting is irreversible, so --fix additionally asks for interactive
 * confirmation (WP_CLI::confirm(), skippable with --yes) after printing
 * exactly what would be removed.
 */
class Usctdp_Delete_Family
{
    private function find_students($family_id)
    {
        $query = new Usctdp_Mgmt_Student_Query(['family_id' => $family_id, 'number' => 0]);
        return $query->items;
    }

    private function find_purchases($family_id)
    {
        $query = new Usctdp_Mgmt_Purchase_Query(['family_id' => $family_id, 'number' => 0]);
        return $query->items;
    }

    private function find_ledger_entries($family_id)
    {
        $query = new Usctdp_Mgmt_Ledger_Query(['family_id' => $family_id, 'number' => 0]);
        return $query->items;
    }

    private function find_registrations($student_ids)
    {
        if (empty($student_ids)) {
            return [];
        }
        $query = new Usctdp_Mgmt_Registration_Query(['student_id__in' => $student_ids, 'number' => 0]);
        return $query->items;
    }

    private function find_waitlist_entries($student_ids)
    {
        if (empty($student_ids)) {
            return [];
        }
        $query = new Usctdp_Mgmt_Waitlist_Query(['student_id__in' => $student_ids, 'number' => 0]);
        return $query->items;
    }

    private function gather($family_id)
    {
        $family = Usctdp_Mgmt_Model::get_family($family_id);
        if (!$family) {
            return null;
        }

        $students = $this->find_students($family_id);
        $student_ids = array_map(function ($student) {
            return $student->id;
        }, $students);

        return [
            'family' => $family,
            'students' => $students,
            'purchases' => $this->find_purchases($family_id),
            'ledger_entries' => $this->find_ledger_entries($family_id),
            'registrations' => $this->find_registrations($student_ids),
            'waitlist_entries' => $this->find_waitlist_entries($student_ids),
        ];
    }

    /**
     * user_id on the family row can point at a wp_users row that's already
     * gone - deleted by hand, by a previous run of this same command, or by
     * anything else that calls wp_delete_user() directly. Surfacing that
     * here (rather than just "#123") means the pre-delete report already
     * tells the operator this account has no login left to clean up, and
     * delete() below relies on this same get_userdata() check to skip
     * calling wp_delete_user() on an id that's already gone.
     */
    private function describe_user($user_id)
    {
        if (empty($user_id)) {
            return '(none)';
        }
        return get_userdata($user_id) ? "#{$user_id}" : "#{$user_id} (already deleted)";
    }

    private function print_summary($data)
    {
        $family = $data['family'];
        $contact = implode(', ', array_filter([
            $family->emails[0] ?? '',
            $family->phone_numbers[0] ?? '',
        ]));

        WP_CLI::log(sprintf('Family #%d: %s%s', $family->id, $family->title, $contact ? " ($contact)" : ''));
        WP_CLI::log('  wp user:          ' . $this->describe_user($family->user_id));
        WP_CLI::log('  students:         ' . count($data['students']));
        foreach ($data['students'] as $student) {
            WP_CLI::log("    - #{$student->id} {$student->title}");
        }
        WP_CLI::log('  purchases:        ' . count($data['purchases']));
        WP_CLI::log('  ledger entries:   ' . count($data['ledger_entries']));
        WP_CLI::log('  registrations:    ' . count($data['registrations']));
        WP_CLI::log('  waitlist entries: ' . count($data['waitlist_entries']));
    }

    /**
     * Deletes in dependency order (leaves first, family last) inside one
     * transaction, so a failure partway through leaves nothing half-deleted.
     * The linked wp_user is removed afterward, deliberately outside that
     * transaction - wp_delete_user() touches wp_users/wp_usermeta and fires
     * its own core hooks, not something to fold into the same rollback
     * boundary as the plugin's own tables. By the time it runs the family
     * row is already gone for good, so it's best-effort cleanup of the
     * linked login, not something worth retrying or rolling back for.
     */
    private function delete($data)
    {
        global $wpdb;
        $family_id = $data['family']->id;

        $wpdb->query('START TRANSACTION');
        try {
            $waitlist_query = new Usctdp_Mgmt_Waitlist_Query();
            foreach ($data['waitlist_entries'] as $entry) {
                if (!$waitlist_query->delete_item($entry->id)) {
                    throw new Exception("Failed to delete waitlist entry #{$entry->id}");
                }
            }

            $registration_query = new Usctdp_Mgmt_Registration_Query();
            foreach ($data['registrations'] as $registration) {
                if (!$registration_query->delete_item($registration->id)) {
                    throw new Exception("Failed to delete registration #{$registration->id}");
                }
            }

            $ledger_query = new Usctdp_Mgmt_Ledger_Query();
            foreach ($data['ledger_entries'] as $entry) {
                if (!$ledger_query->delete_item($entry->id)) {
                    throw new Exception("Failed to delete ledger entry #{$entry->id}");
                }
            }

            $purchase_query = new Usctdp_Mgmt_Purchase_Query();
            foreach ($data['purchases'] as $purchase) {
                if (!$purchase_query->delete_item($purchase->id)) {
                    throw new Exception("Failed to delete purchase #{$purchase->id}");
                }
            }

            $student_query = new Usctdp_Mgmt_Student_Query();
            foreach ($data['students'] as $student) {
                if (!$student_query->delete_item($student->id)) {
                    throw new Exception("Failed to delete student #{$student->id}");
                }
            }

            $family_query = new Usctdp_Mgmt_Family_Query();
            if (!$family_query->delete_item($family_id)) {
                throw new Exception("Failed to delete family #{$family_id}");
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            WP_CLI::warning("Family #{$family_id}: " . $e->getMessage() . ' - rolled back, nothing was deleted.');
            return false;
        }

        // get_userdata() guards against a user_id that's already stale (the
        // wp_users row is gone, e.g. someone deleted the account by hand
        // before running this) - wp_delete_user() returns false for a
        // nonexistent user, which would otherwise look identical to a real
        // deletion failure and produce a misleading warning below.
        $user_id = $data['family']->user_id;
        if (!empty($user_id) && get_userdata($user_id)) {
            if (!wp_delete_user($user_id)) {
                WP_CLI::warning("Family #{$family_id}: deleted, but failed to delete linked wp user #{$user_id}.");
            }
        }

        return true;
    }

    public function run($family_ids, $fix, $assoc_args = [])
    {
        $found = [];
        foreach ($family_ids as $family_id) {
            $family_id = intval($family_id);
            $data = $this->gather($family_id);
            if (!$data) {
                WP_CLI::warning("No family found with id: $family_id");
                continue;
            }
            $found[] = $data;
        }

        if (empty($found)) {
            WP_CLI::error('Nothing to delete.');
            return;
        }

        WP_CLI::log('');
        foreach ($found as $data) {
            $this->print_summary($data);
            WP_CLI::log('');
        }

        $count = count($found);
        $noun = $count === 1 ? 'family' : 'families';

        if (!$fix) {
            WP_CLI::log("$count $noun found. Re-run with --fix to permanently delete them and everything listed above.");
            return;
        }

        WP_CLI::confirm(
            "Permanently delete $count $noun and everything listed above? This cannot be undone.",
            $assoc_args
        );

        $deleted = 0;
        $failed = 0;
        foreach ($found as $data) {
            if ($this->delete($data)) {
                WP_CLI::log("Family #{$data['family']->id}: deleted.");
                $deleted++;
            } else {
                $failed++;
            }
        }

        WP_CLI::log('');
        WP_CLI::log("Deleted $deleted, failed $failed, out of $count found.");
    }
}
