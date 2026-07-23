<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Tournament_Query extends Query
{
    protected $table_name = 'usctdp_tournament';
protected $table_alias = 'utorn';
    protected $table_schema = 'Usctdp_Mgmt_Tournament_Schema';
    protected $item_name = 'tournament';
    protected $item_name_plural = 'tournaments';
    protected $item_shape = 'Usctdp_Mgmt_Tournament_Row';

    public function get_tournament_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["id"])) {
            $conditions[] = "act.id = %d";
            $where_args[] = $args['id'];
        }
        if (isset($args["session_id"])) {
            $conditions[] = "sess.id = %d";
            $where_args[] = $args['session_id'];
        }
        if (isset($args["product_id"])) {
            $conditions[] = "prod.id = %d";
            $where_args[] = $args['product_id'];
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
            "   SELECT
                    act.id as activity_id, act.title as activity_name, act.capacity as activity_capacity,
                    act.level as activity_level,
                    torn.start_date as tournament_start_date, torn.start_date_addtl as tournament_start_date_addtl,
                    sess.id as session_id, sess.title as session_name,
                    sess.start_date as session_start_date, sess.end_date as session_end_date,
                    sess.num_weeks as session_num_weeks, sess.category as session_category,
                    prod.title as product_name, prod.id as product_id, prod.age_group as product_age_group
                FROM {$wpdb->prefix}usctdp_activity AS act
                JOIN {$wpdb->prefix}usctdp_tournament AS torn ON act.id = torn.id
                JOIN {$wpdb->prefix}usctdp_session AS sess ON act.session_id = sess.id
                JOIN {$wpdb->prefix}usctdp_product AS prod ON act.product_id = prod.id
                {$where_clause}
                ORDER BY act.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_activity AS act
                JOIN {$wpdb->prefix}usctdp_tournament AS torn ON act.id = torn.id
                JOIN {$wpdb->prefix}usctdp_session AS sess ON act.session_id = sess.id
                JOIN {$wpdb->prefix}usctdp_product AS prod ON act.product_id = prod.id
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
}
