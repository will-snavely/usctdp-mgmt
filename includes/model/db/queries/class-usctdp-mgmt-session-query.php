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
                sesh.title as title,
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
            $conditions[] = "MATCH(search_term) AGAINST(%s IN BOOLEAN MODE)";
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

    public function search_session_rosters($args)
    {
        global $wpdb;
        $token_suffix = Usctdp_Mgmt_Model::$token_suffix;
        $where_clause = '';
        $where_args = [];
        $conditions = [];

        if (isset($args["q"]) && !empty($args["q"])) {
            $query = $args['q'];
            $parts = preg_split("/\s+/", trim($query));
            $query_terms = [];
            foreach ($parts as $part) {
                $query_terms[] = "+$part*";
            }
            $conditions[] = "MATCH(search_term) AGAINST(%s IN BOOLEAN MODE)";
            $where_args[] = implode(" ", $query_terms);
        }

        if ($conditions) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        $limit_clause = '';
        $limit_args = [];
        if (isset($args["number"])) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];
        }
        if (isset($args["offset"])) {
            $limit_clause .= " OFFSET %d";
            $limit_args[] = $args['offset'];
        }

        $query = $wpdb->prepare("   
            SELECT 
                sesh.id as id,
                sesh.title as title,
                rst.drive_id as drive_id
            FROM {$wpdb->prefix}{$this->table_name} AS sesh
            LEFT JOIN {$wpdb->prefix}usctdp_roster_link as rst ON sesh.id = rst.entity_id
            {$where_clause}
            ORDER BY sesh.title
            {$limit_clause}
        ", array_merge($where_args, $limit_args));
        $window = $wpdb->get_results($query);

        $count_sql = "
            SELECT COUNT(*) as count
            FROM {$wpdb->prefix}{$this->table_name} AS sesh
            {$where_clause}";
        if(empty($where_args)) {
            $count_query = $count_sql;
        } else {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);
        return [
            'data' => $window,
            'count' => $count
        ];
    }
}
