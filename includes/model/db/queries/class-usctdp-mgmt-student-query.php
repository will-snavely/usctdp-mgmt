<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Student_Query extends Query
{
    protected $table_name = 'usctdp_student';
    protected $table_alias = 'ustu';
    protected $table_schema = 'Usctdp_Mgmt_Student_Schema';
    protected $item_name = 'student';
    protected $item_name_plural = 'students';
    protected $item_shape = 'Usctdp_Mgmt_Student_Row';
}
