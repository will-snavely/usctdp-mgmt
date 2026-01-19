<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Transaction_Link_Query extends Query
{
    protected $table_name = 'usctdp_transaction_link';
    protected $table_alias = 'utlink';
    protected $table_schema = 'Usctdp_Mgmt_Transaction_Link_Schema';
    protected $item_name = 'transaction_link';
    protected $item_name_plural = 'transaction_links';
    protected $item_shape = 'Usctdp_Mgmt_Transaction_Link_Row';
}
