<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Pricing_Table extends Table
{
    public $name = 'usctdp_pricing';
    protected $db_version_key = 'usctdp_pricing_version';
    public $description = 'USCTDP Pricing';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            pricing json,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY product_id (product_id)
        ";
    }
}
