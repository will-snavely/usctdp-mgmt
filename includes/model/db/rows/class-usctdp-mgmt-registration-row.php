<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Registration_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->purchase_id = (int) $this->purchase_id;
        $this->activity_id = (int) $this->activity_id;
        $this->student_id = (int) $this->student_id;
        $this->status = (string) $this->status;
        $this->student_level = (string) $this->student_level;
        $this->created_at = (string) $this->created_at;
        $this->created_by = (int) $this->created_by;
        $this->modified_at = (string) $this->modified_at;
        $this->modified_by = (int) $this->modified_by;
        $this->notes = (string) $this->notes;
    }
}
