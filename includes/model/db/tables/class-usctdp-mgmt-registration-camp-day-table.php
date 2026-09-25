<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Which specific camp days a single usctdp_registration covers. A camp
 * registration is one row in usctdp_registration (student + the camp
 * activity as a whole, same as any other activity type) - the particular
 * days chosen (and how many) live here instead, so admins can add/drop a
 * day for a student without touching the registration itself, and per-day
 * roster generation is a plain indexed join on activity_date rather than
 * a JSON scan across every registration for the camp.
 */
class Usctdp_Mgmt_Registration_Camp_Day_Table extends Table
{
    public $name = 'usctdp_registration_camp_day';
    protected $db_version_key = 'usctdp_registration_camp_day_version';
    public $description = 'USCTDP Registration Camp Days';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            registration_id bigint(20) unsigned NOT NULL,
            activity_date date NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY registration_camp_day (registration_id, activity_date),
            KEY activity_date (activity_date)
        ";
    }
}
