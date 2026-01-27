<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Clinic_Class_Table extends Table
{
    public $name = 'usctdp_clinic_class';
    protected $db_version_key = 'usctdp_clinic_class_version';
    public $description = 'USCTDP Clinic Classes';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            clinic_id bigint(20) unsigned NOT NULL,
            day_of_week tinyint unsigned,
            start_time time,
            end_time time,
            capacity smallint unsigned,
            level tinytext, 
            notes text,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY clinic_id (clinic_id)
        ";
    }
}
