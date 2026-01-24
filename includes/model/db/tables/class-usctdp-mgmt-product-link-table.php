<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Link_Table extends Table
{
    public $name = 'usctdp_product_link';
    protected $db_version_key = 'usctdp_product_link_version';
    public $description = 'USCTDP Product Links';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            activity_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY activity_id (activity_id),
            KEY product_id (product_id)
        ";
    }
}
