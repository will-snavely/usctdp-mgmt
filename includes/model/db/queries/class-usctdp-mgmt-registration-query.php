<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
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

    /**
     * Everything Usctdp_Mgmt_Docgen::add_attendance_table() needs to print
     * an activity's roster, in one query - student + family (for phone
     * numbers) joined directly onto the registration, rather than issuing
     * two extra one-off queries per registrant. phone_numbers comes back as
     * a raw JSON string (same as the column itself); the caller decodes it,
     * same as Usctdp_Mgmt_Family_Row does.
     *
     * Restricted to status = 'active' - matching every other roster-facing
     * query in this codebase (e.g. the Activities page's own roster
     * datatable) - so a voided/removed registration, or one still pending
     * checkout, doesn't print. The code this replaced queried every
     * registration regardless of status, which was a real (if narrow) bug:
     * a voided registrant would still show up on the printed roster.
     *
     * $activity_id accepts a single id or an array of ids - the latter is
     * what a merged reservation group's combined roster block passes (see
     * Usctdp_Mgmt_Docgen::add_merged_clinic_roster_block()), to list every
     * registrant across all of the group's clinics in one table.
     *
     * Ordered by student last/first name, alphabetically - the attendance
     * table's own numbering (see Usctdp_Mgmt_Docgen::format_attendance_
     * number()) is purely positional, so this is the only thing that
     * actually determines print order.
     */
    public function get_roster_students($activity_id)
    {
        global $wpdb;
        $ids = is_array($activity_id) ? $activity_id : [$activity_id];
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $query = $wpdb->prepare(
            "   SELECT
                    reg.student_level as student_level,
                    stud.first as student_first,
                    stud.last as student_last,
                    TIMESTAMPDIFF(YEAR, stud.birth_date, CURDATE()) as student_age,
                    fam.phone_numbers as family_phone_numbers
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_family AS fam ON stud.family_id = fam.id
                WHERE reg.activity_id IN ($placeholders) AND reg.status = 'active'
                ORDER BY stud.last, stud.first",
            $ids
        );
        return $wpdb->get_results($query);
    }

    public function get_registration_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];

        if (isset($args["registration_ids"])) {
            $ids = $args["registration_ids"];
            $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
            $conditions[] = "reg.id IN ($placeholders)";
            $where_args = array_merge($where_args, $ids);
        }
        if (isset($args["activity_ids"]) && !empty($args["activity_ids"])) {
            $ids = $args["activity_ids"];
            $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
            $conditions[] = "reg.activity_id IN ($placeholders)";
            $where_args = array_merge($where_args, $ids);
        } elseif (isset($args["activity_id"])) {
            $conditions[] = "reg.activity_id = %d";
            $where_args[] = $args['activity_id'];
        }
        if (isset($args["session_id"])) {
            $conditions[] = "sesh.id = %d";
            $where_args[] = $args['session_id'];
        }
        if (isset($args["product_id"])) {
            $conditions[] = "reg.product_id = %d";
            $where_args[] = $args['product_id'];
        }
        if (isset($args["family_id"])) {
            $conditions[] = "stud.family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["student_id"])) {
            $conditions[] = "reg.student_id = %d";
            $where_args[] = $args['student_id'];
        }
        if (isset($args["status"])) {
            $conditions[] = "reg.status = %s";
            $where_args[] = $args['status'];
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
                    reg.id as registration_id, reg.status as registration_status,
                    reg.student_level as registration_student_level,
                    reg.notes as registration_notes,
                    stud.id as student_id, stud.family_id as family_id,
                    stud.first as student_first,
                    stud.last as student_last,
                    stud.birth_date as student_birth_date,
                    TIMESTAMPDIFF(YEAR, stud.birth_date, CURDATE()) AS student_age,
                    act.id as activity_id,
                    act.title as activity_name,
                    sesh.title as session_name,
                    sesh.id as session_id
                FROM {$wpdb->prefix}usctdp_purchase AS pur
                JOIN {$wpdb->prefix}usctdp_registration AS reg ON pur.id = reg.purchase_id
                JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                {$where_clause}
                ORDER BY reg.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);
        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
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
