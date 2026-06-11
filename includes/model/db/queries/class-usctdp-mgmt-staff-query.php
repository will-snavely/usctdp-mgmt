<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Staff_Query extends Query
{
    protected $table_name = 'usctdp_staff';
    protected $table_alias = 'ustaff';
    protected $table_schema = 'Usctdp_Mgmt_Staff_Schema';
    protected $item_name = 'staff';
    protected $item_name_plural = 'staff';
    protected $item_shape = 'Usctdp_Mgmt_Staff_Row';
}
