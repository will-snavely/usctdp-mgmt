<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Link_Query extends Query
{
    protected $table_name = 'usctdp_activity_link';
    protected $table_alias = 'uact';
    protected $table_schema = 'Usctdp_Mgmt_Activity_Link_Schema';
    protected $item_name = 'activity_link';
    protected $item_name_plural = 'activity_links';
    protected $item_shape = 'Usctdp_Mgmt_Activity_Link_Row';
}
