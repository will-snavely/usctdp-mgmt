<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Payment_Table extends Table
{
    public $name = 'usctdp_payment';
    protected $db_version_key = 'usctdp_payment_version';
    public $description = 'USCTDP Payments';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            registration_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            amount decimal(10, 2),
            house_credit_used decimal(10, 2),
            method tinytext,
            status tinytext,
            created_by bigint(20),
            created_at datetime,
            completed_at datetime,
            reference_number tinytext,
            PRIMARY KEY (id),
            KEY registration_id (registration_id),
            KEY order_id (order_id)
        ";
    }
}
