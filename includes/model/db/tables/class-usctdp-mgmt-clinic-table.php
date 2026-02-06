<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Clinic_Table extends Table
{
    public $name = 'usctdp_clinic';
    protected $db_version_key = 'usctdp_clinic_version';
    public $description = 'USCTDP Clinics';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public static function create_title($clinic_name, $dow, $start_time)
    {
        $time = $start_time->format('g:i A');
        return sanitize_text_field("$clinic_name, $dow at $time");
    }

    public function set_schema()
    {
        $this->schema = "
            activity_id bigint(20) unsigned NOT NULL,
            day_of_week tinyint unsigned,
            start_time time,
            end_time time,
            capacity smallint unsigned,
            level tinytext, 
            notes text,
            PRIMARY KEY (activity_id)
        ";
    }
}
