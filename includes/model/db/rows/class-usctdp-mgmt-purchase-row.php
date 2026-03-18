<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Purchase_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->product_id = (int) $this->product_id;
        $this->family_id = (int) $this->family_id;
        $this->tracking_id = (string) $this->tracking_id;
        $this->type = (string) $this->type;
        $this->created_at = (string) $this->created_at;
        $this->created_by = (int) $this->created_by;
    }
}
