<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Holds capacity for one or more usctdp_activity rows that share a physical
 * space/time slot (e.g. two clinics scheduled on the same court at the same
 * time). Most activities get their own dedicated 1:1 group - see
 * Usctdp_Mgmt_Activity_Table's reservation_group_id migration - so capacity
 * checks never need to special-case "solo" vs "shared" activities, only ever
 * "count/lock against this activity's group".
 */
class Usctdp_Mgmt_Reservation_Group_Table extends Table
{
    public $name = 'usctdp_reservation_group';
    protected $db_version_key = 'usctdp_reservation_group_version';
    public $description = 'USCTDP Reservation Groups';
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'add_name_column',
    ];

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            capacity smallint unsigned NOT NULL DEFAULT 0,
            name tinytext NULL DEFAULT NULL,
            created_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY (id)
        ";
    }

    /**
     * Lets a merged group's roster document be titled with something better
     * than a join of its member activities' titles - see
     * Usctdp_Mgmt_Reservation_Group_Query::get_roster_title(). Optional:
     * NULL for a still-unnamed group (the common case - most groups are a
     * solo activity's own dedicated 1:1 group and never need a name at
     * all), same as usctdp_roster_group.name.
     *
     * column_exists() guard: same resumability reasoning as
     * Usctdp_Mgmt_Activity_Table's migration steps - safe to re-run if an
     * earlier attempt was interrupted before the version could be recorded.
     */
    public function add_name_column()
    {
        global $wpdb;
        if ($this->column_exists('name')) {
            return true;
        }
        $table = $wpdb->prefix . 'usctdp_reservation_group';
        $result = $wpdb->query("ALTER TABLE {$table} ADD COLUMN name tinytext NULL DEFAULT NULL");
        return $result !== false;
    }
}
