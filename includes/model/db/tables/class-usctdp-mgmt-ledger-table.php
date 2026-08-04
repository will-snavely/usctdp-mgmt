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
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'widen_payment_method_column',
    ];

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id bigint(20) UNSIGNED NOT NULL,
            purchase_id bigint(20) UNSIGNED DEFAULT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            event_id varchar(50) NOT NULL,
            event varchar(100) NOT NULL,
            account varchar(50) NOT NULL,
            description varchar(200) NOT NULL,
            entry_type varchar(20) DEFAULT NULL,
            payment_method varchar(100) DEFAULT NULL,
            reference_id varchar(100) DEFAULT NULL,
            debit decimal(10,2) NOT NULL DEFAULT 0.00,
            credit decimal(10,2) NOT NULL DEFAULT 0.00,
            created_by bigint(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_purchase_account_amount (purchase_id, account, debit, credit),
            INDEX idx_family_account (family_id, account),
            INDEX idx_reference_id (reference_id),
            INDEX idx_payment_method (payment_method)
        ";
    }

    /**
     * payment_method was varchar(20) - too narrow for some real gateway
     * slugs (e.g. PayPal's "ppcp-credit-card-gateway", 25 chars). WordPress's
     * own $wpdb->insert() checks column byte-length before running the query
     * and refuses silently rather than truncating, which is what caused
     * create_purchase_and_ledger_entries()/record_deferred_payment() to
     * leave purchases with zero ledger rows for any order paid through a
     * long-slug gateway - see class-usctdp-mgmt-woocommerce-hooks.php.
     * Widened to varchar(100) for headroom against future gateway slugs.
     *
     * $wpdb->query() returns 0 (not true) for a successful ALTER with no
     * matching rows to report, and 0 is empty() - so this must return an
     * explicit bool rather than the raw query result, or Table::is_success()
     * would treat a successful ALTER as a failed upgrade.
     */
    public function widen_payment_method_column()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_ledger';
        $result = $wpdb->query("ALTER TABLE {$table} MODIFY payment_method varchar(100) DEFAULT NULL");
        return $result !== false;
    }
}

