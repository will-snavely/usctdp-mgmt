<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Family_Link_Table extends Table
{
    public $name = 'usctdp_family_link';
    protected $db_version_key = 'usctdp_family_link_version';
    public $description = 'USCTDP Family Links';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            family_id int(11) unsigned NOT NULL,
            student_id int(11) unsigned NOT NULL,
            PRIMARY KEY (id),
            KEY family_id (family_id),
            KEY student_id (student_id)
        ";
    }
}
