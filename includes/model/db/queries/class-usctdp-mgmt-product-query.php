<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Product_Query extends Query
{
    protected $table_name = 'usctdp_product';
    protected $table_alias = 'uprod';
    protected $table_schema = 'Usctdp_Mgmt_Product_Schema';
    protected $item_name = 'product';
    protected $item_name_plural = 'products';
    protected $item_shape = 'Usctdp_Mgmt_Product_Row';

    public function search_products($query, $type = null, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}{$this->table_name}";
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
        if ($type !== null) {
            $conditions[] = "type = %s";
            $args[] = $type;
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;
        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }

    public function get_product_pricing($session_id, $product_id, $product_code)
    {
        global $wpdb;

        $where_args = [];
        $join_args = [];
        $conditions = [];
        $join_clause = "";

        if ($product_id !== null) {
            $conditions[] = "product_id = %d";
            $where_args[] = $product_id;
        }
        if ($product_code !== null) {
            $conditions[] = "code = %s";
            $where_args[] = $product_code;
        }
        if ($session_id !== null) {
            $join_clause = "JOIN {$wpdb->prefix}usctdp_pricing as price 
                            ON prod.id = price.product_id 
                            AND price.session_id = %d";
            $join_args[] = $session_id;
        } else {
            $join_clause = "JOIN {$wpdb->prefix}usctdp_pricing as price 
                            ON prod.id = price.product_id";
        }

        if ($conditions) {
            $where_clause = " WHERE " . implode(" AND ", $conditions);
        }

        $sql = "SELECT prod.id as product_id, price.pricing as pricing
                FROM {$wpdb->prefix}{$this->table_name} as prod
                {$join_clause} {$where_clause} LIMIT 1";

        $query = $wpdb->prepare($sql, array_merge($join_args, $where_args));
        return $wpdb->get_row($query);
    }
}
