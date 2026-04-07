<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Waitlist_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->activity_id = (int) $this->activity_id;
        $this->student_id = (int) $this->student_id;
        $this->priority = (int) $this->priority;
        $this->status = (string) $this->status;
        $this->created_at = (string) $this->created_at;
        $this->notified_at = (string) $this->notified_at;
        $this->expires_at = (string) $this->expires_at;
    }
}
