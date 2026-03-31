<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Purchase_Query extends Query
{
    protected $table_name = 'usctdp_purchase';
    protected $table_alias = 'upur';
    protected $table_schema = 'Usctdp_Mgmt_Purchase_Schema';
    protected $item_name = 'purchase';
    protected $item_name_plural = 'purchases';
    protected $item_shape = 'Usctdp_Mgmt_Purchase_Row';

    public function get_purchase_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];

        if (isset($args["activity_id"])) {
            $conditions[] = "reg.activity_id = %d";
            $where_args[] = $args['activity_id'];
        }
        if (isset($args["session_id"])) {
            $conditions[] = "sesh.id = %d";
            $where_args[] = $args['session_id'];
        }
        if (isset($args["product_id"])) {
            $conditions[] = "pur.product_id = %d";
            $where_args[] = $args['product_id'];
        }
        if (isset($args["family_id"])) {
            $conditions[] = "pur.family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["student_id"])) {
            $conditions[] = "pur.student_id = %d";
            $where_args[] = $args['student_id'];
        }
        if (isset($args["owes"])) {
            $conditions[] = "ledger.total_debit > ledger.total_credit";
        }
        if (isset($args["type"])) {
            $conditions[] = "pur.type = %s";
            $where_args[] = $args['type'];
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
                    pur.id as purchase_id, pur.type as purchase_type,
                    pur.product_id as purchase_product_id,
                    pur.created_at as purchase_created_at,
                    pur.created_by as purchase_created_by,
                    pur.notes as purchase_notes,
                    prod.title as product_title, prod.id as product_id,
                    ledger.total_debit as total_debit,
                    ledger.total_credit as total_credit,
                    stud.id as student_id, stud.family_id as family_id,
                    stud.first as student_first,
                    stud.last as student_last,
                    stud.birth_date as student_birth_date,
                    TIMESTAMPDIFF(YEAR, stud.birth_date, CURDATE()) AS student_age,
                    act.id as activity_id,
                    act.title as activity_name,
                    sesh.title as session_name,
                    sesh.id as session_id,
                    reg.id as registration_id,
                    reg.student_level as registration_student_level
                FROM {$wpdb->prefix}usctdp_purchase AS pur
                JOIN {$wpdb->prefix}usctdp_student AS stud ON pur.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_product AS prod ON pur.product_id = prod.id
                LEFT JOIN {$wpdb->prefix}usctdp_registration AS reg ON pur.id = reg.purchase_id
                LEFT JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
                LEFT JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                LEFT JOIN (
                    SELECT 
                        purchase_id,
                        SUM(debit) as total_debit,
                        SUM(credit) as total_credit
                    FROM {$wpdb->prefix}usctdp_ledger
                    WHERE account IN ('registration_fees', 'merchandise_fees')
                    GROUP BY purchase_id
                ) AS ledger ON ledger.purchase_id = pur.id
                {$where_clause}
                ORDER BY pur.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_purchase AS pur
                LEFT JOIN {$wpdb->prefix}usctdp_registration AS reg ON pur.id = reg.purchase_id
                LEFT JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                LEFT JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
                LEFT JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                LEFT JOIN (
                    SELECT 
                        purchase_id,
                        SUM(debit) as total_debit,
                        SUM(credit) as total_credit
                    FROM {$wpdb->prefix}usctdp_ledger
                    WHERE account IN ('registration_fees', 'merchandise_fees')
                    GROUP BY purchase_id
                ) AS ledger ON ledger.purchase_id = pur.id
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
