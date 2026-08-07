<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Staff_Query extends Query
{
    protected $table_name = 'usctdp_activity_staff';
    protected $table_alias = 'uactstaff';
    protected $table_schema = 'Usctdp_Mgmt_Activity_Staff_Schema';
    protected $item_name = 'activity_staff';
    protected $item_name_plural = 'activity_staff';
    protected $item_shape = 'Usctdp_Mgmt_Activity_Staff_Row';

    /**
     * Assigns a staff member to an activity. Idempotent - reassigning a
     * pair that's already linked just returns the existing row rather than
     * erroring or creating a duplicate (also enforced at the DB level by the
     * UNIQUE key on (activity_id, staff_id), same pattern as
     * Usctdp_Mgmt_Roster_Group_Query::add_session()).
     */
    public function assign_staff($activity_id, $staff_id)
    {
        $existing = new self([
            'activity_id' => $activity_id,
            'staff_id' => $staff_id,
            'number' => 1,
        ]);
        if (!empty($existing->items)) {
            return $existing->items[0];
        }

        $id = $this->add_item([
            'activity_id' => $activity_id,
            'staff_id' => $staff_id,
        ]);
        if (!$id) {
            return null;
        }

        $query = new self(['id' => $id, 'number' => 1]);
        return !empty($query->items) ? $query->items[0] : null;
    }

    /**
     * Removes a single staff/activity pairing. A no-op (returns false) if
     * that pair isn't currently assigned, rather than throwing - callers
     * that don't care whether it already existed can call this unconditionally.
     */
    public function unassign_staff($activity_id, $staff_id)
    {
        $existing = new self([
            'activity_id' => $activity_id,
            'staff_id' => $staff_id,
            'number' => 1,
        ]);
        if (empty($existing->items)) {
            return false;
        }
        return $this->delete_item($existing->items[0]->id);
    }

    /**
     * Every usctdp_staff row currently assigned to an activity, ordered by
     * name. Staff rows are never hard-deleted in practice (departed
     * instructors just stay in the table), so this join stays valid for
     * historical activities indefinitely - no snapshot of the staff member's
     * name is kept on the assignment row itself.
     */
    public function get_staff_for_activity($activity_id)
    {
        global $wpdb;
        $assignment_table = $wpdb->prefix . 'usctdp_activity_staff';
        $staff_table = $wpdb->prefix . 'usctdp_staff';

        $query = $wpdb->prepare(
            "SELECT staff.*
                FROM {$assignment_table} AS assign
                JOIN {$staff_table} AS staff ON staff.id = assign.staff_id
                WHERE assign.activity_id = %d
                ORDER BY staff.last_name, staff.first_name",
            $activity_id
        );
        $rows = $wpdb->get_results($query);
        return array_map(function ($row) {
            return new Usctdp_Mgmt_Staff_Row($row);
        }, $rows);
    }

    /**
     * Ids of every activity a staff member is currently assigned to. Mirrors
     * Usctdp_Mgmt_Roster_Group_Query::get_member_session_ids().
     */
    public function get_activity_ids_for_staff($staff_id)
    {
        $query = new self([
            'staff_id' => $staff_id,
            'orderby' => 'id',
            'order' => 'ASC',
        ]);
        return array_map(function ($assignment) {
            return (int) $assignment->activity_id;
        }, $query->items);
    }

    /**
     * Removes every staff assignment for an activity. Nothing currently
     * hard-deletes usctdp_activity rows (only waitlist entries are ever
     * deleted today - see class-usctdp-mgmt-admin-ajax.php), so this isn't
     * wired into an activity-delete path yet, but it's here for when one
     * exists - same shape as Usctdp_Mgmt_Roster_Group_Query::delete_group()'s
     * membership cleanup.
     */
    public function remove_for_activity($activity_id)
    {
        $query = new self(['activity_id' => $activity_id]);
        foreach ($query->items as $assignment) {
            $this->delete_item($assignment->id);
        }
    }
}
