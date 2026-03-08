<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Payment_Query extends Query
{
    protected $table_name = 'usctdp_payment';
    protected $table_alias = 'upay';
    protected $table_schema = 'Usctdp_Mgmt_Payment_Schema';
    protected $item_name = 'payment';
    protected $item_name_plural = 'payments';
    protected $item_shape = 'Usctdp_Mgmt_Payment_Row';
}
