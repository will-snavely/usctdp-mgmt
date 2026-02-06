<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Query extends Query
{
    protected $table_name = 'usctdp_product';
    protected $table_alias = 'uprod';
    protected $table_schema = 'Usctdp_Mgmt_Product_Schema';
    protected $item_name = 'product';
    protected $item_name_plural = 'products';
    protected $item_shape = 'Usctdp_Mgmt_Product_Row';
}
