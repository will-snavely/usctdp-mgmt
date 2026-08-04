<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Registration_Table extends Table
{
    public $name = 'usctdp_registration';
    protected $db_version_key = 'usctdp_registration_version';
    public $description = 'USCTDP Registrations';
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'add_order_id_column',
    ];

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            purchase_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            activity_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            tracking_id varchar(50),
            status varchar(20) NOT NULL DEFAULT 'active',
            student_level tinytext,
            created_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            modified_at datetime NOT NULL,
            modified_by bigint(20) unsigned NOT NULL,
            notes text,
            PRIMARY KEY (id),
            KEY purchase_id (purchase_id),
            KEY order_id (order_id),
            KEY activity_id (activity_id),
            KEY student_id (student_id)
        ";
    }

    /**
     * order_id was intentionally removed from this table in f7cfe73
     * ("Revising model for payments") in favor of tracking_id, but the
     * calling code in class-usctdp-mgmt-woocommerce-hooks.php
     * (confirm_registration(), create_purchase_and_ledger_entries()) was
     * never updated off of it - it kept filtering registration queries by
     * 'order_id' => $order_id, a key with no matching schema column, which
     * BerlinDB silently drops from the WHERE clause instead of erroring.
     * confirm_registration()'s query in particular actually ran as bare
     * ['status' => 'pending'], activating up to 100 unrelated pending
     * registrations (the base class's default limit) on every successful
     * order - including other customers' failed or abandoned ones.
     *
     * Re-added as a real column: direct, single-hop order scoping for
     * confirm_registration()/create_purchase_and_ledger_entries()/
     * release_registrations_for_order(), set once by
     * checkout_order_processed() right after the order is created.
     * tracking_id stays for the job order_id can't do - identifying a
     * specific checkout attempt (after_checkout_validation()'s
     * already_enrolled check, and checkout_order_processed()'s own lookup
     * of which specific pending registration a line item belongs to) - all
     * of which happens before an order_id even exists.
     *
     * Nullable for the same reason: a registration is created 'pending'
     * before its order exists, so order_id starts null and is only filled
     * in once checkout_order_processed() links it afterward.
     *
     * $wpdb->query() returns 0 (not true) for a successful ALTER with no
     * matching rows to report, and 0 is empty() - so this must return an
     * explicit bool rather than the raw query result, or Table::is_success()
     * would treat a successful ALTER as a failed upgrade (same reasoning as
     * Usctdp_Mgmt_Ledger_Table::widen_payment_method_column()).
     */
    public function add_order_id_column()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_registration';
        $result = $wpdb->query("ALTER TABLE {$table} ADD COLUMN order_id bigint(20) unsigned DEFAULT NULL, ADD INDEX order_id (order_id)");
        return $result !== false;
    }
}
