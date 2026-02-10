<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Student_Query extends Query
{
    protected $table_name = 'usctdp_student';
    protected $table_alias = 'ustu';
    protected $table_schema = 'Usctdp_Mgmt_Student_Schema';
    protected $item_name = 'student';
    protected $item_name_plural = 'students';
    protected $item_shape = 'Usctdp_Mgmt_Student_Row';

    public function create_student($first, $last, $family_id, $birthdate, $level)
    {
        $title = Usctdp_Mgmt_Student_Table::create_title($first, $last);
        $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
        $args = [
            'first' => $first,
            'last' => $last,
            'title' => $title,
            'search_term' => $search_term,
            'family_id' => $family_id,
            'birth_date' => $birthdate,
            'level' => $level,
        ];
        return $this->add_item($args);
    }
    public function search_students($query, $family_id, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}{$this->table_name}";
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
        if ($family_id !== null) {
            $conditions[] = "family_id = %d";
            $args[] = $family_id;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;

        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }

    public function get_student_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["id"])) {
            $conditions[] = "stud.id = %d";
            $where_args[] = $args['id'];
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
                    stud.id as student_id, stud.title as student_name,
                    stud.first as student_first, stud.last as student_last,
                    stud.birth_date as student_birth_date,
                    stud.level as student_level,
                    fam.id as family_id,
                    fam.title as family_name
                FROM {$wpdb->prefix}usctdp_student AS stud
                JOIN {$wpdb->prefix}usctdp_family AS fam ON stud.family_id = fam.id
                {$where_clause}
                ORDER BY stud.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);
        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_student AS stud
                JOIN {$wpdb->prefix}usctdp_family AS fam ON stud.family_id = fam.id
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
