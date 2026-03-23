<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Ledger_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->family_id = (int) $this->family_id;
        $this->order_id = (int) $this->order_id;
        $this->event_id = (string) $this->event_id;
        $this->event = (string) $this->event;
        $this->account = (string) $this->account;
        $this->purchase_id = (int) $this->purchase_id;
        $this->debit = (string) $this->debit;
        $this->credit = (string) $this->credit;
        $this->created_by = (int) $this->created_by;
        $this->created_at = (string) $this->created_at;
        $this->reference_id = (string) $this->reference_id;
        $this->payment_method = (string) $this->payment_method;
        $this->notes = (string) $this->notes;
    }
}
