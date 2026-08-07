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
     * Fetches a single roster group by id, or null if it doesn't exist.
     */
    public function get_group($roster_group_id)
    {
        $group_query = new Usctdp_Mgmt_Roster_Group_Query(['id' => $roster_group_id, 'number' => 1]);
        return !empty($group_query->items) ? $group_query->items[0] : null;
    }

    /**
     * Sessions default to an implicit 1:1 roster (no usctdp_roster_group row
     * at all) and only get a real row here the first time their roster is
     * edited - renamed, or another session added to it. This keeps every
     * still-implicit session's existing usctdp_roster_link history untouched.
     */
    public function get_or_create_for_session($session_id, $name = null)
    {
        $existing_group = $this->find_group_for_session($session_id);
        if ($existing_group) {
            return $existing_group;
        }

        if ($name === null) {
            $name = $this->default_name_for_session($session_id);
        }

        // Carry forward any doc this session already generated on its own,
        // so promoting it to a group doesn't orphan that Drive link.
        $existing_link_query = new Usctdp_Mgmt_Roster_Link_Query([
            'entity_id' => $session_id,
            'number' => 1
        ]);
        $existing_link = !empty($existing_link_query->items) ? $existing_link_query->items[0] : null;

        $group_id = $this->add_item([
            'name' => $name,
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

        return $this->get_group($group_id);
    }

    /**
     * The name a session's roster gets when nobody has picked one: the
     * session's own title. Stored on the row at creation time rather than
     * left blank so the group is self-describing to anything reading the
     * table directly (CLI, reports, ad-hoc SQL), not just to search_rosters().
     */
    public function default_name_for_session($session_id)
    {
        $session_query = new Usctdp_Mgmt_Session_Query([
            'id' => $session_id,
            'number' => 1
        ]);
        return !empty($session_query->items) ? $session_query->items[0]->title : null;
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
        return $this->get_group($membership_query->items[0]->roster_group_id);
    }

    /**
     * Member session ids in the order they were added to the group - the
     * membership row's own auto-increment id is a reliable stand-in for
     * that, since rows are only ever inserted, never reordered. Used by
     * docgen (Usctdp_Mgmt_Docgen::generate_and_upload_roster_group()) so
     * a multi-session roster's doc lists sessions in that same order.
     */
    public function get_member_session_ids($roster_group_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'roster_group_id' => $roster_group_id,
            'orderby' => 'id',
            'order' => 'ASC'
        ]);
        return array_map(function ($member) {
            return (int) $member->session_id;
        }, $membership_query->items);
    }

    public function rename($roster_group_id, $name)
    {
        $name = trim((string) $name);
        return $this->update_item($roster_group_id, [
            'name' => $name === '' ? null : $name
        ]);
    }

    /**
     * A session may belong to any number of roster groups at once - this
     * only guards against adding it to the *same* group twice, not against
     * membership in some other group.
     */
    public function add_session($roster_group_id, $session_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'roster_group_id' => $roster_group_id,
            'session_id' => $session_id,
            'number' => 1
        ]);
        if (!empty($membership_query->items)) {
            return $membership_query->items[0];
        }

        return $membership_query->add_item([
            'roster_group_id' => $roster_group_id,
            'session_id' => $session_id,
            'created_at' => current_time('mysql', true)
        ]);
    }

    /**
     * Rosters are allowed to end up with zero sessions - deliberately no
     * minimum-membership check here. An empty group just stops appearing in
     * search_rosters() (it INNER JOINs member sessions); delete_group() is
     * the explicit action for getting rid of the row entirely.
     */
    public function remove_session($roster_group_id, $session_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'roster_group_id' => $roster_group_id
        ]);

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
     * Always makes a brand-new, empty roster group - unlike
     * get_or_create_for_session(), which reuses a session's existing group.
     * Used by the "Create New Roster" flow, where the point is a second (or
     * third...) roster for a session that already has one, now that group
     * membership isn't exclusive.
     */
    public function create_group($name = null)
    {
        $name = $name !== null ? trim((string) $name) : null;
        $group_id = $this->add_item([
            'name' => $name === '' ? null : $name,
            'drive_id' => '',
            'created_at' => current_time('mysql', true)
        ]);
        if (!$group_id) {
            throw new Roster_Group_Exception('Failed to create a new roster.');
        }

        return $this->get_group($group_id);
    }

    /**
     * Deletes a roster group and its memberships. Member sessions aren't
     * touched - each one just reverts to its own implicit 1:1 roster (or, if
     * it belongs to another group too, keeps showing under that one).
     */
    public function delete_group($roster_group_id)
    {
        $membership_query = new Usctdp_Mgmt_Roster_Group_Session_Query([
            'roster_group_id' => $roster_group_id
        ]);
        foreach ($membership_query->items as $member) {
            $membership_query->delete_item($member->id);
        }

        return $this->delete_item($roster_group_id);
    }

    /**
     * Sessions-tab listing: one row per explicit roster group, including
     * empty ones - a group is a deliberately-created thing, like a folder,
     * and doesn't disappear just because it's temporarily got nothing in it.
     * A session with no group of its own doesn't show up here at all -
     * it only appears once something (the "Create Roster" button, the seed
     * CLI, or import) has actually put it in a group. $args supports 'q'
     * (search), 'number'/'offset' (pagination) - omit both to get every
     * roster back.
     */
    public function search_rosters($args = [])
    {
        global $wpdb;
        $session_table = $wpdb->prefix . 'usctdp_session';
        $group_table = $wpdb->prefix . 'usctdp_roster_group';
        $group_session_table = $wpdb->prefix . 'usctdp_roster_group_session';

        // Note: the DATE_FORMAT() '%' specifiers below are literal - this
        // combined query is never itself passed through $wpdb->prepare()
        // (only the WHERE/LIMIT fragments appended to it are), so they must
        // NOT be doubled the way prepare()-bound query strings elsewhere in
        // this codebase double them.
        //
        // LEFT JOINs throughout (rather than the INNER JOINs a "one row per
        // roster" query would normally use) so a group with zero - or zero
        // still-active - member sessions still produces its one row, just
        // with NULL primary_session_id and empty session_ids/session_titles.
        $combined_sql = "
            SELECT
                rg.id as roster_group_id,
                primary_sess.id as primary_session_id,
                COALESCE(NULLIF(rg.name, ''), primary_sess.title, 'Untitled Roster') as name,
                rg.drive_id as drive_id,
                DATE_FORMAT(rg.updated_at, '%Y-%m-%dT%T.%fZ') as generated_at,
                -- Ordered by the membership row's own id, not the session's -
                -- i.e. the order sessions were added to the group, matching
                -- get_member_session_ids() below and the doc it drives.
                GROUP_CONCAT(sesh.id ORDER BY rgs.id) as session_ids,
                GROUP_CONCAT(sesh.title ORDER BY rgs.id SEPARATOR '||') as session_titles
            FROM {$group_table} AS rg
            LEFT JOIN {$group_session_table} AS rgs ON rgs.roster_group_id = rg.id
            LEFT JOIN {$session_table} AS sesh ON sesh.id = rgs.session_id AND sesh.is_active = 1
            LEFT JOIN (
                SELECT roster_group_id, MIN(session_id) as primary_session_id
                FROM {$group_session_table}
                GROUP BY roster_group_id
            ) AS primary_map ON primary_map.roster_group_id = rg.id
            LEFT JOIN {$session_table} AS primary_sess ON primary_sess.id = primary_map.primary_session_id
            GROUP BY rg.id
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
        $sessions = [];
        if (!empty($row->session_ids)) {
            $ids = explode(',', $row->session_ids);
            $titles = explode('||', $row->session_titles);
            foreach ($ids as $index => $id) {
                $sessions[] = [
                    'id' => (int) $id,
                    'title' => isset($titles[$index]) ? $titles[$index] : ''
                ];
            }
        }
        return [
            // An empty group has no primary session to key off of - fall
            // back to a synthetic negative id (session ids are always
            // positive) so the row still has something unique to identify
            // it by in the JS table.
            'id' => $row->primary_session_id !== null ? (int) $row->primary_session_id : -((int) $row->roster_group_id),
            'roster_group_id' => (int) $row->roster_group_id,
            'name' => $row->name,
            'drive_id' => $row->drive_id,
            'generated_at' => $row->generated_at,
            'sessions' => $sessions
        ];
    }
}
