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
    protected $version = '1.0.0';
    protected $upgrades = array();
    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
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
            invited_at datetime,
            confirmed_at datetime,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ";
    }
}
