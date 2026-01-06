<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Family_Link_Query extends Query
{
    protected $table_name = 'usctdp_family_link';
    protected $table_alias = 'ufam';
    protected $table_schema = 'Usctdp_Mgmt_Family_Link_Schema';
    protected $item_name = 'family_link';
    protected $item_name_plural = 'family_links';
    protected $item_shape = 'Usctdp_Mgmt_Family_Link_Row';
}
