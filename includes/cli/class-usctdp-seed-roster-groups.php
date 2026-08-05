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
        $session_query = new Usctdp_Mgmt_Session_Query();
        $group_query = new Usctdp_Mgmt_Roster_Group_Query();
        $created = 0;
        $skipped = 0;

        foreach ($session_query->items as $session) {
            if ($group_query->find_group_for_session($session->id)) {
                $skipped++;
                continue;
            }
            $group_query->get_or_create_for_session($session->id);
            $created++;
            WP_CLI::log('Created roster group for session: ' . $session->title . ' (id=' . $session->id . ')');
        }

        WP_CLI::success("Seeded roster groups: $created created, $skipped already had one.");
    }
}
