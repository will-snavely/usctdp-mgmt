<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
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
            activity_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            status varchar(50) NOT NULL,
            priority smallint unsigned,
            created_at datetime,
            notified_at datetime,
            expires_at datetime,
            PRIMARY KEY (id),
            KEY activity_id (activity_id),
            KEY student_id (student_id)
        ";
    }
}
