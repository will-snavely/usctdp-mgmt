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
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'add_status_created_at_index',
    ];

    public function set_schema()
    {
        // status + created_at is exactly how every report-style query
        // filters this table (Earnings dashboard, Purchase History, balance
        // lookups) - `WHERE status = 'active' AND created_at BETWEEN ...` -
        // so a single composite index (status leading, since it's always an
        // equality filter, then created_at for the range scan) covers that
        // pattern and still serves status-only filtering via the same
        // leftmost prefix. Listed here too (this is what a brand-new install
        // actually creates), but *not* relied on to reach existing
        // installations - see add_status_created_at_index() below for why.
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            family_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
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
            KEY tracking_id (tracking_id),
            KEY idx_status_created_at (status, created_at)
        ";
    }

    /**
     * Explicit, idempotent path onto existing installations for the index
     * declared in set_schema() above - deliberately not left to dbDelta()
     * (which Table::upgrade() also runs against that schema string on
     * every version bump) to reach existing tables on its own. dbDelta
     * *can* add a genuinely new, missing index like this one, but it does
     * so by silently diffing a whitespace/capitalization-sensitive
     * CREATE TABLE string against the live schema - there's no log of what
     * it decided to do or why, and if it and this method both try to add
     * the same index in the same request (which is what happened the
     * first time this shipped), the second one fails with a "Duplicate key
     * name" error. Checking information_schema first removes that
     * unpredictability entirely: this method becomes the actual source of
     * truth for whether the index exists, and is a safe no-op whether
     * dbDelta got there first, a previous run of this method did, or
     * neither did.
     */
    public function add_status_created_at_index()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_purchase';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = %s AND table_name = %s AND index_name = %s",
            DB_NAME,
            $table,
            'idx_status_created_at'
        ));
        if ($exists > 0) {
            return true;
        }

        $result = $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_status_created_at (status, created_at)");
        return $result !== false;
    }
}
