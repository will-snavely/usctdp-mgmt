<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Session_Table extends Table
{
    public $name = 'usctdp_product_session';
    protected $db_version_key = 'usctdp_product_session_version';
    public $description = 'USCTDP Product Active Sessions';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY session_id (session_id)
        ";
    }
}
