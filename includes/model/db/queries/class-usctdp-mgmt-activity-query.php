<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Query extends Query
{
    protected $table_name = 'usctdp_activity';
    protected $table_alias = 'uact';
    protected $table_schema = 'Usctdp_Mgmt_Activity_Schema';
    protected $item_name = 'activity';
    protected $item_name_plural = 'activities';
    protected $item_shape = 'Usctdp_Mgmt_Activity_Row';
}
