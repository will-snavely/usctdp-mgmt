<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Family_Link_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->family_id = (int) $this->family_id;
        $this->student_id = (int) $this->student_id;
    }
}
