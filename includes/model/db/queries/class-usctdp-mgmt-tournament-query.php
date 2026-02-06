<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Tournament_Query extends Query
{
    protected $table_name = 'usctdp_tournament';
    protected $table_alias = 'utorn';
    protected $table_schema = 'Usctdp_Mgmt_Tournament_Schema';
    protected $item_name = 'tournament';
    protected $item_name_plural = 'tournaments';
    protected $item_shape = 'Usctdp_Mgmt_Tournament_Row';
}
