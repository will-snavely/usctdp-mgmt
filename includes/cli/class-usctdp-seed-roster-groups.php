<?php

/**
 * One-time (but safe to re-run) seeding tool: materializes an explicit
 * usctdp_roster_group for every session that doesn't already have one, each
 * containing just that session. Roster groups are normally created lazily
 * the first time a roster is edited in the admin UI - this exists purely to
 * pre-seed prod so every session already has an explicit group to work
 * from, without anyone having to click "Edit" first.
 */
class Usctdp_Seed_Roster_Groups
{
    public function seed()
    {
        // BerlinDB caps queries at 100 rows unless 'number' says otherwise,
        // and this needs to reach every session, not the first page of them.
        $session_query = new Usctdp_Mgmt_Session_Query(['number' => 0]);
        $group_query = new Usctdp_Mgmt_Roster_Group_Query();
        $created = 0;
        $named = 0;
        $skipped = 0;

        foreach ($session_query->items as $session) {
            $group = $group_query->find_group_for_session($session->id);
            if (!$group) {
                $group_query->get_or_create_for_session($session->id, $session->title);
                $created++;
                WP_CLI::log('Created roster group for session: ' . $session->title . ' (id=' . $session->id . ')');
                continue;
            }

            // Groups created before names were stored explicitly came out
            // blank and only got a name from the primary session's title at
            // render time. Backfill that same title onto the row so the
            // stored name matches what the UI was already showing - only
            // from the group's primary (lowest-id) member - matching
            // search_rosters()'s MIN(session_id) - so a multi-session group
            // doesn't get named after an arbitrary member. Note this is
            // deliberately NOT get_member_session_ids()[0], which is ordered
            // by when each session was added, not by id.
            if (trim((string) $group->name) !== '') {
                $skipped++;
                continue;
            }
            $member_ids = $group_query->get_member_session_ids($group->id);
            if (empty($member_ids) || min($member_ids) !== (int) $session->id) {
                $skipped++;
                continue;
            }
            $group_query->rename($group->id, $session->title);
            $named++;
            WP_CLI::log('Named roster group ' . $group->id . ': ' . $session->title);
        }

        WP_CLI::success("Seeded roster groups: $created created, $named named, $skipped unchanged.");
    }
}
