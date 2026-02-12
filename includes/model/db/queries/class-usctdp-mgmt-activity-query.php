<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Query extends Query
{
    protected $table_name = 'usctdp_activity';
    protected $table_alias = 'uact';
    protected $table_schema = 'Usctdp_Mgmt_Activity_Schema';
    protected $item_name = 'activity';
    protected $item_name_plural = 'activities';
    protected $item_shape = 'Usctdp_Mgmt_Activity_Row';

    public function search_activities($query, $session_id, $product_id, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT * FROM";
        $sql .= " {$wpdb->prefix}usctdp_activity as act"; 
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
        if ($session_id !== null) {
            $conditions[] = "session_id = %d";
            $args[] = $session_id;
        }
        if ($product_id !== null) {
            $conditions[] = "product_id = %d";
            $args[] = $product_id;
        }
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;

        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }
}
