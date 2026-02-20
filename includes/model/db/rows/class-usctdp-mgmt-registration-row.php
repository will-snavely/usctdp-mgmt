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
        $this->activity_id = (int) $this->activity_id;
        $this->student_id = (int) $this->student_id;
        $this->order_id = (int) $this->order_id;
        $this->checkout_reference_id = (string) $this->checkout_reference_id;
        $this->student_level = (string) $this->student_level;
        $this->credit = (int) $this->credit;
        $this->debit = (int) $this->debit;
        $this->notes = (string) $this->notes;
        $this->status = (int) $this->status;
        $this->created_at = (string) $this->created_at;
        $this->created_by = (int) $this->created_by;
        $this->last_modified_at = (string) $this->last_modified_at;
        $this->last_modified_by = (int) $this->last_modified_by;
    }
}
