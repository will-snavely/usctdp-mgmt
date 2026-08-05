<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Thrown for roster-group edits that fail a business rule (e.g. a session
 * already belongs to another roster). Callers should surface the message
 * directly rather than treating it as an unexpected server error.
 */
class Roster_Group_Exception extends Exception
{
}

class Usctdp_Mgmt_Roster_Group_Query extends Query
{
    protected $table_name = 'usctdp_roster_group';
    protected $table_alias = 'urg';
    protected $table_schema = 'Usctdp_Mgmt_Roster_Group_Schema';
    protected $item_name = 'roster_group';
    protected $item_name_plural = 'roster_groups';
    protected $item_shape = 'Usctdp_Mgmt_Roster_Group_Row';

    /**
     * Sessions default to an implicit 1:1 roster (no usctdp_roster_group row
     * at all) and only get a real row here the first time their roster is
     * edited - renamed, or another session added to it. This keeps every
     * still-implicit session's existing usctdp_roster_link history untouched.
     */
    public function get_or_create_for_session($session_id)
    {
        $existing_group = $this->find_group_for_session($session_id);
        if ($existing_group) {
            return $existing_group;
        }

        // Carry forward any doc this session already generated on its own,
        // so promoting it to a group doesn't orphan that Drive link.
        $existing_link_query = new Usctdp_Mgmt_Roster_Link_Query([
            'entity_id' => $session_id,
            'number' => 1
        ]);
        $existing_link = !empty($existing_link_query->items) ? $existing_link_query->items[0] : null;

        $group_id = $this->add_item([
            'drive_id' => $existing_link ? $existing_link->drive_id : '',
            'updated_at' => ($existing_link && $existing_link->updated_at) ? $existing_link->updated_at->format('Y-m-d H:i:s') : null,
            'created_at' => current_time('mysql', true)
        ]);
        if (!$group_id) {
            throw new Roster_Group_Exception('Failed to create a roster for this session.');
        }

        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query();
        $membership_query->add_item([
            'roster_group_id' => $group_id,
            'session_id' => $session_id,
            'created_at' => current_time('mysql', true)
        ]);

        $group_query = new Usctdp_Mgmt_Roster_Group_Query(['id' => $group_id, 'number' => 1]);
        return $group_query->items[0];
    }

    public function find_group_for_session($session_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'session_id' => $session_id,
            'number' => 1
        ]);
        if (empty($membership_query->items)) {
            return null;
        }
        $group_query = new Usctdp_Mgmt_Roster_Group_Query([
            'id' => $membership_query->items[0]->roster_group_id,
            'number' => 1
        ]);
        return !empty($group_query->items) ? $group_query->items[0] : null;
    }

    public function get_member_session_ids($roster_group_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'roster_group_id' => $roster_group_id
        ]);
        $session_ids = array_map(function ($member) {
            return (int) $member->session_id;
        }, $membership_query->items);
        sort($session_ids);
        return $session_ids;
    }

    public function rename($roster_group_id, $name)
    {
        $name = trim((string) $name);
        return $this->update_item($roster_group_id, [
            'name' => $name === '' ? null : $name
        ]);
    }

    public function add_session($roster_group_id, $session_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'session_id' => $session_id,
            'number' => 1
        ]);
        if (!empty($membership_query->items)) {
            $existing = $membership_query->items[0];
            if ((int) $existing->roster_group_id !== (int) $roster_group_id) {
                throw new Roster_Group_Exception('That session already belongs to another roster.');
            }
            return $existing;
        }

        return $membership_query->add_item([
            'roster_group_id' => $roster_group_id,
            'session_id' => $session_id,
            'created_at' => current_time('mysql', true)
        ]);
    }

    public function remove_session($roster_group_id, $session_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'roster_group_id' => $roster_group_id
        ]);
        if (count($membership_query->items) <= 1) {
            throw new Roster_Group_Exception('A roster must contain at least one session.');
        }

        $target = null;
        foreach ($membership_query->items as $member) {
            if ((int) $member->session_id === (int) $session_id) {
                $target = $member;
                break;
            }
        }
        if (!$target) {
            throw new Roster_Group_Exception('That session is not part of this roster.');
        }

        return $membership_query->delete_item($target->id);
    }

    /**
     * Sessions-tab listing: one row per roster, whether it's an explicit
     * multi-session group or a still-implicit single-session default. Built
     * as a UNION of both cases directly in SQL since they live in different
     * tables. $args supports 'q' (search), 'number'/'offset' (pagination) -
     * omit both to get every roster back.
     */
    public function search_rosters($args = [])
    {
        global $wpdb;
        $session_table = $wpdb->prefix . 'usctdp_session';
        $group_table = $wpdb->prefix . 'usctdp_roster_group';
        $group_session_table = $wpdb->prefix . 'usctdp_roster_group_session';
        $link_table = $wpdb->prefix . 'usctdp_roster_link';

        // Note: the DATE_FORMAT() '%' specifiers below are literal - this
        // combined query is never itself passed through $wpdb->prepare()
        // (only the WHERE/LIMIT fragments appended to it are), so they must
        // NOT be doubled the way prepare()-bound query strings elsewhere in
        // this codebase double them.
        $combined_sql = "
            SELECT
                rg.id as roster_group_id,
                primary_sess.id as primary_session_id,
                COALESCE(rg.name, primary_sess.title) as name,
                rg.drive_id as drive_id,
                DATE_FORMAT(rg.updated_at, '%Y-%m-%dT%T.%fZ') as generated_at,
                GROUP_CONCAT(sesh.id ORDER BY sesh.id) as session_ids,
                GROUP_CONCAT(sesh.title ORDER BY sesh.id SEPARATOR '||') as session_titles
            FROM {$group_table} AS rg
            INNER JOIN {$group_session_table} AS rgs ON rgs.roster_group_id = rg.id
            INNER JOIN {$session_table} AS sesh ON sesh.id = rgs.session_id AND sesh.is_active = 1
            INNER JOIN (
                SELECT roster_group_id, MIN(session_id) as primary_session_id
                FROM {$group_session_table}
                GROUP BY roster_group_id
            ) AS primary_map ON primary_map.roster_group_id = rg.id
            INNER JOIN {$session_table} AS primary_sess ON primary_sess.id = primary_map.primary_session_id
            GROUP BY rg.id
            HAVING COUNT(sesh.id) > 0

            UNION ALL

            SELECT
                NULL as roster_group_id,
                sesh.id as primary_session_id,
                sesh.title as name,
                rst.drive_id as drive_id,
                DATE_FORMAT(rst.updated_at, '%Y-%m-%dT%T.%fZ') as generated_at,
                CAST(sesh.id as CHAR) as session_ids,
                sesh.title as session_titles
            FROM {$session_table} AS sesh
            LEFT JOIN {$link_table} AS rst ON rst.entity_id = sesh.id
            WHERE sesh.is_active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM {$group_session_table} AS rgs2 WHERE rgs2.session_id = sesh.id
                )
        ";

        $where_sql = '';
        if (!empty($args['q'])) {
            $like = '%' . $wpdb->esc_like($args['q']) . '%';
            $where_sql = $wpdb->prepare("WHERE combined.name LIKE %s OR combined.session_titles LIKE %s", $like, $like);
        }

        $limit_sql = '';
        if (isset($args['number']) && $args['number'] !== null) {
            if (isset($args['offset'])) {
                $limit_sql = $wpdb->prepare("LIMIT %d OFFSET %d", (int) $args['number'], (int) $args['offset']);
            } else {
                $limit_sql = $wpdb->prepare("LIMIT %d", (int) $args['number']);
            }
        }

        $rows = $wpdb->get_results("
            SELECT * FROM ({$combined_sql}) AS combined
            {$where_sql}
            ORDER BY combined.name
            {$limit_sql}
        ");
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM ({$combined_sql}) AS combined {$where_sql}");

        return [
            'data' => array_map([$this, 'format_roster_row'], $rows),
            'count' => $count
        ];
    }

    private function format_roster_row($row)
    {
        $ids = explode(',', $row->session_ids);
        $titles = explode('||', $row->session_titles);
        $sessions = [];
        foreach ($ids as $index => $id) {
            $sessions[] = [
                'id' => (int) $id,
                'title' => isset($titles[$index]) ? $titles[$index] : ''
            ];
        }
        return [
            'id' => (int) $row->primary_session_id,
            'roster_group_id' => $row->roster_group_id !== null ? (int) $row->roster_group_id : null,
            'name' => $row->name,
            'drive_id' => $row->drive_id,
            'generated_at' => $row->generated_at,
            'sessions' => $sessions
        ];
    }
}
