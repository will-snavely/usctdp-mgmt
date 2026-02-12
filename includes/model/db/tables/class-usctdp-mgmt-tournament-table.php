<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Tournament_Table extends Table
{
    public $name = 'usctdp_tournament';
    protected $db_version_key = 'usctdp_tournament_version';
    public $description = 'USCTDP Tournaments';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            activity_id bigint(20) unsigned NOT NULL,
            start_date date NOT NULL,
            registration_deadline date NOT NULL,
            days json,
            PRIMARY KEY (activity_id)
        ";
    }
}
