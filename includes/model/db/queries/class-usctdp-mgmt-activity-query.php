<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Activity_Query extends Query
{
    protected $table_name = 'usctdp_activity';
    protected $table_alias = 'uact';
    protected $table_schema = 'Usctdp_Mgmt_Activity_Schema';
    protected $item_name = 'activity';
    protected $item_name_plural = 'activities';
    protected $item_shape = 'Usctdp_Mgmt_Activity_Row';

    /**
     * Everything Usctdp_Mgmt_Docgen::generate_roster_for_sessions() needs to
     * order a combined multi-session roster document, for every activity
     * across the given sessions, in one query - one row per activity, not
     * per session, so this stays O(1) queries regardless of how many
     * sessions are combined.
     *
     * day_of_week/start_time come from a LEFT JOIN to usctdp_clinic (NULL
     * for a tournament - see the docgen's tournament-ordering handling,
     * which doesn't try to sort by these). activity_meta is the activity's
     * own raw meta JSON, so the caller can check its "segregate_roster" flag
     * (see Usctdp_Mgmt_Docgen::generate_roster_for_sessions()) without a
     * second lookup - same ad hoc meta-key convention as this same column's
     * "session_title" key (see
     * Usctdp_Mgmt_Docgen::derive_clinic_roster_fields()). Deliberately
     * per-activity, not per-session - a client can want just one clinic
     * (e.g. "Tiny Tots") pulled out of a session's normal day/time flow,
     * not the whole session.
     */
    public function get_roster_ordering_data(array $session_ids)
    {
        global $wpdb;
        if (empty($session_ids)) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($session_ids), '%d'));
        $query = $wpdb->prepare(
            "   SELECT
                    act.id as id,
                    act.type as type,
                    act.session_id as session_id,
                    act.reservation_group_id as reservation_group_id,
                    act.meta as activity_meta,
                    clin.day_of_week as day_of_week,
                    clin.start_time as start_time
                FROM {$wpdb->prefix}usctdp_activity AS act
                LEFT JOIN {$wpdb->prefix}usctdp_clinic AS clin ON clin.id = act.id AND act.type = 'clinic'
                WHERE act.session_id IN ($placeholders)
                ORDER BY act.primary_sort_order ASC, act.secondary_sort_order ASC",
            $session_ids
        );
        return $wpdb->get_results($query);
    }

    public function search_activities($query, $session_id, $product_id, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT * FROM";
        $sql .= " {$wpdb->prefix}usctdp_activity as act";
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
        if ($product_id !== null) {
            $conditions[] = "product_id = %d";
            $args[] = $product_id;
        }
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY primary_sort_order ASC, secondary_sort_order ASC LIMIT %d";
        $args[] = $limit;

        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }

    public function get_activity_data($args)
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
                    act.id as activity_id, act.title as activity_name, act.type as activity_type,
                    resg.capacity as activity_capacity, act.level as activity_level,
                    sess.id as session_id, sess.title as session_name,
                    sess.start_date as session_start_date, sess.end_date as session_end_date,
                    sess.num_weeks as session_num_weeks, sess.category as session_category,
                    prod.title as product_name, prod.id as product_id, prod.woocommerce_id as product_woocommerce_id
                FROM {$wpdb->prefix}usctdp_activity AS act
                JOIN {$wpdb->prefix}usctdp_reservation_group AS resg ON act.reservation_group_id = resg.id
                JOIN {$wpdb->prefix}usctdp_session AS sess ON act.session_id = sess.id
                JOIN {$wpdb->prefix}usctdp_product AS prod ON act.product_id = prod.id
                {$where_clause}
                ORDER BY act.primary_sort_order ASC, act.secondary_sort_order ASC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_activity AS act
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
