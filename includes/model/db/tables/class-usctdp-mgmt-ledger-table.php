<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Ledger_Table extends Table
{
    public $name = 'usctdp_ledger';
    protected $db_version_key = 'usctdp_ledger_version';
    public $description = 'USCTDP Ledger';
    protected $version = '1.0.0';
    protected $upgrades = [];

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id bigint(20) UNSIGNED NOT NULL,
            registration_id bigint(20) UNSIGNED DEFAULT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            event_id varchar(50) NOT NULL,
            account varchar(50) NOT NULL,
            event varchar(50) NOT NULL,
            payment_method varchar(20) DEFAULT NULL,
            reference_id varchar(100) DEFAULT NULL,
            debit decimal(10,2) NOT NULL DEFAULT 0.00,
            credit decimal(10,2) NOT NULL DEFAULT 0.00,
            notes text,
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_registration (registration_id),
            INDEX idx_family_account (family_id, account),
            INDEX idx_ref (reference_id),
            INDEX idx_method (payment_method)
        ";
    }
}

