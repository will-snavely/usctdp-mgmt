<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Program_Schedule_Query extends Query
{
    protected $table_name = 'usctdp_program_schedule';
    protected $table_alias = 'uprogsched';
    protected $table_schema = 'Usctdp_Mgmt_Program_Schedule_Schema';
    protected $item_name = 'program_schedule';
    protected $item_name_plural = 'program_schedules';
    protected $item_shape = 'Usctdp_Mgmt_Program_Schedule_Row';
}
