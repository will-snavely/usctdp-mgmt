<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Program_Schedule_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->product_id = (int) $this->product_id;
        $this->woocommerce_id = (int) $this->woocommerce_id;
        $this->type = (string) $this->type;
        $this->age_group = (string) $this->age_group;
        $this->level = (string) $this->level;
        $this->data = json_decode($this->data, true);
    }
}
