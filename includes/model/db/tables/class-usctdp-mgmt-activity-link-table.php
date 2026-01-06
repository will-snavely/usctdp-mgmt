<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Link_Table extends Table
{
    public $name = 'usctdp_activity_link';
    protected $db_version_key = 'usctdp_activity_link_version';
    public $description = 'USCTDP Activity Links';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            activity_id int(11) unsigned NOT NULL,
            session_id int(11) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY activity_id (activity_id),
            KEY session_id (session_id)
        ";
    }
}
