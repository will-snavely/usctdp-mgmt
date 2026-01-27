<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Clinic_Class_Query extends Query
{
    protected $table_name = 'usctdp_clinic_class';
    protected $table_alias = 'ucc';
    protected $table_schema = 'Usctdp_Mgmt_Clinic_Class_Schema';
    protected $item_name = 'clinic_class';
    protected $item_name_plural = 'clinic_classes';
    protected $item_shape = 'Usctdp_Mgmt_Clinic_Class_Row';
}
