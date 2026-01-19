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
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            kind tinyint unsigned NOT NULL,
            status tinyint unsigned NOT NULL,
            method tinyint unsigned NOT NULL,
            amount int signed NOT NULL,
            reference_id bigint(20),
            reference_string tinytext,
            notes text,
            PRIMARY KEY (id),
            KEY family_id (family_id),
            KEY reference_id (reference_id),
            KEY reference_string (reference_string(20))
        ";
    }
}
