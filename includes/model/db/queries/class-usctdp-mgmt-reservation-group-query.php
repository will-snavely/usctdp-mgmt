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
