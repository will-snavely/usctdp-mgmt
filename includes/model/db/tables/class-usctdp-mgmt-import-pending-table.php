<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Import_Pending_Table extends Table
{
    public $name = 'usctdp_import_pending';
    protected $db_version_key = 'usctdp_import_pending_version';
    public $description = 'USCTDP Legacy Family Imports Awaiting Customer Confirmation';
    protected $version = '1.2.0';
    protected $upgrades = array(
        '1.1.0' => 'add_confirm_token_columns',
        '1.2.0' => 'fix_zero_date_defaults',
    );
    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            external_id tinytext,
            matched_existing_user tinyint(1) unsigned NOT NULL DEFAULT 0,
            last tinytext NOT NULL,
            address tinytext,
            city tinytext,
            state tinytext,
            zip tinytext,
            phone_numbers JSON,
            emails JSON,
            notes text,
            students JSON,
            confirm_token_hash varchar(64),
            confirm_token_expires_at datetime NULL DEFAULT NULL,
            invited_at datetime NULL DEFAULT NULL,
            confirmed_at datetime NULL DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ";
    }

    /**
     * user_id is now 0 until a customer either confirms (new account) or is
     * matched to an existing one at staging time - previously every staged
     * row got a placeholder WP account immediately, which meant a legacy
     * customer trying to self-register hit WooCommerce's "email already in
     * use" error before ever seeing the invite email.
     */
    protected function add_confirm_token_columns()
    {
        global $wpdb;
        $table = $this->table_name;
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS confirm_token_hash varchar(64) NULL");
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS confirm_token_expires_at datetime NULL");
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN user_id bigint(20) unsigned NOT NULL DEFAULT 0");
        return true;
    }

    /**
     * invited_at/confirmed_at/confirm_token_expires_at were declared as
     * plain `datetime` with no explicit NULL/default, so an unset value
     * silently stored as MySQL's zero-date sentinel ('0000-00-00 00:00:00')
     * instead of NULL - and empty('0000-00-00 00:00:00') is false, so every
     * "is this confirmed/invited yet" check across the plugin read every
     * never-confirmed row as already confirmed. This makes the columns
     * genuinely nullable and backfills any zero-dates already written.
     */
    protected function fix_zero_date_defaults()
    {
        global $wpdb;
        $table = $this->table_name;
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN invited_at datetime NULL DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN confirmed_at datetime NULL DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN confirm_token_expires_at datetime NULL DEFAULT NULL");
        $wpdb->query("UPDATE {$table} SET invited_at = NULL WHERE invited_at = '0000-00-00 00:00:00'");
        $wpdb->query("UPDATE {$table} SET confirmed_at = NULL WHERE confirmed_at = '0000-00-00 00:00:00'");
        $wpdb->query("UPDATE {$table} SET confirm_token_expires_at = NULL WHERE confirm_token_expires_at = '0000-00-00 00:00:00'");
        return true;
    }
}
