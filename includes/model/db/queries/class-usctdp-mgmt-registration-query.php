<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Registration_Query extends Query
{
    protected $table_name = 'usctdp_registration';
    protected $table_alias = 'ureg';
    protected $table_schema = 'Usctdp_Mgmt_Registration_Schema';
    protected $item_name = 'registration';
    protected $item_name_plural = 'registrations';
    protected $item_shape = 'Usctdp_Mgmt_Registration_Row';

    public function get_class_registration_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        $token_suffix = Usctdp_Mgmt_Model::$token_suffix;
        if (isset($args["class_id"])) {
            $conditions[] = "reg.activity_id = %d";
            $where_args[] = $args['class_id'];
        }
        if (isset($args["student_id"])) {
            $conditions[] = "reg.student_id = %d";
            $where_args[] = $args['student_id'];
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
                    reg.id as registration_id,
                    reg.starting_level as registration_starting_level,
                    reg.credit as registration_credit,
                    reg.debit as registration_debit,
                    reg.notes as registration_notes,
                    stud.id as student_id,
                    stud.first as student_first,
                    stud.last as student_last,
                    stud.birth_date as student_birth_date,
                    TIMESTAMPDIFF(YEAR, stud.birth_date, CURDATE()) AS student_age,
                    cls.id as class_id,
                    cls.title as class_name,
                    sesh.title as session_name
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_activity AS cls ON reg.activity_id = cls.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON cls.session_id = sesh.id
                {$where_clause}
                ORDER BY reg.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);
        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_activity AS cls ON reg.activity_id = cls.id
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
