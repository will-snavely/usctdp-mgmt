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
            activity_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned,
            tracking_id tinytext,
            student_level tinytext,
            credit smallint unsigned,
            debit smallint unsigned,
            status tinyint unsigned NOT NULL,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            last_modified_at datetime,
            last_modified_by bigint(20) unsigned,
            notes text,
            PRIMARY KEY (id),
            KEY activity_id (activity_id),
            KEY student_id (student_id),
            KEY order_id (order_id)
        ";
    }
}
