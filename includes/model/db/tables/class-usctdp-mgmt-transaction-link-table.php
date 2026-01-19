<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Transaction_Link_Table extends Table
{
    public $name = 'usctdp_transaction_link';
    protected $db_version_key = 'usctdp_transaction_link_version';
    public $description = 'USCTDP Transaction Links';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            transaction_id bigint(20) unsigned NOT NULL,
            registration_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY transaction_id (transaction_id),
            KEY registration_id (registration_id)
        ";
    }
}
