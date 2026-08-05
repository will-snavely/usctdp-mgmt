<?php

use BerlinDB\Database\Row;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Group_Session_Row extends Row
{
    public function __construct($item)
    {
        parent::__construct($item);
        $this->id = (int) $this->id;
        $this->roster_group_id = (int) $this->roster_group_id;
        $this->session_id = (int) $this->session_id;
        if (!empty($this->created_at)) {
            $this->created_at = DateTime::createFromFormat('Y-m-d H:i:s', $this->created_at, new DateTimeZone('UTC'));
        }
    }
}
