<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Tournament_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->start_date = DateTime::createFromFormat('Y-m-d', $this->start_date);
        $this->registration_deadline = DateTime::createFromFormat('Y-m-d', $this->registration_deadline);
        if (!empty($this->start_date_addtl)) {
            $this->start_date_addtl = DateTime::createFromFormat('Y-m-d', $this->start_date_addtl);
        } else {
            $this->start_date_addtl = null;
        }
        if (!empty($this->early_registration_deadline)) {
            $this->early_registration_deadline = DateTime::createFromFormat('Y-m-d', $this->early_registration_deadline);
        } else {
            $this->early_registration_deadline = null;
        }
        $this->schedule = json_decode($this->schedule);
    }
}
