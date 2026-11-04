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
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'add_soft_delete_columns',
    ];
    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            title tinytext NOT NULL,
            search_term tinytext,
            last tinytext NOT NULL,
            address tinytext,
            city tinytext,
            state tinytext,
            zip tinytext,
            phone_numbers JSON,
            emails JSON,
            notes text,
            is_deleted tinyint(1) unsigned NOT NULL DEFAULT 0,
            deleted_at datetime NULL DEFAULT NULL,
            deleted_by bigint(20) unsigned NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            FULLTEXT search (search_term)
        ";
    }

    /**
     * Soft delete from the admin Families page (see ajax_delete_family) -
     * the row and everything referencing it stays put, it's just hidden
     * from family search/listings. Each column is guarded by
     * column_exists() so a partially-applied earlier run is safe to resume,
     * same as Usctdp_Mgmt_Reservation_Group_Table::add_name_column().
     */
    public function add_soft_delete_columns()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_family';
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
