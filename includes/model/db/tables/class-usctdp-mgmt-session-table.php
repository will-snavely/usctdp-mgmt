<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Session_Table extends Table
{
    public $name = 'usctdp_session';
    protected $db_version_key = 'usctdp_session_version';
    public $description = 'USCTDP Sessions';
    protected $version = '1.0.0';
    protected $upgrades = array();

    public static function create_title(
        $name,
        $length_weeks,
        $start_date,
        $end_date
    ) {
        $start = $start_date->format('Y');
        $end = $end_date->format('Y');
        $year = $start;
        if ($start != $end) {
            $year = $start . '/' . $end;
        }
        return sanitize_text_field($name . ' - ' . $year);
    }

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title tinytext,
            search_term tinytext,
            is_active bool,
            start_date date,
            end_date date,
            num_weeks tinyint unsigned,
            category tinyint unsigned,
            PRIMARY KEY (id),
            INDEX title_prefix (title(10)),
            INDEX idx_start_date (start_date),
            INDEX idx_active_items (is_active),
            FULLTEXT search (search_term)
        ";
    }
}
