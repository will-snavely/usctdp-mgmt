<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Merchandise_Table extends Table
{
    public $name = 'usctdp_merchandise';
    protected $db_version_key = 'usctdp_merchandise_version';
    public $description = 'USCTDP Merchandise';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned default NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY student_id (student_id)
        ";
    }
}
