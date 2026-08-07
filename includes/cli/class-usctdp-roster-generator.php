<?php

class Usctdp_Roster_Generator
{
    public function __construct() {}

    public function create_rosters()
    {
        $roster_query = new Usctdp_Mgmt_Roster_Group_Query();
        $rosters = $roster_query->search_rosters([])['data'];
        $doc_gen = new Usctdp_Mgmt_Docgen();

        foreach ($rosters as $roster) {
            WP_CLI::log('Processing Roster: ' . $roster['name'] . ' (sessions: ' . implode(', ', array_column($roster['sessions'], 'id')) . ')');
            if (empty($roster['sessions'])) {
                WP_CLI::log('Skipping - no sessions in this roster.');
                continue;
            }
            $drive_file = $doc_gen->generate_and_upload_roster_group($roster['roster_group_id']);
            WP_CLI::log('Roster: ' . $drive_file->webViewLink);
        }
    }
}
