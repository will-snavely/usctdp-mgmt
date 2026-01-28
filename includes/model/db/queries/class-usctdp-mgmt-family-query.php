<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Family_Query extends Query
{
    protected $table_name = 'usctdp_family';
    protected $table_alias = 'ustf';
    protected $table_schema = 'Usctdp_Mgmt_Family_Schema';
    protected $item_name = 'family';
    protected $item_name_plural = 'families';
    protected $item_shape = 'Usctdp_Mgmt_Family_Row';

    public function search_families($query, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}{$this->table_name}";
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
        $sql .= $where_clause;
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;

        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }
}
