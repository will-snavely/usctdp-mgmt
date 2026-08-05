<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Group_Session_Query extends Query
{
    protected $table_name = 'usctdp_roster_group_session';
    protected $table_alias = 'urgs';
    protected $table_schema = 'Usctdp_Mgmt_Roster_Group_Session_Schema';
    protected $item_name = 'roster_group_session';
    protected $item_name_plural = 'roster_group_sessions';
    protected $item_shape = 'Usctdp_Mgmt_Roster_Group_Session_Row';
}
