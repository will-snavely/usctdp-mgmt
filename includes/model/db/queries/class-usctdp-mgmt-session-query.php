<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Session_Query extends Query
{
    protected $table_name = 'usctdp_session';
    protected $table_alias = 'usesh';
    protected $table_schema = 'Usctdp_Mgmt_Session_Schema';
    protected $item_name = 'session';
    protected $item_name_plural = 'sessions';
    protected $item_shape = 'Usctdp_Mgmt_Session_Row';

    public function get_active_session_rosters()
    {
        global $wpdb;
        $token_suffix = Usctdp_Mgmt_Model::$token_suffix;
        $session_roster_query = "   
            SELECT 
                sesh.id as id,
                REPLACE(sesh.title, '{$token_suffix}', '') as title,
                rst.drive_id as drive_id
            FROM {$wpdb->prefix}{$this->table_name} AS sesh
            LEFT JOIN {$wpdb->prefix}usctdp_roster_link as rst ON sesh.id = rst.entity_id
            WHERE sesh.is_active = 1
            ORDER BY sesh.title
        ";
        return $wpdb->get_results($session_roster_query);
    }

    public function search_sessions($query, $active = null, $category = null, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT id, title FROM {$wpdb->prefix}{$this->table_name}";
        $args = [];
        $conditions = [];
        if ($query) {
            $parts = preg_split("/\s+/", trim($query));
            $query_terms = [];
            foreach ($parts as $part) {
                $query_terms[] = "+$part*";
            }
            $conditions[] = "MATCH(title) AGAINST(%s IN BOOLEAN MODE)";
            $args[] = implode(" ", $query_terms);
        }
        if ($active !== null) {
            $conditions[] = "is_active = %d";
            $args[] = $active;
        }
        if ($category !== null) {
            $conditions[] = "category = %d";
            $args[] = $category;
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;
        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }
}
