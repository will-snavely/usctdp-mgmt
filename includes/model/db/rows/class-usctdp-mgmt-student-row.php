<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Student_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->family_id = (int) $this->family_id;
        $this->first = (string) $this->first;
        $this->last = (string) $this->last;
        $this->title = (string) $this->title;
        $this->birth_date = DateTime::createFromFormat('Y-m-d', $this->birth_date);
        $this->level = (string) $this->level;
    }
}
