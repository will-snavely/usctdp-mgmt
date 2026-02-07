<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Session_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->title = (string) $this->title;
        $this->search_term = (string) $this->search_term;
        $this->start_date = DateTime::createFromFormat('Y-m-d', $this->start_date);
        $this->end_date = DateTime::createFromFormat('Y-m-d', $this->end_date);
        $this->num_weeks = (int) $this->num_weeks;
        $this->category = Usctdp_Session_Category::from($this->category);
        $this->notes = (string) $this->notes;
    }
}
