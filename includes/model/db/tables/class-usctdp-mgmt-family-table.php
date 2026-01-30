<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Family_Table extends Table
{
    public $name = 'usctdp_family';
    protected $db_version_key = 'usctdp_family_version';
    public $description = 'USCTDP Families';
    protected $version = '1.0.0';
    protected $upgrades = array();
    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            title tinytext NOT NULL,
            last tinytext NOT NULL,
            address tinytext,
            city tinytext,
            state tinytext,
            zip tinytext,
            phone_numbers JSON,
            email tinytext,
            notes text,
            last_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_modified_by bigint(20) unsigned,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            FULLTEXT idx_title (title)
        ";
    }
}
