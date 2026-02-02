<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

enum Transaction_Kind: int
{
    case Payment = 1;
    case ClubCredit = 2;
}

enum Transaction_Method: int
{
    case Check = 1;
    case Cash = 2;
    case WebStore = 3;
    case PayPal = 4;
}

enum Check_Status: int
{
    case None = 0;
    case Pending = 1;
    case Voided = 2;
    case Cleared = 3;
    case Bounced = 4;
}

class Usctdp_Mgmt_Transaction_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->family_id = (int) $this->family_id;
        $this->created_by = (int) $this->created_by;
        $this->created_at = new DateTime($this->family_id);
        $this->kind = Transaction_Kind::from($this->kind);
        $this->method = Transaction_Method::from($this->method);
        $this->amount = (int) $this->amount;
        $this->check_status = Check_Status::from($this->check_status);
        $this->check_date_received = new DateTime($this->check_date_received);
        $this->check_cleared_date = new DateTime($this->check_cleared_date);
        $this->woocommerce_order_id = (int) $this->woocommerce_order_id;
        $this->paypal_transaction_id = (string) $this->paypal_transaction_id;
        $this->history = json_decode($this->history, true);
        $this->notes = (string) $this->notes;
    }
}
