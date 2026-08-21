<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Session_Query extends Query
{
    protected $table_name = 'usctdp_session';
    protected $table_alias = 'usesh';
    protected $table_schema = 'Usctdp_Mgmt_Session_Schema';
    protected $item_name = 'session';
    protected $item_name_plural = 'sessions';
    protected $item_shape = 'Usctdp_Mgmt_Session_Row';

    public function search_sessions($query, $active = null, $category = null, $limit = 10, $exclude_roster_group_id = null)
    {
        global $wpdb;
        $sql = "SELECT id, title, category FROM {$wpdb->prefix}{$this->table_name}";
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
        if ($active !== null) {
            // $active is still the boolean "admin-visible" filter every
            // caller of this method passes (Rosters' add-session picker,
            // etc.) - status collapses the old is_active flag together with
            // on-sale state, but "visible to admin" is just "not archived"
            // (scheduled and on_sale both still show up in these dropdowns).
            $conditions[] = $active ? "status != %s" : "status = %s";
            $args[] = 'archived';
        }
        if ($category !== null) {
            $conditions[] = "category = %d";
            $args[] = $category;
        }
        if ($exclude_roster_group_id) {
            // A session can belong to more than one roster group now, so
            // this only keeps the "add session" picker from offering
            // sessions already in the specific group being edited - not
            // sessions that merely belong to some other group.
            $conditions[] = "id NOT IN (SELECT session_id FROM {$wpdb->prefix}usctdp_roster_group_session WHERE roster_group_id = %d)";
            $args[] = $exclude_roster_group_id;
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY title ASC LIMIT %d";
        $args[] = $limit;
        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }

    /**
     * Paginated, searchable session listing for the Sessions admin page -
     * full row data (not just id/title/category like search_sessions())
     * plus the total count DataTables server-side paging needs. Raw SQL
     * rather than the BerlinDB item_shape path so dates come back as plain
     * 'Y-m-d' strings the caller can format, matching search_session_rosters()
     * below.
     *
     * Unlike search_sessions()'s boolean $active, $args['status'] exposes
     * the full lifecycle directly - this listing IS the status control
     * surface, so it needs to filter/display all three states, not just
     * collapse scheduled+on_sale into "visible". Accepts a single status
     * ('scheduled' | 'on_sale' | 'archived'), the composite 'not_archived'
     * (scheduled + on_sale - the default "hide old sessions" view), or '' /
     * omitted for no filter at all.
     */
    public function search_sessions_table($args)
    {
        global $wpdb;
        $where_clause = '';
        $where_args = [];
        $conditions = [];

        if (isset($args["q"]) && !empty($args["q"])) {
            $query = $args['q'];
            $parts = preg_split("/\s+/", trim($query));
            $query_terms = [];
            foreach ($parts as $part) {
                $query_terms[] = "+$part*";
            }
            $conditions[] = "MATCH(search_term) AGAINST(%s IN BOOLEAN MODE)";
            $where_args[] = implode(" ", $query_terms);
        }

        if (isset($args['status']) && $args['status'] !== '') {
            if ($args['status'] === 'not_archived') {
                $conditions[] = "status != %s";
                $where_args[] = 'archived';
            } else {
                $conditions[] = "status = %s";
                $where_args[] = $args['status'];
            }
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

        $query = $wpdb->prepare("
            SELECT id, title, start_date, end_date, num_weeks, status
            FROM {$wpdb->prefix}{$this->table_name}
            {$where_clause}
            ORDER BY FIELD(status, 'on_sale', 'scheduled', 'archived'), start_date ASC, title ASC
            {$limit_clause}
        ", array_merge($where_args, $limit_args));
        $window = $wpdb->get_results($query);

        $count_sql = "SELECT COUNT(*) as count FROM {$wpdb->prefix}{$this->table_name} {$where_clause}";
        if (empty($where_args)) {
            $count_query = $count_sql;
        } else {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);
        return [
            'data' => $window,
            'count' => $count
        ];
    }

    public function search_session_rosters($args)
    {
        global $wpdb;
        $token_suffix = Usctdp_Mgmt_Model::$token_suffix;
        $where_clause = '';
        $where_args = [];
        $conditions = [];

        if (isset($args["q"]) && !empty($args["q"])) {
            $query = $args['q'];
            $parts = preg_split("/\s+/", trim($query));
            $query_terms = [];
            foreach ($parts as $part) {
                $query_terms[] = "+$part*";
            }
            $conditions[] = "MATCH(search_term) AGAINST(%s IN BOOLEAN MODE)";
            $where_args[] = implode(" ", $query_terms);
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

        $query = $wpdb->prepare("   
            SELECT
                sesh.id as id,
                sesh.title as title,
                rst.drive_id as drive_id,
                DATE_FORMAT(rst.updated_at, '%%Y-%%m-%%dT%%T.%%fZ') as generated_at
            FROM {$wpdb->prefix}{$this->table_name} AS sesh
            LEFT JOIN {$wpdb->prefix}usctdp_roster_link as rst ON sesh.id = rst.entity_id
            {$where_clause}
            ORDER BY sesh.title
            {$limit_clause}
        ", array_merge($where_args, $limit_args));
        $window = $wpdb->get_results($query);

        $count_sql = "
            SELECT COUNT(*) as count
            FROM {$wpdb->prefix}{$this->table_name} AS sesh
            {$where_clause}";
        if(empty($where_args)) {
            $count_query = $count_sql;
        } else {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);
        return [
            'data' => $window,
            'count' => $count
        ];
    }
}
