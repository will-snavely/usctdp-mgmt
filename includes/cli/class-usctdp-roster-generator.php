<?php

class Usctdp_Roster_Generator
{
    public function __construct() {}

    public function create_rosters()
    {
        $session_query = new Usctdp_Mgmt_Session_Query([
            'is_active' => 1
        ]);
        $doc_gen = new Usctdp_Mgmt_Docgen();
        $current_date = new DateTime('now');

        foreach ($session_query->items as $session) {
            $session_id = $session->id;
            WP_CLI::log('Processing Session: ' . $session_id);
            $session_doc = $doc_gen->generate_session_roster($session_id);
            $session_drive_file = $doc_gen->upload_to_google_drive($session_doc, $session_id, $session->title);
            WP_CLI::log('Session Roster: ' . $session_drive_file->webViewLink);
        }
    }
}
