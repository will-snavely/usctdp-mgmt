<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Purchase_Table extends Table
{
    public $name = 'usctdp_purchase';
    protected $db_version_key = 'usctdp_purchase_version';
    public $description = 'USCTDP Purchases';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            family_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned DEFAULT NULL,
            tracking_id varchar(255) DEFAULT NULL,
            type varchar(50) NOT NULL,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            notes text DEFAULT NULL,
            discounts json DEFAULT '[]',
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY family_id (family_id),
            KEY student_id (student_id),
            KEY tracking_id (tracking_id)
        ";
    }
}
