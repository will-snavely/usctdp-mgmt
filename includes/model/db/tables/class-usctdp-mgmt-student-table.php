<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Student_Table extends Table
{
    public $name = 'usctdp_student';
    protected $db_version_key = 'usctdp_student_version';
    public $description = 'USCTDP Students';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public static function create_title($first, $last)
    {
        $sanitized = sanitize_text_field($first . ' ' . $last);
        return Usctdp_Mgmt_Model::append_token_suffix($sanitized);
    }

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            family_id bigint(20) unsigned NOT NULL,
            title tinytext NOT NULL,
            search_term tinytext,
            first tinytext NOT NULL,
            last tinytext NOT NULL,
            birth_date date,
            level tinytext,
            PRIMARY KEY (id),
            KEY family_id (family_id),
            FULLTEXT search (search_term)
        ";
    }
}
