<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Table extends Table
{
    public $name = 'usctdp_activity';
    protected $db_version_key = 'usctdp_activity_version';
    public $description = 'USCTDP Activities';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            type tinyint,
            title tinytext,
            search_term tinytext,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY product_id (product_id),
            FULLTEXT search (search_term)
        ";
    }
}
