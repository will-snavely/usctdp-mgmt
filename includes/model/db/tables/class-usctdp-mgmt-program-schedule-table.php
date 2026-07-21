<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Program_Schedule_Table extends Table
{
    public $name = 'usctdp_program_schedule';
    protected $db_version_key = 'usctdp_program_schedule_version';
    public $description = 'USCTDP Materialized Program Schedules';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            woocommerce_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            age_group varchar(50) NOT NULL,
            level varchar(50) NOT NULL,
            data json default '{}',
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY woocommerce_id (woocommerce_id),
            KEY type (type),
            KEY age_group (age_group),
            KEY level (level)
        ";
    }
}
