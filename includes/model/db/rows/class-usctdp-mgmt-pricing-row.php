<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Pricing_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->session_id = (int) $this->session_id;
        $this->product_id = (int) $this->product_id;
        $this->pricing = json_decode($this->pricing);
    }
}
