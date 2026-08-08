<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Table extends Table
{
    public $name = 'usctdp_activity';
    protected $db_version_key = 'usctdp_activity_version';
    public $description = 'USCTDP Activities';
    protected $version = '1.3.0';
    protected $upgrades = [
        '1.1.0' => 'add_reservation_group_id_column',
        '1.2.0' => 'backfill_reservation_groups',
        '1.3.0' => 'finalize_reservation_group_id_column',
    ];

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            title tinytext,
            level tinytext,
            search_term tinytext,
            reservation_group_id bigint(20) unsigned NOT NULL,
            primary_sort_order smallint unsigned,
            secondary_sort_order smallint unsigned,
            meta json default '{}',
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY product_id (product_id),
            KEY reservation_group_id (reservation_group_id),
            FULLTEXT search (search_term)
        ";
    }

    /**
     * Step 1/3 of moving capacity off this table onto usctdp_reservation_group
     * (see backfill_reservation_groups(), finalize_reservation_group_id_column()).
     * Nullable for now - existing rows have nothing to point at yet - NOT
     * NULL is enforced once every row is backfilled.
     *
     * column_exists() guards a retry after an earlier partial run: BerlinDB
     * only advances usctdp_activity_version past this step once this method
     * returns truthy, so an interrupted run (e.g. request timeout) re-enters
     * here on the next request rather than skipping ahead.
     */
    public function add_reservation_group_id_column()
    {
        global $wpdb;
        if ($this->column_exists('reservation_group_id')) {
            return true;
        }
        $table = $wpdb->prefix . 'usctdp_activity';
        $result = $wpdb->query(
            "ALTER TABLE {$table}
             ADD COLUMN reservation_group_id bigint(20) unsigned NULL DEFAULT NULL,
             ADD KEY reservation_group_id (reservation_group_id)"
        );
        return $result !== false;
    }

    /**
     * Step 2/3: gives every activity that doesn't have one yet its own
     * dedicated reservation_group row, carrying over its current `capacity`
     * value - so, until an admin deliberately merges activities together
     * with `wp usctdp merge_reservation_group`, capacity checks behave
     * exactly as they did with a plain per-activity column.
     *
     * Batched and resumable rather than one giant query: this runs on
     * `init` (see Usctdp_Mgmt_Model::register_berlindb_entities()), so a
     * request timeout mid-run is plausible on a large table. Each
     * activity's INSERT+claim commits immediately (no wrapping
     * transaction), which is what makes `WHERE reservation_group_id IS
     * NULL` a correct resume point on the next request.
     *
     * The claim UPDATE is itself the concurrency guard: if two requests
     * race on the same activity, only one claim can succeed (the other
     * affects 0 rows, since the row is no longer NULL) and the loser
     * deletes its now-orphaned group insert instead of leaving a duplicate
     * behind.
     */
    public function backfill_reservation_groups()
    {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'usctdp_activity';
        $group_table = $wpdb->prefix . 'usctdp_reservation_group';
        $batch_size = 500;

        while (true) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, capacity FROM {$activity_table}
                 WHERE reservation_group_id IS NULL LIMIT %d",
                $batch_size
            ));
            if ($rows === false) {
                return false;
            }
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $capacity = $row->capacity !== null ? (int) $row->capacity : 0;
                $inserted = $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$group_table} (capacity, created_at, updated_at) VALUES (%d, NOW(), NOW())",
                    $capacity
                ));
                if ($inserted === false) {
                    return false;
                }
                $group_id = $wpdb->insert_id;

                $claimed = $wpdb->query($wpdb->prepare(
                    "UPDATE {$activity_table} SET reservation_group_id = %d
                     WHERE id = %d AND reservation_group_id IS NULL",
                    $group_id,
                    $row->id
                ));
                if ($claimed === false) {
                    return false;
                }
                if ($claimed === 0) {
                    // Lost the race to a concurrent request - its group
                    // insert wins, ours is now orphaned. Clean it up rather
                    // than leaving a dangling unreferenced group row.
                    $wpdb->query($wpdb->prepare("DELETE FROM {$group_table} WHERE id = %d", $group_id));
                }
            }
        }
        return true;
    }

    /**
     * Step 3/3: re-runs the backfill defensively (cheap no-op if step 2
     * already finished - see the IS NULL guard above), then locks in
     * NOT NULL and drops the now-unused capacity column in one ALTER. This
     * is a single-deploy WordPress plugin, not a rolling multi-instance
     * service - there's no older code left running against the DB once
     * this deploys, so there's no reason to keep `capacity` around as a
     * compatibility shim once every row has a reservation_group_id.
     */
    public function finalize_reservation_group_id_column()
    {
        if (!$this->backfill_reservation_groups()) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_activity';
        $result = $wpdb->query(
            "ALTER TABLE {$table}
             MODIFY reservation_group_id bigint(20) unsigned NOT NULL,
             DROP COLUMN capacity"
        );
        return $result !== false;
    }
}
