<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Payment_Query extends Query
{
    protected $table_name = 'usctdp_payment';
    protected $table_alias = 'upay';
    protected $table_schema = 'Usctdp_Mgmt_Payment_Schema';
    protected $item_name = 'payment';
    protected $item_name_plural = 'payments';
    protected $item_shape = 'Usctdp_Mgmt_Payment_Row';

    public function get_payment_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["registration_id"])) {
            $conditions[] = "pment.registration_id = %d";
            $where_args[] = $args['registration_id'];
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

        $query = $wpdb->prepare(
            "   SELECT *
                FROM {$wpdb->prefix}usctdp_payment AS pment
                {$where_clause}
                ORDER BY reg.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);
        $count_sql = "SELECT COUNT(*) as count FROM {$wpdb->prefix}usctdp_payment AS pment {$where_clause}";
        $count_query = $count_sql;
        if (!empty($where_args)) {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);
        return [
            'data' => $window,
            'count' => $count
        ];
    }

}
