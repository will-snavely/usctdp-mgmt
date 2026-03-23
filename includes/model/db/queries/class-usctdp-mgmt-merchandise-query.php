<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Merchandise_Query extends Query
{
    protected $table_name = 'usctdp_merchandise';
    protected $table_alias = 'umerch';
    protected $table_schema = 'Usctdp_Mgmt_Merchandise_Schema';
    protected $item_name = 'merchandise';
    protected $item_name_plural = 'merchandise';
    protected $item_shape = 'Usctdp_Mgmt_Merchandise_Row';
}
