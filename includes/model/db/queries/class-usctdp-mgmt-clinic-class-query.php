<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Clinic_Class_Query extends Query
{
    protected $table_name = 'usctdp_clinic_class';
    protected $table_alias = 'ucc';
    protected $table_schema = 'Usctdp_Mgmt_Clinic_Class_Schema';
    protected $item_name = 'clinic_class';
    protected $item_name_plural = 'clinic_classes';
    protected $item_shape = 'Usctdp_Mgmt_Clinic_Class_Row';

    public function get_class_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["id"])) {
            $conditions[] = "cls.id = %d";
            $where_args[] = $args['id'];
        }
        if (isset($args["session_id"])) {
            $conditions[] = "sess.id = %d";
            $where_args[] = $args['session_id'];
        }
        if (isset($args["clinic_id"])) {
            $conditions[] = "post.ID = %d";
            $where_args[] = $args['clinic_id'];
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

        $token_suffix = Usctdp_Mgmt_Model::$token_suffix;
        $query = $wpdb->prepare(
            "   SELECT 
                    cls.id as class_id, cls.title as class_name, cls.day_of_week as class_day_of_week,
                    cls.start_time as class_start_time, cls.end_time as class_end_time,
                    cls.capacity as class_capacity, cls.level as class_level,
                    cls.notes as class_notes,
                    sess.id as session_id, 
                    REPLACE(sess.title, '{$token_suffix}', '') as session_name,
                    sess.start_date as session_start_date, sess.end_date as session_end_date,
                    sess.num_weeks as session_num_weeks, sess.category as session_category,
                    post.post_title as clinic_name, post.ID as clinic_id
                FROM {$wpdb->prefix}usctdp_clinic_class AS cls 
                JOIN {$wpdb->prefix}usctdp_session AS sess ON cls.session_id = sess.id
                JOIN {$wpdb->prefix}posts AS post ON cls.clinic_id = post.ID 
                {$where_clause}
                ORDER BY cls.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_query = $wpdb->prepare(
            "   SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_clinic_class AS cls 
                JOIN {$wpdb->prefix}usctdp_session AS sess ON cls.session_id = sess.id
                JOIN {$wpdb->prefix}posts AS post ON cls.clinic_id = post.ID 
                {$where_clause}",
            $where_args
        );
        $count = $wpdb->get_row($count_query);
        return [
            'data' => $window,
            'count' => $count->count
        ];
    }
}
