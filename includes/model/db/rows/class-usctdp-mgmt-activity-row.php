<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->title = (string) $this->title;
        $this->level = (int) $this->level;
        $this->search_term = (string) $this->search_term;
        $this->session_id = (int) $this->session_id;
        $this->product_id = (int) $this->product_id;
        $this->type = (string) $this->type;
        $this->capacity = (int) $this->capacity;
        $this->primary_sort_order = (int) $this->primary_sort_order;
        $this->secondary_sort_order = (int) $this->secondary_sort_order;
        $this->meta = (string) $this->meta;
    }
}
