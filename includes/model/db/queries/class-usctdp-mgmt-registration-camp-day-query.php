<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A camp registration's booked slots. A slot is one (date, start_time)
 * pair, not just a date - a camp can run the same weekday more than once
 * (e.g. a Thursday 11 AM session and a separate Thursday 6 PM one), and
 * each is independently bookable and counts as its own day for pricing.
 *
 * Slots travel between JS and PHP as a single "slot key" string,
 * 'Y-m-d H:i' (24h start time, e.g. '2027-06-10 18:00') - see slot_key()/
 * parse_slot_key(). That's what the day-picker checkboxes carry as their
 * value (USCTDP_Admin.renderCampDayPicker()), what camp_dates holds on the
 * wire, and what ajax_get_registration_camp_days() returns.
 */
class Usctdp_Mgmt_Registration_Camp_Day_Query extends Query
{
    protected $table_name = 'usctdp_registration_camp_day';
    protected $table_alias = 'uregcampday';
    protected $table_schema = 'Usctdp_Mgmt_Registration_Camp_Day_Schema';
    protected $item_name = 'registration_camp_day';
    protected $item_name_plural = 'registration_camp_days';
    protected $item_shape = 'Usctdp_Mgmt_Registration_Camp_Day_Row';

    /**
     * A camp schedule time ("11:00 AM", as stored in usctdp_camp.schedule)
     * or a DB time ("18:00:00"/"18:00") -> 'H:i:s', or null if it can't be
     * parsed.
     */
    public static function normalize_start_time($time)
    {
        if (!is_string($time) || trim($time) === '') {
            return null;
        }
        $time = trim($time);
        foreach (['g:i A', 'g:iA', 'H:i:s', 'H:i'] as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $time);
            if ($parsed && $parsed->format($format) === $time) {
                return $parsed->format('H:i:s');
            }
        }
        return null;
    }

    /**
     * ('2027-06-10', '18:00:00') -> '2027-06-10 18:00'.
     */
    public static function slot_key($activity_date, $start_time)
    {
        return $activity_date . ' ' . substr($start_time, 0, 5);
    }

    /**
     * '2027-06-10 18:00' -> ['activity_date' => '2027-06-10', 'start_time' => '18:00:00'],
     * or null for anything malformed.
     */
    public static function parse_slot_key($key)
    {
        if (!is_string($key) || !preg_match('/^(\d{4}-\d{2}-\d{2}) ([01]\d|2[0-3]):([0-5]\d)$/', $key, $m)) {
            return null;
        }
        return [
            'activity_date' => $m[1],
            'start_time' => $m[2] . ':' . $m[3] . ':00',
        ];
    }

    /**
     * Sanitizes a raw camp_dates request array into unique, valid slot
     * keys. Anything malformed is silently dropped rather than erroring the
     * whole request over one bad value.
     */
    public static function sanitize_slot_keys($raw)
    {
        if (!is_array($raw)) {
            return [];
        }
        $keys = [];
        foreach ($raw as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $value = sanitize_text_field((string) $value);
            if (self::parse_slot_key($value) !== null) {
                $keys[$value] = true;
            }
        }
        return array_keys($keys);
    }

    /**
     * Adds one slot to a camp registration. Idempotent - re-adding a slot
     * that's already there just returns the existing row rather than
     * erroring or creating a duplicate (also enforced at the DB level by
     * the UNIQUE key on (registration_id, activity_date, start_time)), same
     * pattern as Usctdp_Mgmt_Activity_Staff_Query::assign_staff().
     */
    public function add_day($registration_id, $activity_date, $start_time)
    {
        $existing = new self([
            'registration_id' => $registration_id,
            'activity_date' => $activity_date,
            'start_time' => $start_time,
            'number' => 1,
        ]);
        if (!empty($existing->items)) {
            return $existing->items[0];
        }

        $id = $this->add_item([
            'registration_id' => $registration_id,
            'activity_date' => $activity_date,
            'start_time' => $start_time,
        ]);
        if (!$id) {
            return null;
        }

        $query = new self(['id' => $id, 'number' => 1]);
        return !empty($query->items) ? $query->items[0] : null;
    }

    /**
     * add_day() for a slot key (see the class doc comment). Returns null
     * for a malformed key.
     */
    public function add_slot($registration_id, $slot_key)
    {
        $slot = self::parse_slot_key($slot_key);
        if ($slot === null) {
            return null;
        }
        return $this->add_day($registration_id, $slot['activity_date'], $slot['start_time']);
    }

    /**
     * Drops a single slot from a camp registration. A no-op (returns false)
     * if that slot isn't currently on the registration, rather than
     * throwing - callers that don't care whether it was there can call this
     * unconditionally.
     */
    public function remove_slot($registration_id, $slot_key)
    {
        foreach ($this->get_days_for_registration($registration_id) as $day) {
            if ($day->slot_key() === $slot_key) {
                return $this->delete_item($day->id);
            }
        }
        return false;
    }

    /**
     * Every slot a registration currently covers, in (date, start_time)
     * order. Sorted here rather than via a multi-column BerlinDB orderby so
     * a legacy row with a NULL start_time still sorts predictably (first
     * within its date).
     */
    public function get_days_for_registration($registration_id)
    {
        $query = new self([
            'registration_id' => $registration_id,
            'number' => 0,
        ]);
        $items = $query->items;
        usort($items, function ($a, $b) {
            $date_cmp = strcmp($a->activity_date->format('Y-m-d'), $b->activity_date->format('Y-m-d'));
            if ($date_cmp !== 0) {
                return $date_cmp;
            }
            return strcmp((string) $a->start_time, (string) $b->start_time);
        });
        return $items;
    }

    /**
     * A registration's slots as slot keys, in (date, start_time) order.
     */
    public function get_slot_keys_for_registration($registration_id)
    {
        return array_map(function ($day) {
            return $day->slot_key();
        }, $this->get_days_for_registration($registration_id));
    }

    /**
     * Every registration_id signed up for a specific calendar day - the
     * source list for that day's roster. With $start_time, only that one
     * slot's registrations; without, every slot on that date.
     */
    public function get_registration_ids_for_day($activity_date, $start_time = null)
    {
        $args = [
            'activity_date' => $activity_date,
            'orderby' => 'registration_id',
            'order' => 'ASC',
            'number' => 0,
        ];
        if ($start_time !== null) {
            $args['start_time'] = $start_time;
        }
        $query = new self($args);
        return array_values(array_unique(array_map(function ($row) {
            return (int) $row->registration_id;
        }, $query->items)));
    }

    /**
     * Removes every slot for a registration. Not currently wired into a
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
