<?php

use BerlinDB\Database\Row;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Link_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->entity_id = (int) $this->entity_id;
        $this->drive_id = (string) $this->drive_id;
    }
}
