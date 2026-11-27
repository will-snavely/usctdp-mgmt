<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Which specific camp slots a single usctdp_registration covers. A camp
 * registration is one row in usctdp_registration (student + the camp
 * activity as a whole, same as any other activity type) - the particular
 * slots chosen (and how many) live here instead, so admins can add/drop a
 * day for a student without touching the registration itself, and per-day
 * roster generation is a plain indexed join on activity_date rather than
 * a JSON scan across every registration for the camp.
 *
 * A slot is (activity_date, start_time), not just a date: a camp can run
 * the same weekday more than once (e.g. Thursday 11 AM and Thursday 6 PM),
 * and each is booked independently.
 */
class Usctdp_Mgmt_Registration_Camp_Day_Table extends Table
{
    public $name = 'usctdp_registration_camp_day';
    protected $db_version_key = 'usctdp_registration_camp_day_version';
    public $description = 'USCTDP Registration Camp Days';
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'add_start_time_column',
    ];

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            registration_id bigint(20) unsigned NOT NULL,
            activity_date date NOT NULL,
            start_time time DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY registration_camp_slot (registration_id, activity_date, start_time),
            KEY activity_date (activity_date)
        ";
    }

    /**
     * Moves existing installations from one-row-per-date to
     * one-row-per-slot:
     *   1. add start_time (nullable),
     *   2. backfill it for existing rows from the camp's schedule,
     *   3. swap UNIQUE (registration_id, activity_date) for
     *      UNIQUE (registration_id, activity_date, start_time).
     *
     * Every step checks information_schema / current data first, so this
     * is a safe no-op whether dbDelta got there first, a previous run did,
     * or neither did (see add_status_created_at_index() in
     * Usctdp_Mgmt_Purchase_Table for the pattern).
     *
     * Backfill: before this, build_camp_week_grid() kept only the LAST
     * schedule entry for any weekday listed twice, so that's the slot the
     * admin was actually shown and picked - the same rule is applied here.
     * A row whose camp/schedule can't be resolved keeps a NULL start_time
     * rather than being guessed at or deleted.
     */
    public function add_start_time_column()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_registration_camp_day';

        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE table_schema = %s AND table_name = %s AND column_name = %s",
            DB_NAME,
            $table,
            'start_time'
        ));
        if (!$column_exists) {
            $result = $wpdb->query("ALTER TABLE {$table} ADD COLUMN start_time time DEFAULT NULL AFTER activity_date");
            if ($result === false) {
                return false;
            }
        }

        if (!$this->backfill_start_times($table)) {
            return false;
        }

        $old_key_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = %s AND table_name = %s AND index_name = %s",
            DB_NAME,
            $table,
            'registration_camp_day'
        ));
        if ($old_key_exists) {
            $result = $wpdb->query("ALTER TABLE {$table} DROP INDEX registration_camp_day");
            if ($result === false) {
                return false;
            }
        }

        $new_key_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE table_schema = %s AND table_name = %s AND index_name = %s",
            DB_NAME,
            $table,
            'registration_camp_slot'
        ));
        if (!$new_key_exists) {
            $result = $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY registration_camp_slot (registration_id, activity_date, start_time)");
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    private function backfill_start_times($table)
    {
        global $wpdb;
        $registration_table = $wpdb->prefix . 'usctdp_registration';
        $camp_table = $wpdb->prefix . 'usctdp_camp';

        $rows = $wpdb->get_results(
            "SELECT cd.id, cd.activity_date, camp.schedule
             FROM {$table} AS cd
             JOIN {$registration_table} AS reg ON reg.id = cd.registration_id
             LEFT JOIN {$camp_table} AS camp ON camp.id = reg.activity_id
             WHERE cd.start_time IS NULL"
        );
        if ($rows === null) {
            return false;
        }

        foreach ($rows as $row) {
            $schedule = json_decode($row->schedule ?? '', true);
            if (!is_array($schedule)) {
                continue;
            }
            $day_name = (new DateTime($row->activity_date))->format('l');
            $start_time = null;
            foreach ($schedule as $entry) {
                if (is_array($entry) && ($entry['day'] ?? null) === $day_name) {
                    $parsed = Usctdp_Mgmt_Registration_Camp_Day_Query::normalize_start_time($entry['start_time'] ?? null);
                    if ($parsed !== null) {
                        $start_time = $parsed; // last match wins - see doc comment
                    }
                }
            }
            if ($start_time === null) {
                continue;
            }
            $result = $wpdb->update($table, ['start_time' => $start_time], ['id' => (int) $row->id], ['%s'], ['%d']);
            if ($result === false) {
                return false;
            }
        }
        return true;
    }
}
