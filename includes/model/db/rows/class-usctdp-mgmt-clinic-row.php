<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Clinic_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->activity_id = (int) $this->activity_id;
        $this->day_of_week = Usctdp_Day_Of_Week::from($this->day_of_week);
        $this->start_time = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 ' . $this->start_time);
        $this->end_time = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 ' . $this->end_time);
        $this->capacity = (int) $this->capacity;
        $this->level = (string) $this->level;
        $this->notes = (string) $this->notes;
    }
}
