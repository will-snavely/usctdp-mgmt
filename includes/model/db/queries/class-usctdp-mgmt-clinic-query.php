<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Clinic_Query extends Query
{
    protected $table_name = 'usctdp_clinic';
    protected $table_alias = 'uclin';
    protected $table_schema = 'Usctdp_Mgmt_Clinic_Schema';
    protected $item_name = 'clinic';
    protected $item_name_plural = 'clinics';
    protected $item_shape = 'Usctdp_Mgmt_Clinic_Row';

    public function search_clinics($query, $session_id, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT * FROM";
        $sql .= " {$wpdb->prefix}usctdp_activity as act";
        $sql .= " JOIN {$wpdb->prefix}{$this->table_name} as uclin";
        $sql .= " ON act.id = uclin.activity_id";
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
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;

        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }

    public function get_clinic_data($args)
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
                    act.id as clinic_id, act.title as clinic_name, act.capacity as clinic_capacity,
                    act.level as clinic_level, act.notes as clinic_notes,
                    clin.day_of_week as clinic_day_of_week,
                    clin.start_time as clinic_start_time, clin.end_time as clinic_end_time,
                    sess.id as session_id, sess.title as session_name,
                    sess.start_date as session_start_date, sess.end_date as session_end_date,
                    sess.num_weeks as session_num_weeks, sess.category as session_category,
                    prod.title as clinic_name, prod.id as clinic_id
                FROM {$wpdb->prefix}usctdp_activity AS act
                JOIN {$wpdb->prefix}usctdp_clinic AS clin ON act.id = clin.activity_id
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
                JOIN {$wpdb->prefix}usctdp_clinic AS clin ON act.id = clin.activity_id
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
