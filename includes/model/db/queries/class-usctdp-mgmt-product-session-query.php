<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Session_Query extends Query
{
    protected $table_name = 'usctdp_product_session';
    protected $table_alias = 'uprodsess';
    protected $table_schema = 'Usctdp_Mgmt_Product_Session_Schema';
    protected $item_name = 'product_session';
    protected $item_name_plural = 'product_sessions';
    protected $item_shape = 'Usctdp_Mgmt_Product_Session_Row';
}
