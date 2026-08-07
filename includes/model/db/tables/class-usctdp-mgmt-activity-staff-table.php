<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Staff_Table extends Table
{
    public $name = 'usctdp_activity_staff';
    protected $db_version_key = 'usctdp_activity_staff_version';
    public $description = 'USCTDP Activity Staff Assignments';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            activity_id bigint(20) unsigned NOT NULL,
            staff_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY activity_staff (activity_id, staff_id),
            KEY staff_id (staff_id)
        ";
    }
}
