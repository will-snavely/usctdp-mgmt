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
            method tinyint unsigned NOT NULL,
            amount int signed NOT NULL,
            check_number tinytext,
            check_status tinyint unsigned,
            check_date_received date,
            ceck_cleared_date date,
            woocommerce_order_id bigint(20) unsigned,
            paypal_transaction_id tinytext,
            history json,
            notes text,
            PRIMARY KEY (id),
            KEY family_id (family_id),
            KEY woocommerce_order_id (woocommerce_order_id),
            KEY paypal_transaction_id (paypal_transaction_id)
        ";
    }
}
