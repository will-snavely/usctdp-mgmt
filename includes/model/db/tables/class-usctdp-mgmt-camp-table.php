<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One usctdp_camp row per usctdp_activity (id shared, same 1:1 pattern as
 * usctdp_tournament) - the activity represents the whole camp offering
 * (e.g. "Junior 5.0/5.5 Tue/Thu Summer Camp"), not an individual day.
 * `schedule` lists every available week/day/date, the same JSON-blob-for-
 * display approach usctdp_tournament.schedule uses (see
 * Usctdp_Mgmt_Tournament_Table) - it's informational (product page display,
 * admin day picker), not the source of truth for who's signed up on which
 * day. That's usctdp_registration_camp_day, keyed off individual
 * registrations so one camp registration can cover any subset of days.
 */
class Usctdp_Mgmt_Camp_Table extends Table
{
    public $name = 'usctdp_camp';
    protected $db_version_key = 'usctdp_camp_version';
    public $description = 'USCTDP Camps';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL,
            schedule json default '{}',
            PRIMARY KEY (id)
        ";
    }
}
