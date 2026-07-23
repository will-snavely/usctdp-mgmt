<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Link_Table extends Table
{
    public $name = 'usctdp_roster_link';
    protected $db_version_key = 'usctdp_roster_link_version';
    public $description = 'USCTDP Roster Links';
    protected $version = '1.0.1';
    protected $upgrades = array(
        '1.0.1' => 'upgrade_1_0_1',
    );

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            drive_id TINYTEXT NOT NULL,
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY entity_id (entity_id)
        ";
    }

    public function upgrade_1_0_1()
    {
        $db = $this->get_db();
        if (empty($db)) {
            return false;
        }
        $result = $db->query("ALTER TABLE {$this->table_name} ADD COLUMN updated_at DATETIME NULL DEFAULT NULL");
        return $this->is_success($result);
    }
}
