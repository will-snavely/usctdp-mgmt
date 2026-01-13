<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Link_Query extends Query
{
    protected $table_name = 'usctdp_roster_link';
    protected $table_alias = 'url';
    protected $table_schema = 'Usctdp_Mgmt_Roster_Link_Schema';
    protected $item_name = 'roster_link';
    protected $item_name_plural = 'roster_links';
    protected $item_shape = 'Usctdp_Mgmt_Roster_Link_Row';
}
