<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

enum Day_Of_Week: int {
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;
}

class Usctdp_Mgmt_Clinic_Class_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->session_id = (int) $this->family_id;
        $this->clinic_id = (int) $this->created_by;
        $this->day_of_week = Day_Of_Week::from($this->day_of_week);
        $this->start_time = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 ' . $this->start_time);
        $this->end_time = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 ' . $this->end_time);
        $this->capacity = (int) $this->capacity;
        $this->level = (string) $this->level;
        $this->notes = (string) $this->notes;
    }
}
