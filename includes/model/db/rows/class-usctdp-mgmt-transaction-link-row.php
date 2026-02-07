<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Transaction_Link_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->transaction_id = (int) $this->transaction_id;
        $this->registration_id = (int) $this->registration_id;
    }
}
