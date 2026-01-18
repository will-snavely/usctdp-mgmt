<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Transaction_Table extends Table
{
    public $name = 'usctdp_transaction';
    protected $db_version_key = 'usctdp_transaction_version';
    public $description = 'USCTDP Transactions';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            family_id bigint(20) unsigned NOT NULL,
            registration_id bigint(20) unsigned NOT NULL,
            kind tinyint unsigned NOT NULL,
            amount int signed NOT NULL,
            method tinytext NOT NULL,
            notes text NOT NULL,
            PRIMARY KEY (id),
            KEY family_id (activity_id),
            KEY registration_id (student_id)
        ";
    }
}
