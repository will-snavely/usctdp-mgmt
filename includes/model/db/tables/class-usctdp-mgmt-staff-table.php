<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Staff_Table extends Table
{
    public $name = 'usctdp_staff';
    protected $db_version_key = 'usctdp_staff_version';
    public $description = 'USCTDP Staff';

    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(255) NOT NULL,
            wp_user_id bigint(20) unsigned NOT NULL,
            image_id bigint(20) unsigned,
            display_order tinyint unsigned DEFAULT 10,
            first_name varchar(255) NOT NULL,
            last_name varchar(255) NOT NULL,
            title tinytext,
            search_term tinytext,
            biography text,
            quote text,
            roles json,
            faq json,   
            PRIMARY KEY (id),
            INDEX slug (slug),
            FULLTEXT search (search_term)
        ";
    }
}
