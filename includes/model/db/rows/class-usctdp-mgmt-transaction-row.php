<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

enum Transaction_Kind: int
{
    case Payment = 1;
    case Credit = 2;
}

enum Transaction_Method: int
{
    case Check = 1;
    case Cash = 2;
    case WebStore = 3;
    case PayPal = 4;
}

enum Transaction_Status: int
{
    case Pending = 1;
    case Voided = 2;
    case Completed = 3;
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
        $this->status = Transaction_Status::from($this->status);
        $this->method = Transaction_Method::from($this->method);
        $this->amount = (int) $this->amount;
        $this->reference_id = (int) $this->reference_id;
        $this->reference_string = (string) $this->notes;
        $this->notes = (string) $this->notes;
    }
}
