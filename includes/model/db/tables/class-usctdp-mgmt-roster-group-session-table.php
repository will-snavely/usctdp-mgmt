<?php

use BerlinDB\Database\Table;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Roster_Group_Session_Table extends Table
{
    public $name = 'usctdp_roster_group_session';
    protected $db_version_key = 'usctdp_roster_group_session_version';
    public $description = 'USCTDP Roster Group Sessions';
    protected $version = '1.1.0';
    protected $upgrades = [
        '1.1.0' => 'allow_multiple_groups_per_session',
    ];

    public function set_schema()
    {
        $this->schema = "
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            roster_group_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned NOT NULL,
            created_at datetime NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY roster_group_session (roster_group_id, session_id),
            KEY session_id (session_id)
        ";
    }

    /**
     * A session could only ever belong to one roster group, enforced by a
     * UNIQUE KEY on session_id alone - see Usctdp_Mgmt_Roster_Group_Query::
     * add_session(). Rosters can now share sessions, so that's replaced with
     * a UNIQUE key on the (roster_group_id, session_id) pair instead: it
     * still stops the same session being inserted into the same group twice,
     * just no longer stops it from being in a second group.
     *
     * $wpdb->query() returns 0 (not true) for a successful ALTER with no rows
     * to report, and 0 is empty() - so this must return an explicit bool
     * rather than the raw query result, or Table::is_success() would treat a
     * successful ALTER as a failed upgrade.
     */
    public function allow_multiple_groups_per_session()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'usctdp_roster_group_session';
        $result = $wpdb->query("
            ALTER TABLE {$table}
            DROP INDEX session_id,
            ADD UNIQUE KEY roster_group_session (roster_group_id, session_id),
            ADD KEY session_id (session_id)
        ");
        return $result !== false;
    }
}
