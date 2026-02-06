<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
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
        if ($type !== null) {
            $conditions[] = "type = %d";
            $args[] = $type->value;
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
