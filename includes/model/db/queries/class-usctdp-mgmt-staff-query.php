<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Staff_Query extends Query
{
    protected $table_name = 'usctdp_staff';
    protected $table_alias = 'ustaff';
    protected $table_schema = 'Usctdp_Mgmt_Staff_Schema';
    protected $item_name = 'staff';
    protected $item_name_plural = 'staff';
    protected $item_shape = 'Usctdp_Mgmt_Staff_Row';

    /**
     * Same shape as Usctdp_Mgmt_Session_Query::search_sessions() /
     * Usctdp_Mgmt_Activity_Query::search_activities(). $exclude_activity_id
     * lets the "add instructor" picker leave out staff already assigned to
     * the activity being edited, mirroring how search_sessions() excludes
     * sessions already in the roster group being edited.
     */
    public function search_staff($query, $exclude_activity_id = null, $limit = 10)
    {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}usctdp_staff";
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
        if ($exclude_activity_id !== null) {
            $conditions[] = "id NOT IN (
                SELECT staff_id FROM {$wpdb->prefix}usctdp_activity_staff WHERE activity_id = %d
            )";
            $args[] = $exclude_activity_id;
        }
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY last_name ASC, first_name ASC LIMIT %d";
        $args[] = $limit;

        $query = $wpdb->prepare($sql, $args);
        return $wpdb->get_results($query);
    }
}
