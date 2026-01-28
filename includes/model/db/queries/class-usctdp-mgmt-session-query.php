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

    public function search_sessions($query, $is_active = null, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT id, title FROM {$wpdb->prefix}{$this->table_name}";
        $where_clause = "";
        $args = [];
        if ($query) {
            $parts = preg_split("/\s+/", trim($query));
            $query_terms = [];
            foreach ($parts as $part) {
                $query_terms[] = "+$part*";
            }
            $where_clause .= " WHERE MATCH(title) AGAINST(%s IN BOOLEAN MODE)";
            $args[] = implode(" ", $query_terms);
        }
        if ($is_active) {
            if ($where_clause) {
                $where_clause = " AND is_active = %d";
            } else {
                $where_clause = " WHERE is_active = %d";
            }
            $args[] = $is_active;
        }
        $sql .= $where_clause;
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;

        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }
}
