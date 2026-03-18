<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Purchase_Query extends Query
{
    protected $table_name = 'usctdp_purchase';
    protected $table_alias = 'upur';
    protected $table_schema = 'Usctdp_Mgmt_Purchase_Schema';
    protected $item_name = 'purchase';
    protected $item_name_plural = 'purchases';
    protected $item_shape = 'Usctdp_Mgmt_Purchase_Row';
}
