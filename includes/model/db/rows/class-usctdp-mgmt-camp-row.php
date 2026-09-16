<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Camp_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->activity_date = DateTime::createFromFormat('Y-m-d', $this->activity_date);
        $this->week_number = (int) $this->week_number;
        $this->start_time = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 ' . $this->start_time);
        $this->end_time = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 ' . $this->end_time);
    }
}
