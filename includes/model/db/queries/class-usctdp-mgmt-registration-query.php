<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Registration_Query extends Query
{
    protected $table_name = 'usctdp_registration';
    protected $table_alias = 'ureg';
    protected $table_schema = 'Usctdp_Mgmt_Registration_Schema';
    protected $item_name = 'registration';
    protected $item_name_plural = 'registrations';
    protected $item_shape = 'Usctdp_Mgmt_Registration_Row';
}
