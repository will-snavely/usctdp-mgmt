<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Tournament_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->start_date = DateTime::createFromFormat('Y-m-d', $this->start_date);
        $this->registration_deadline = DateTime::createFromFormat('Y-m-d', $this->start_registration_deadline);
        $this->capacity = (int) $this->capacity;
        $this->days = json_decode($this->notes);
    }
}
