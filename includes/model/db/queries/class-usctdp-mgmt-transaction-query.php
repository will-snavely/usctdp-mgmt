<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Transaction_Query extends Query
{
    protected $table_name = 'usctdp_transaction';
    protected $table_alias = 'utrans';
    protected $table_schema = 'Usctdp_Mgmt_Transaction_Schema';
    protected $item_name = 'transaction';
    protected $item_name_plural = 'transactions';
    protected $item_shape = 'Usctdp_Mgmt_Transaction_Row';
}
