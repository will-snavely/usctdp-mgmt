<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Payment_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->registration_id = (int) $this->registration_id;
        $this->order_id = (int) $this->order_id;
        $this->amount = (string) $this->amount;
        $this->house_credit_used = (string) $this->house_credit_used;
        $this->created_by = (int) $this->created_by;
        $this->created_at = (string) $this->created_at;
        $this->completed_at = (string) $this->completed_at;
        $this->reference_number = (string) $this->reference_number;
        $this->method = (string) $this->method;
        $this->status = (string) $this->status;
    }
}
