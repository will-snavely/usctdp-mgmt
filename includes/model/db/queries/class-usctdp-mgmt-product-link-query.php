<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Link_Query extends Query
{
    protected $table_name = 'usctdp_product_link';
    protected $table_alias = 'uplink';
    protected $table_schema = 'Usctdp_Mgmt_Product_Link_Schema';
    protected $item_name = 'product_link';
    protected $item_name_plural = 'product_links';
    protected $item_shape = 'Usctdp_Mgmt_Product_Link_Row';
}
