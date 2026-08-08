<?php

/**
 * Admin tooling for usctdp_reservation_group, the shared-capacity pool
 * behind two or more activities that occupy the same physical space/time
 * slot (see Usctdp_Mgmt_Reservation_Group_Table). Every activity always
 * belongs to a group - most get their own dedicated 1:1 group, created
 * automatically at import time - so "merging" activities just repoints them
 * at one new shared group; there's no separate "ungrouped" state to handle.
 */
class Usctdp_Manage_Reservation_Groups
{
    /**
     * Repoints the given activities at one brand-new reservation group with
     * the given capacity, then deletes each of their old groups that's now
     * orphaned (no other activity still references it) - a group still
     * shared with some activity outside this merge is left alone.
     *
     * Always creates a fresh group rather than reusing one of the inputs'
     * existing groups, so "which group survives" is never ambiguous.
     * Capacity is always given explicitly rather than inferred from the
     * activities being merged, since they may currently disagree.
     *
     * $name is optional and titles the group's combined roster document
     * (Usctdp_Mgmt_Docgen::generate_and_upload_reservation_group_roster()).
     * Left null when omitted rather than defaulted to something computed
     * here - Usctdp_Mgmt_Reservation_Group_Query::get_roster_title()
     * already falls back to the merged activities' own titles, joined, and
     * doing that lazily at render time (not baked in here) keeps the title
     * in sync if an activity is renamed later. Use `rename()` to set/change
     * it after the fact.
     */
    public function merge($activity_ids, $capacity, $name = null)
    {
        global $wpdb;
        $activity_ids = array_values(array_unique(array_map('intval', $activity_ids)));
        if (count($activity_ids) < 2) {
            WP_CLI::error('Provide at least two distinct activity ids to merge.');
            return;
        }
        if (!is_numeric($capacity) || intval($capacity) < 0) {
            WP_CLI::error('Capacity must be a non-negative integer.');
            return;
        }
        $capacity = intval($capacity);
        $name = $name !== null ? trim((string) $name) : null;
        $name = ($name === '') ? null : $name;

        $activity_query = new Usctdp_Mgmt_Activity_Query(['id__in' => $activity_ids, 'number' => 0]);
        $activities = $activity_query->items;
        if (count($activities) !== count($activity_ids)) {
            $found_ids = array_map(function ($activity) {
                return (int) $activity->id;
            }, $activities);
            $missing = array_diff($activity_ids, $found_ids);
            WP_CLI::error('No activity found with id(s): ' . implode(', ', $missing));
            return;
        }

        $old_group_ids = array_unique(array_map(function ($activity) {
            return (int) $activity->reservation_group_id;
        }, $activities));

        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $new_group_id = null;
        $deleted = 0;

        $wpdb->query('START TRANSACTION');
        try {
            $new_group_id = $group_query->add_item([
                'capacity' => $capacity,
                'name' => $name,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ]);
            if (!$new_group_id) {
                throw new Exception('Failed to create new reservation group.');
            }

            foreach ($activities as $activity) {
                if (!$activity_query->update_item($activity->id, ['reservation_group_id' => $new_group_id])) {
                    throw new Exception('Failed to update activity #' . $activity->id . '.');
                }
            }

            foreach ($old_group_ids as $old_group_id) {
                if (empty($group_query->get_member_activity_ids($old_group_id))) {
                    $group_query->delete_item($old_group_id);
                    $deleted++;
                }
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            WP_CLI::error('Merge failed, nothing was changed: ' . $e->getMessage());
            return;
        }

        $titles = implode(', ', array_map(function ($activity) {
            return '#' . $activity->id . ' (' . $activity->title . ')';
        }, $activities));
        $name_note = $name !== null ? " named \"$name\"" : '';
        WP_CLI::success(
            "Merged $titles into new reservation group #$new_group_id{$name_note} (capacity $capacity). " .
                "Removed $deleted orphaned group(s)."
        );
    }

    public function rename($group_id, $name)
    {
        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $group = $group_query->get_group(intval($group_id));
        if (!$group) {
            WP_CLI::error("No reservation group found with id $group_id.");
            return;
        }

        if (!$group_query->rename($group->id, $name)) {
            WP_CLI::error('Failed to rename reservation group.');
            return;
        }

        $display_name = trim((string) $name) === '' ? '(cleared)' : trim((string) $name);
        WP_CLI::success("Reservation group #{$group->id} renamed to: $display_name");
    }

    /**
     * Explicitly runs (or resumes) the migration that gives every existing
     * activity its own dedicated reservation group - normally this happens
     * automatically the first time any request hits the site after
     * deploying a version bump (Usctdp_Mgmt_Model::register_berlindb_entities(),
     * hooked on `init`), but that means it fires as a side effect of
     * whichever request happens to land first. This triggers it
     * deterministically instead.
     *
     * Safe to run anytime, including before this plugin version has
     * otherwise touched the site, and safe to re-run: install_berlindb_entities()
     * checks each table's own version individually and no-ops tables that
     * are already current, and the activity table's own backfill step
     * (Usctdp_Mgmt_Activity_Table::backfill_reservation_groups()) is itself
     * resumable - see its docblock.
     */
    public function seed()
    {
        $model = new Usctdp_Mgmt_Model();
        $model->install_berlindb_entities();
    }

    public function set_capacity($group_id, $capacity)
    {
        if (!is_numeric($capacity) || intval($capacity) < 0) {
            WP_CLI::error('Capacity must be a non-negative integer.');
            return;
        }

        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $group = $group_query->get_group(intval($group_id));
        if (!$group) {
            WP_CLI::error("No reservation group found with id $group_id.");
            return;
        }

        $result = $group_query->update_item($group->id, [
            'capacity' => intval($capacity),
            'updated_at' => current_time('mysql', true),
        ]);
        if (!$result) {
            WP_CLI::error('Failed to update capacity.');
            return;
        }

        WP_CLI::success("Reservation group #{$group->id} capacity set to " . intval($capacity) . '.');
    }
}
