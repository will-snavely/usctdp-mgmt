<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Group_Session_Table extends Table
{
    public $name = 'usctdp_roster_group_session';
    protected $db_version_key = 'usctdp_roster_group_session_version';
    public $description = 'USCTDP Roster Group Sessions';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            roster_group_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned NOT NULL,
            created_at datetime NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY roster_group_id (roster_group_id)
        ";
    }
}
