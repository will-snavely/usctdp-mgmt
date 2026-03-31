<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
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
            purchase_id bigint(20) unsigned NOT NULL,
            activity_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            student_level tinytext,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            modified_at datetime NOT NULL,
            modified_by bigint(20) unsigned NOT NULL,
            notes text,
            PRIMARY KEY (id),
            KEY purchase_id (purchase_id),
            KEY activity_id (activity_id),
            KEY student_id (student_id)
        ";
    }
}
