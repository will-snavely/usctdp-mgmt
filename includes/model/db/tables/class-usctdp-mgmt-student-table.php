<?php

use BerlinDB\Database\Table;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Student_Table extends Table
{
    public $name = 'usctdp_student';
    protected $db_version_key = 'usctdp_student_version';
    public $description = 'USCTDP Students';
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'add_soft_delete_columns',
    ];

    public static function create_title($first, $last)
    {
        return sanitize_text_field($first . ' ' . $last);
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
            is_deleted tinyint(1) unsigned NOT NULL DEFAULT 0,
            deleted_at datetime NULL DEFAULT NULL,
            deleted_by bigint(20) unsigned NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY family_id (family_id),
            FULLTEXT search (search_term)
        ";
    }

    /**
     * Soft delete from the admin Families page (see ajax_delete_student) -
     * same reasoning and column_exists() guard as
     * Usctdp_Mgmt_Family_Table::add_soft_delete_columns().
     */
    public function add_soft_delete_columns()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_student';
        $columns = [
            'is_deleted' => 'tinyint(1) unsigned NOT NULL DEFAULT 0',
            'deleted_at' => 'datetime NULL DEFAULT NULL',
            'deleted_by' => 'bigint(20) unsigned NULL DEFAULT NULL',
        ];
        foreach ($columns as $name => $definition) {
            if ($this->column_exists($name)) {
                continue;
            }
            if ($wpdb->query("ALTER TABLE {$table} ADD COLUMN {$name} {$definition}") === false) {
                return false;
            }
        }
        return true;
    }
}
