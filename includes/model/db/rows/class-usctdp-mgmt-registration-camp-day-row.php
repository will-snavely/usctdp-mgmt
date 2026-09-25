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
    }
}
