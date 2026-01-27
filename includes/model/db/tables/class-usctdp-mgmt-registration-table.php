<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Registration_Table extends Table
{
    public $name = 'usctdp_registration';
    protected $db_version_key = 'usctdp_registration_version';
    public $description = 'USCTDP Registrations';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            activity_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            starting_level tinytext NOT NULL,
            balance smallint unsigned NOT NULL,
            notes text NOT NULL,
            PRIMARY KEY (id),
            KEY activity_id (activity_id),
            KEY student_id (student_id)
        ";
    }
}
