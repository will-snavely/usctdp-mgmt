<?php

class Usctdp_Roster_Generator
{
    public function __construct() {}

    public function create_rosters($include)
    {
        $active_sessions = get_posts([
            'post_type' => 'usctdp-session',
            'tag' => 'active',
            'posts_per_page' => -1
        ]);
        $doc_gen = new Usctdp_Mgmt_Docgen();
        $current_date = new DateTime('now');

        foreach ($active_sessions as $session) {
            $session_id = $session->ID;
            $start_date = new DateTime(get_field('start_date', $session_id));
            $end_date = new DateTime(get_field('end_date', $session_id));
            if ($start_date > $current_date || $end_date < $current_date) {
                continue;
            }

            $class_ids = [];
            $clinic_ids = [];
            $activity_query = new Usctdp_Mgmt_Activity_Link_Query([
                'session_id' => $session_id
            ]);
            foreach ($activity_query->items as $item) {
                $class_ids[] = $item->activity_id;
                $clinic_ids[$item->clinic_id] = $item->clinic_id;
            }
            if (in_array('sessions', $include)) {
                WP_CLI::log('Processing Session: ' . $session_id);
                $session_doc = $doc_gen->generate_session_roster($session_id);
                $session_drive_file = $doc_gen->upload_to_google_drive($session_doc, $session_id);
                WP_CLI::log('Session Roster: ' . $session_drive_file->webViewLink);
            }
            if (in_array('clinics', $include)) {
                foreach ($clinic_ids as $clinic_id) {
                    WP_CLI::log('Processing Clinic: ' . $clinic_id);
                    $clinic_doc = $doc_gen->generate_clinic_roster($clinic_id, $session_id);
                    $clinic_drive_file = $doc_gen->upload_to_google_drive($clinic_doc, $clinic_id);
                    WP_CLI::log('Clinic Roster: ' . $clinic_drive_file->webViewLink);
                }
            }
            if (in_array('classes', $include)) {
                foreach ($class_ids as $class_id) {
                    WP_CLI::log('Processing Class: ' . $class_id);
                    $class_doc = $doc_gen->generate_class_roster($class_id);
                    $class_drive_file = $doc_gen->upload_to_google_drive($class_doc, $class_id);
                    WP_CLI::log('Class Roster: ' . $class_drive_file->webViewLink);
                }
            }
        }
    }
}
