<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Group_Table extends Table
{
    public $name = 'usctdp_roster_group';
    protected $db_version_key = 'usctdp_roster_group_version';
    public $description = 'USCTDP Roster Groups';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name TINYTEXT NULL DEFAULT NULL,
            drive_id TINYTEXT NOT NULL,
            updated_at datetime NULL DEFAULT NULL,
            created_at datetime NULL DEFAULT NULL,
            PRIMARY KEY (id)
        ";
    }
}
