<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Waitlist_Query extends Query
{
    protected $table_name = 'usctdp_waitlist';
    protected $table_alias = 'uwait';
    protected $table_schema = 'Usctdp_Mgmt_Waitlist_Schema';
    protected $item_name = 'waitlist';
    protected $item_name_plural = 'waitlists';
    protected $item_shape = 'Usctdp_Mgmt_Waitlist_Row';

    public function get_waitlist_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];

        if (isset($args["activity_id"])) {
            $conditions[] = "wl.activity_id = %d";
            $where_args[] = $args['activity_id'];
        }
        if (isset($args["student_id"])) {
            $conditions[] = "wl.student_id = %d";
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
                    wl.id as waitlist_id,
                    wl.priority as waitlist_priority,
                    wl.status as waitlist_status,
                    DATE_FORMAT(wl.created_at, '%%Y-%%m-%%dT%%T.%%fZ') as waitlist_created_at,
                    DATE_FORMAT(wl.notified_at, '%%Y-%%m-%%dT%%T.%%fZ') as waitlist_notified_at,
                    DATE_FORMAT(wl.expires_at, '%%Y-%%m-%%dT%%T.%%fZ') as waitlist_expires_at,
                    stud.id as student_id, stud.family_id as family_id,
                    stud.first as student_first,
                    stud.last as student_last,
                    stud.birth_date as student_birth_date,
                    TIMESTAMPDIFF(YEAR, stud.birth_date, CURDATE()) AS student_age,
                    act.id as activity_id,
                    act.title as activity_name,
                    sesh.title as session_name,
                    sesh.id as session_id
                FROM {$wpdb->prefix}usctdp_waitlist AS wl
                JOIN {$wpdb->prefix}usctdp_student AS stud ON wl.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON wl.activity_id = act.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                {$where_clause}
                ORDER BY wl.created_at ASC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);
        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_waitlist AS wl
                JOIN {$wpdb->prefix}usctdp_student AS stud ON wl.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON wl.activity_id = act.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
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
