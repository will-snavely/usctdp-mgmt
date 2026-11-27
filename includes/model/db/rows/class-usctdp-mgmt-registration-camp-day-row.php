<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Registration_Camp_Day_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->registration_id = (int) $this->registration_id;
        $this->activity_date = DateTime::createFromFormat('Y-m-d', $this->activity_date);
        // 'H:i:s', or null for a legacy row the 1.1.0 migration couldn't
        // resolve to a schedule slot (see
        // Usctdp_Mgmt_Registration_Camp_Day_Table::add_start_time_column()).
        $this->start_time = empty($this->start_time) ? null : (string) $this->start_time;
    }

    /**
     * This row as a 'Y-m-d H:i' slot key (see
     * Usctdp_Mgmt_Registration_Camp_Day_Query's class doc comment). A
     * legacy row with no start_time falls back to the bare date, which
     * matches no day-picker checkbox - it still displays and counts, but
     * the admin has to re-pick its slot to edit it.
     */
    public function slot_key()
    {
        $date = $this->activity_date->format('Y-m-d');
        if ($this->start_time === null) {
            return $date;
        }
        return Usctdp_Mgmt_Registration_Camp_Day_Query::slot_key($date, $this->start_time);
    }
}
