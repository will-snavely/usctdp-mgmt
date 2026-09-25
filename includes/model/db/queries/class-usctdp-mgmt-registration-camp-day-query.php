<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Registration_Camp_Day_Query extends Query
{
    protected $table_name = 'usctdp_registration_camp_day';
    protected $table_alias = 'uregcampday';
    protected $table_schema = 'Usctdp_Mgmt_Registration_Camp_Day_Schema';
    protected $item_name = 'registration_camp_day';
    protected $item_name_plural = 'registration_camp_days';
    protected $item_shape = 'Usctdp_Mgmt_Registration_Camp_Day_Row';

    /**
     * Adds one day to a camp registration. Idempotent - re-adding a day
     * that's already there just returns the existing row rather than
     * erroring or creating a duplicate (also enforced at the DB level by
     * the UNIQUE key on (registration_id, activity_date)), same pattern as
     * Usctdp_Mgmt_Activity_Staff_Query::assign_staff().
     */
    public function add_day($registration_id, $activity_date)
    {
        $existing = new self([
            'registration_id' => $registration_id,
            'activity_date' => $activity_date,
            'number' => 1,
        ]);
        if (!empty($existing->items)) {
            return $existing->items[0];
        }

        $id = $this->add_item([
            'registration_id' => $registration_id,
            'activity_date' => $activity_date,
        ]);
        if (!$id) {
            return null;
        }

        $query = new self(['id' => $id, 'number' => 1]);
        return !empty($query->items) ? $query->items[0] : null;
    }

    /**
     * Drops a single day from a camp registration. A no-op (returns false)
     * if that day isn't currently on the registration, rather than
     * throwing - callers that don't care whether it was there can call this
     * unconditionally.
     */
    public function remove_day($registration_id, $activity_date)
    {
        $existing = new self([
            'registration_id' => $registration_id,
            'activity_date' => $activity_date,
            'number' => 1,
        ]);
        if (empty($existing->items)) {
            return false;
        }
        return $this->delete_item($existing->items[0]->id);
    }

    /**
     * Every day a registration currently covers, in date order.
     */
    public function get_days_for_registration($registration_id)
    {
        $query = new self([
            'registration_id' => $registration_id,
            'orderby' => 'activity_date',
            'order' => 'ASC',
            'number' => 0,
        ]);
        return $query->items;
    }

    /**
     * Every registration_id signed up for a specific calendar day - the
     * source list for that day's roster.
     */
    public function get_registration_ids_for_day($activity_date)
    {
        $query = new self([
            'activity_date' => $activity_date,
            'orderby' => 'registration_id',
            'order' => 'ASC',
            'number' => 0,
        ]);
        return array_map(function ($row) {
            return (int) $row->registration_id;
        }, $query->items);
    }

    /**
     * Removes every day for a registration. Not currently wired into a
     * registration-delete path (nothing hard-deletes registrations today -
     * see Usctdp_Mgmt_Activity_Staff_Query::remove_for_activity()'s same
     * note), but here for when one exists.
     */
    public function remove_for_registration($registration_id)
    {
        $query = new self(['registration_id' => $registration_id, 'number' => 0]);
        foreach ($query->items as $day) {
            $this->delete_item($day->id);
        }
    }
}
