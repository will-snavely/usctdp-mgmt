<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Waitlist_Table extends Table
{
    public $name = 'usctdp_waitlist';
    protected $db_version_key = 'usctdp_waitlist_version';
    public $description = 'USCTDP Waitlist';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            registration_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            priority smallint unsigned NOT NULL,
            status tinyint unsigned NOT NULL,
            created_at datetime,
            notified_at datetime,
            expires_at datetime,
            PRIMARY KEY (id),
            KEY registration_id (registration_id),
            KEY student_id (student_id)
        ";
    }
}
