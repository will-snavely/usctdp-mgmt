<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Camp_Table extends Table
{
    public $name = 'usctdp_camp';
    protected $db_version_key = 'usctdp_camp_version';
    public $description = 'USCTDP Camp Days';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL,
            activity_date date NOT NULL,
            week_number tinyint unsigned NOT NULL,
            start_time time,
            end_time time,
            PRIMARY KEY (id),
            KEY activity_date (activity_date)
        ";
    }
}
