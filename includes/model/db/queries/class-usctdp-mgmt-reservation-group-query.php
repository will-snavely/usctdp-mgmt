<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thrown for reservation-group roster generation that fails a business rule
 * (e.g. the group doesn't exist, or has no member activities to build a
 * roster from). Mirrors Roster_Group_Exception - callers should surface the
 * message directly rather than treating it as an unexpected server error.
 */
class Reservation_Group_Exception extends Exception
{
}

class Usctdp_Mgmt_Reservation_Group_Query extends Query
{
    protected $table_name = 'usctdp_reservation_group';
    protected $table_alias = 'uresg';
    protected $table_schema = 'Usctdp_Mgmt_Reservation_Group_Schema';
    protected $item_name = 'reservation_group';
    protected $item_name_plural = 'reservation_groups';
    protected $item_shape = 'Usctdp_Mgmt_Reservation_Group_Row';

    public function get_group($reservation_group_id)
    {
        $query = new Usctdp_Mgmt_Reservation_Group_Query(['id' => $reservation_group_id, 'number' => 1]);
        return !empty($query->items) ? $query->items[0] : null;
    }

    /**
     * Activity ids currently pointing at this group. Used by callers that
     * need to know whether a group is still a dedicated 1:1 group for a
     * single activity, or shared (see import-session-data.php's capacity
     * update guard, and merge_reservation_group's orphan cleanup).
     *
     * @return int[]
     */
    public function get_member_activity_ids($reservation_group_id)
    {
        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'reservation_group_id' => $reservation_group_id,
            'number' => 0,
        ]);
        return array_map(function ($activity) {
            return (int) $activity->id;
        }, $activity_query->items);
    }

    public function rename($reservation_group_id, $name)
    {
        $name = trim((string) $name);
        return $this->update_item($reservation_group_id, [
            'name' => $name === '' ? null : $name
        ]);
    }

    /**
     * Repoints a single activity at an already-existing reservation group,
     * then deletes its old group if that leaves it orphaned (no other
     * activity still referencing it). Unlike merge() (see
     * Usctdp_Manage_Reservation_Groups), which always creates a brand-new
     * group when combining several pre-existing ones - so "which group
     * wins" is never ambiguous - there's nothing ambiguous to resolve here:
     * $target_group_id already unambiguously exists and survives, this just
     * attaches the activity to it directly.
     *
     * @throws Reservation_Group_Exception On a missing activity/group or a failed write.
     */
    public function move_activity_to_group($activity_id, $target_group_id)
    {
        global $wpdb;
        $activity_query = new Usctdp_Mgmt_Activity_Query(['id' => $activity_id, 'number' => 1]);
        $activity = $activity_query->items[0] ?? null;
        if (!$activity) {
            throw new Reservation_Group_Exception("Activity #$activity_id not found.");
        }
        $target_group = $this->get_group($target_group_id);
        if (!$target_group) {
            throw new Reservation_Group_Exception("Reservation group #$target_group_id not found.");
        }

        $old_group_id = (int) $activity->reservation_group_id;
        if ($old_group_id === (int) $target_group_id) {
            return true;
        }

        $wpdb->query('START TRANSACTION');
        try {
            if (!$activity_query->update_item($activity_id, ['reservation_group_id' => $target_group_id])) {
                throw new Exception('Failed to repoint activity.');
            }
            if (empty($this->get_member_activity_ids($old_group_id))) {
                $this->delete_item($old_group_id);
            }
            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw new Reservation_Group_Exception('Failed to move activity to group: ' . $e->getMessage());
        }
        return true;
    }

    /**
     * Creates a brand-new dedicated reservation group and repoints the
     * given activity at it - splitting it out of a shared group (or just
     * setting an explicit new capacity/name without affecting any sibling
     * activities). Deletes the activity's old group if that leaves it
     * orphaned, same as move_activity_to_group() above.
     *
     * @return int The new group's id.
     * @throws Reservation_Group_Exception On a missing activity, invalid capacity, or a failed write.
     */
    public function create_group_for_activity($activity_id, $capacity, $name = null)
    {
        global $wpdb;
        $activity_query = new Usctdp_Mgmt_Activity_Query(['id' => $activity_id, 'number' => 1]);
        $activity = $activity_query->items[0] ?? null;
        if (!$activity) {
            throw new Reservation_Group_Exception("Activity #$activity_id not found.");
        }
        if (!is_numeric($capacity) || intval($capacity) < 0) {
            throw new Reservation_Group_Exception('Capacity must be a non-negative integer.');
        }
        $capacity = intval($capacity);
        $name = $name !== null ? trim((string) $name) : null;
        $name = ($name === '') ? null : $name;
        $old_group_id = (int) $activity->reservation_group_id;

        $wpdb->query('START TRANSACTION');
        $new_group_id = null;
        try {
            $new_group_id = $this->add_item([
                'capacity' => $capacity,
                'name' => $name,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ]);
            if (!$new_group_id) {
                throw new Exception('Failed to create new reservation group.');
            }
            if (!$activity_query->update_item($activity_id, ['reservation_group_id' => $new_group_id])) {
                throw new Exception('Failed to repoint activity.');
            }
            if (empty($this->get_member_activity_ids($old_group_id))) {
                $this->delete_item($old_group_id);
            }
            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw new Reservation_Group_Exception('Failed to create group: ' . $e->getMessage());
        }
        return $new_group_id;
    }

    /**
     * Single source of truth for a reservation group's roster display name -
     * used both to title the generated .docx (Usctdp_Mgmt_Docgen::
     * generate_and_upload_reservation_group_roster()) and the register
     * page's "View Roster" modal heading. Falls back to the member
     * activities' own titles, joined, when nobody's set an explicit name -
     * for a solo (unmerged) group this naturally returns just that one
     * activity's title, no special case needed.
     */
    public function get_roster_title($reservation_group_id)
    {
        $group = $this->get_group($reservation_group_id);
        if ($group && !empty($group->name)) {
            return $group->name;
        }

        $member_ids = $this->get_member_activity_ids($reservation_group_id);
        if (empty($member_ids)) {
            return 'Untitled Roster';
        }

        $activity_query = new Usctdp_Mgmt_Activity_Query(['id__in' => $member_ids, 'number' => 0]);
        $titles = array_map(function ($activity) {
            return $activity->title;
        }, $activity_query->items);

        return !empty($titles) ? implode(' / ', $titles) : 'Untitled Roster';
    }
}
