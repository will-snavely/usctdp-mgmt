<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Group_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->name = $this->name !== null ? (string) $this->name : null;
        $this->drive_id = (string) $this->drive_id;
        if (!empty($this->updated_at)) {
            $this->updated_at = DateTime::createFromFormat('Y-m-d H:i:s', $this->updated_at, new DateTimeZone('UTC'));
        }
        if (!empty($this->created_at)) {
            $this->created_at = DateTime::createFromFormat('Y-m-d H:i:s', $this->created_at, new DateTimeZone('UTC'));
        }
    }
}
