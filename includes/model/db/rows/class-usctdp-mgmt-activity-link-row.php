<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Link_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id             = (int) $this->id;
        $this->activity_id    = (int) $this->activity_id;
        $this->student_id     = (int) $this->student_id;
    }
}
