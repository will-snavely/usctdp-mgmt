<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Session_Table extends Table
{
    public $name = 'usctdp_session';
    protected $db_version_key = 'usctdp_session_version';
    public $description = 'USCTDP Sessions';
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'migrate_is_active_to_status',
    ];

    public static function create_title(
        $name,
        $length_weeks,
        $start_date,
        $end_date
    ) {
        $start = $start_date->format('Y');
        $end = $end_date->format('Y');
        $year = $start;
        if ($start != $end) {
            $year = $start . '/' . $end;
        }
        return sanitize_text_field($name . ' - ' . $year);
    }

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title tinytext,
            search_term tinytext,
            status varchar(20) NOT NULL DEFAULT 'scheduled',
            start_date date,
            end_date date,
            num_weeks tinyint unsigned,
            category tinyint unsigned,
            season varchar(50),
            meta json default '{}',
            PRIMARY KEY (id),
            INDEX title_prefix (title(10)),
            INDEX idx_start_date (start_date),
            INDEX idx_status (status),
            FULLTEXT search (search_term)
        ";
    }

    /**
     * Replaces the old is_active bool with a 'scheduled' | 'on_sale' |
     * 'archived' lifecycle status - see the 'status' entry in
     * Usctdp_Mgmt_Session_Schema for what each state means. Deliberately
     * not left to dbDelta() (which Table::upgrade() also runs against
     * set_schema() above on every version bump) to reach existing
     * installations on its own - see class-usctdp-mgmt-purchase-table.php's
     * add_status_created_at_index() for why. Every step here checks
     * information_schema first and is safe to re-run.
     */
    public function migrate_is_active_to_status()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_session';

        if (!$this->usctdp_column_exists($table, 'status')) {
            $result = $wpdb->query(
                "ALTER TABLE {$table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'scheduled'"
            );
            if ($result === false) {
                return false;
            }
        }

        if ($this->usctdp_column_exists($table, 'is_active')) {
            $product_session_table = $wpdb->prefix . 'usctdp_product_session';
            // One-time backfill from the old is_active flag, run only while
            // is_active still exists (so a re-run after it's dropped is a
            // no-op rather than re-deriving from a column that's gone).
            // There was never a real "on sale" flag to read - the closest
            // available signal is whether some product currently has this
            // session wired up for sale, i.e. rows in usctdp_product_session
            // (populated by the CLI importer). This is a heuristic: it
            // reflects the last import run, not necessarily any manual
            // WooCommerce edits since.
            $wpdb->query("UPDATE {$table} SET status = 'archived' WHERE is_active = 0");
            $wpdb->query("
                UPDATE {$table} sesh
                SET status = 'on_sale'
                WHERE is_active = 1
                  AND EXISTS (SELECT 1 FROM {$product_session_table} ps WHERE ps.session_id = sesh.id)
            ");
            // Remaining is_active = 1 rows have no product_session rows, so
            // they stay at the column's 'scheduled' default - nothing left
            // to set explicitly for them.

            if (!$this->usctdp_index_exists($table, 'idx_status')) {
                $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_status (status)");
            }
            if ($this->usctdp_index_exists($table, 'idx_active_items')) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX idx_active_items");
            }

            $result = $wpdb->query("ALTER TABLE {$table} DROP COLUMN is_active");
            return $result !== false;
        }

        if (!$this->usctdp_index_exists($table, 'idx_status')) {
            $result = $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_status (status)");
            return $result !== false;
        }

        return true;
    }

    private function usctdp_column_exists($table, $column)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE table_schema = %s AND table_name = %s AND column_name = %s",
            DB_NAME,
            $table,
            $column
        ));
        return $count > 0;
    }

    private function usctdp_index_exists($table, $index)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = %s AND table_name = %s AND index_name = %s",
            DB_NAME,
            $table,
            $index
        ));
        return $count > 0;
    }
}
