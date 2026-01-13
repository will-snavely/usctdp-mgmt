<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Link_Table extends Table
{
    public $name = 'usctdp_roster_link';
    protected $db_version_key = 'usctdp_roster_link_version';
    public $description = 'USCTDP Roster Links';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id int(11) unsigned NOT NULL,
            drive_id TINYTEXT NOT NULL,
            PRIMARY KEY (id),
            KEY entity_id (entity_id)
        ";
    }
}
