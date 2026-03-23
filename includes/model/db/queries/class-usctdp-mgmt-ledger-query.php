<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Ledger_Query extends Query
{
    protected $table_name = 'usctdp_ledger';
    protected $table_alias = 'ulgr';
    protected $table_schema = 'Usctdp_Mgmt_Ledger_Schema';
    protected $item_name = 'ledger';
    protected $item_name_plural = 'ledger';
    protected $item_shape = 'Usctdp_Mgmt_Ledger_Row';

    public function get_ledger_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["id"])) {
            $conditions[] = "ulgr.id = %d";
            $where_args[] = $args['id'];
        }
        if (isset($args["family_id"])) {
            $conditions[] = "ulgr.family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["event_id"])) {
            $conditions[] = "ulgr.event_id = %d";
            $where_args[] = $args['event_id'];
        }
        if (isset($args["account"])) {
            $conditions[] = "ulgr.account = %s";
            $where_args[] = $args['account'];
        }
        if (isset($args["order_id"])) {
            $conditions[] = "ulgr.order_id = %d";
            $where_args[] = $args['order_id'];
        }
        if (isset($args["purchase_id"])) {
            $conditions[] = "ulgr.purchase_id = %d";
            $where_args[] = $args['purchase_id'];
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
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                {$where_clause}
                ORDER BY ulgr.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                {$where_clause}";
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
    public function get_ledger_events($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["family_id"])) {
            $conditions[] = "family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["purchase_id"])) {
            $conditions[] = "purchase_id = %d";
            $where_args[] = $args['purchase_id'];
        }
        if (isset($args["account"])) {
            $conditions[] = "account = %s";
            $where_args[] = $args['account'];
        }
        if ($conditions) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        $limit_clause = '';
        $limit_args = [];
        if (isset($args["number"]) && $args["number"] > 0) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];

            if (isset($args["offset"])) {
                $limit_clause .= " OFFSET %d";
                $limit_args[] = $args['offset'];
            }
        }

        $query = $wpdb->prepare(
            "SELECT 
                event_id,
                MAX(created_at) as event_date,
                MAX(event) as event_description,
                MAX(payment_method) as method,
                SUM(debit) as charge_amount,
                SUM(credit) as payment_amount,
                (SUM(debit) - SUM(credit)) as balance
            FROM {$wpdb->prefix}usctdp_ledger
            {$where_clause}
            GROUP BY event_id
            ORDER BY event_date ASC
            {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql =
            "SELECT count(event_id)
            FROM {$wpdb->prefix}usctdp_ledger
            {$where_clause}
            GROUP BY event_id";
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
