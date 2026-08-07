<?php

if (!defined('ABSPATH')) {
    exit;
}

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use PhpOffice\PhpWord\TemplateProcessor;

define('PCLZIP_TEMPORARY_DIR', plugin_dir_path(__FILE__) . '/templates/tmp');

class Usctdp_Mgmt_Docgen
{
    private $roster_template_file;
    private $statement_template_file;

    public function __construct()
    {
        $docgen_dir = plugin_dir_path(__FILE__);
        $this->roster_template_file = $docgen_dir . 'templates/roster_template.docx';
        $this->statement_template_file = $docgen_dir . 'templates/statement_template.docx';
    }

    private function int_to_day($day)
    {
        switch ($day) {
            case 1:
                return 'Monday';
            case 2:
                return 'Tuesday';
            case 3:
                return 'Wednesday';
            case 4:
                return 'Thursday';
            case 5:
                return 'Friday';
            case 6:
                return 'Saturday';
            case 7:
                return 'Sunday';
            default:
                return 'Unknown';
        }
    }

    private function get_activity_registrations($activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $activity_id
        ]);
        return $reg_query->items;
    }

    private function get_activity_waitlist($activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Waitlist_Query([
            'activity_id' => $activity_id
        ]);
        return $reg_query->items;
    }

    /**
     * "First Last, First Last, ..." for every staff member currently
     * assigned to an activity, in the same name order as
     * Usctdp_Mgmt_Activity_Staff_Query::get_staff_for_activity() (last name,
     * then first). Empty string if nobody's assigned.
     */
    private function get_instructor_names($activity_id)
    {
        $staff_query = new Usctdp_Mgmt_Activity_Staff_Query();
        $staff = $staff_query->get_staff_for_activity($activity_id);
        $names = array_map(function ($person) {
            return trim($person->first_name . ' ' . $person->last_name);
        }, $staff);
        return implode(', ', $names);
    }

    public function get_roster_link($entity_id)
    {
        $reg_query = new Usctdp_Mgmt_Roster_Link_Query([
            'entity_id' => $entity_id,
            'number' => 1
        ]);
        if (empty($reg_query->items)) {
            return null;
        }
        return $reg_query->items[0];
    }

    public function generate_clinic_roster($clinic_id)
    {
        $templateProcessor = new TemplateProcessor($this->roster_template_file);
        $templateProcessor->cloneBlock('roster', 1, true, true);
        $this->generate_clinic_roster_impl($templateProcessor, $clinic_id, '1');
        return $templateProcessor;
    }

    public function generate_tournament_roster($tournament_id)
    {
        $templateProcessor = new TemplateProcessor($this->roster_template_file);
        $templateProcessor->cloneBlock('roster', 1, true, true);
        $this->generate_tournament_roster_impl($templateProcessor, $tournament_id, '1');
        return $templateProcessor;
    }

    public function generate_session_roster($session_id)
    {
        return $this->generate_roster_for_sessions([$session_id]);
    }

    /**
     * Builds one roster document spanning every activity across all of the
     * given sessions. Each activity block already stamps its own session's
     * title (see generate_clinic_roster_impl/generate_tournament_roster_impl),
     * so blocks from different sessions can simply be concatenated - no
     * template changes needed to support multi-session rosters.
     */
    public function generate_roster_for_sessions(array $session_ids)
    {
        $activity_items = [];
        foreach ($session_ids as $session_id) {
            $activity_query = new Usctdp_Mgmt_Activity_Query([
                'session_id' => $session_id,
                'orderby' => [
                    'primary_sort_order',
                    'secondary_sort_order',
                ],
                "order" => 'ASC'
            ]);
            array_push($activity_items, ...$activity_query->items);
        }

        $templateProcessor = new TemplateProcessor($this->roster_template_file);
        $templateProcessor->cloneBlock('roster', count($activity_items), true, true);
        $index = 1;
        foreach ($activity_items as $item) {
            if ($item->type === 'clinic') {
                $this->generate_clinic_roster_impl($templateProcessor, $item->id, $index);
            } elseif ($item->type === 'tournament') {
                $this->generate_tournament_roster_impl($templateProcessor, $item->id, $index);
            } else {
                Usctdp_Mgmt::logger()->log_info(
                    'Skipping roster block for unsupported activity type: ' . $item->type . ' (activity ' . $item->id . ')'
                );
            }
            $index++;
        }
        return $templateProcessor;
    }

    /**
     * Generates and uploads the roster for a single session, transparently
     * honoring roster grouping: if the session has been merged into a
     * multi-session roster, the document covers every member session and is
     * persisted against that roster group; otherwise this is identical to
     * generate_session_roster()+upload_to_google_drive() for that session
     * alone. This is the single entry point session-level roster generation
     * should go through (ajax_gen_roster's "session" target, "Regenerate
     * All Rosters", and the WP-CLI roster generator all use it).
     */
    public function generate_and_upload_session_roster($session_id, $fallback_title)
    {
        $group_query = new Usctdp_Mgmt_Roster_Group_Query();
        $roster_group = $group_query->find_group_for_session($session_id);
        if ($roster_group) {
            $member_session_ids = $group_query->get_member_session_ids($roster_group->id);
            $document = $this->generate_roster_for_sessions($member_session_ids);
            $title = $roster_group->name ? $roster_group->name : $fallback_title;
            return $this->upload_roster_group_document($document, $roster_group, $title);
        }
        $document = $this->generate_session_roster($session_id);
        return $this->upload_to_google_drive($document, $session_id, $fallback_title);
    }

    public function generate_financial_statement($family_id, $purchase_ids)
    {
        $templateProcessor = new TemplateProcessor($this->statement_template_file);
        $this->generate_statement_impl($templateProcessor, $family_id, $purchase_ids);
        return $templateProcessor;
    }

    public function upload_to_google_drive($templateProcessor, $entity_id, $title)
    {
        $roster_link = $this->get_roster_link($entity_id);
        $drive_id = $roster_link ? $roster_link->drive_id : null;

        $file = $this->upload_document_to_drive($templateProcessor, $drive_id, $title);

        if ($roster_link) {
            $link_query = new Usctdp_Mgmt_Roster_Link_Query([]);
            $link_query->update_item($roster_link->id, [
                'updated_at' => current_time('mysql', true)
            ]);
        } else {
            $link_query = new Usctdp_Mgmt_Roster_Link_Query([]);
            $link_query->add_item([
                'entity_id' => $entity_id,
                'drive_id' => $file->id,
                'updated_at' => current_time('mysql', true)
            ]);
        }
        return $file;
    }

    /**
     * Uploads/updates a roster group's own Drive doc and persists the
     * result directly on the usctdp_roster_group row. Deliberately does NOT
     * go through usctdp_roster_link - that table is shared (untyped) with
     * activity/clinic/tournament rosters and family statements, and a
     * roster_group.id could collide with an unrelated entity_id there.
     */
    public function upload_roster_group_document($templateProcessor, $roster_group, $title)
    {
        $file = $this->upload_document_to_drive($templateProcessor, $roster_group->drive_id ?: null, $title);

        $group_query = new Usctdp_Mgmt_Roster_Group_Query();
        $group_query->update_item($roster_group->id, [
            'drive_id' => $file->id,
            'updated_at' => current_time('mysql', true)
        ]);
        return $file;
    }

    /**
     * Pure Drive create-or-update call: writes $templateProcessor's content
     * to $existing_drive_id if given, otherwise creates a new file. Doesn't
     * know or care how the caller persists the resulting file id.
     */
    private function upload_document_to_drive($templateProcessor, $existing_drive_id, $title)
    {
        $client = $this->create_google_client();
        $drive = new Drive($client);
        $destinationFolderId = env('GOOGLE_DRIVE_FOLDER_ID');

        ob_start();
        $templateProcessor->saveAs('php://output');
        $content = ob_get_clean();

        $clean_title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $metadata_args = [
            'name' => $clean_title,
            'mimeType' => 'application/vnd.google-apps.document',
        ];

        if ($existing_drive_id !== null) {
            $fileMetadata = new DriveFile($metadata_args);
            return $drive->files->update($existing_drive_id, $fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink'
            ]);
        }

        if (!empty($destinationFolderId)) {
            $metadata_args['parents'] = [$destinationFolderId];
        }
        $fileMetadata = new DriveFile($metadata_args);
        $file = $drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink'
        ]);

        $drive->permissions->create($file->id, new Permission([
            'type' => 'anyone',
            'role' => 'writer'
        ]), ['fields' => 'id']);

        return $file;
    }

    private function create_google_client()
    {
        $refreshToken = get_option('usctdp_google_refresh_token');
        if (empty($refreshToken)) {
            throw new ErrorException('No refresh token found. User must re-authorize.');
        }

        $client = new Client();
        $client->setClientId(env('GOOGLE_DOCS_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_DOCS_CLIENT_SECRET'));
        $client->fetchAccessTokenWithRefreshToken($refreshToken);
        return $client;
    }

    private function generate_statement_impl($templateProcessor, $family_id, $purchase_ids)
    {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $family = Usctdp_Mgmt_Model::get_family($family_id);
        if (empty($family)) {
            throw new ErrorException('Family not found');
        }
        $templateProcessor->setValue("family_id", $family->title);
        $templateProcessor->setValue("address", $family->address);
        $templateProcessor->setValue("city", $family->city);
        $templateProcessor->setValue("state", $family->state);
        $templateProcessor->setValue("zip", $family->zip);
        $templateProcessor->setValue("stmt_date", date("m/d/Y"));

        $runningBalance = 0;
        $statement_rows = [];

        foreach ($purchase_ids as $purchase_id) {
            $purchase_query = new Usctdp_Mgmt_Purchase_Query();
            $purchase_data = $purchase_query->get_purchase_data([
                'purchase_id' => $purchase_id
            ])['data'];
            if (empty($purchase_data)) {
                throw new ErrorException('Purchase not found: ' . $purchase_id);
            }
            $purchase_fields = $purchase_data[0];

            $ledger_query = new Usctdp_Mgmt_Ledger_Query();
            $ledger_events = $ledger_query->get_ledger_events([
                'purchase_id' => $purchase_id,
                'account' => $purchase_fields->purchase_type . '_fees'
            ])['data'];
            $session = '';
            if ($purchase_fields->product_type === "tournament") {
                $session = $purchase_fields->session_name;

            } else {
                $session = $purchase_fields->session_name . ', ' . $purchase_fields->activity_name;
            }
            $first = true;
            foreach ($ledger_events as $item) {
                $charge = floatval($item->charge_amount);
                $payment = floatval($item->payment_amount);
                $runningBalance += ($charge - $payment);
                $item->calculated_balance = $runningBalance;
                $date = new DateTime($item->event_date);
                $date->setTimezone(new DateTimeZone('America/New_York'));
                $formatted_date = $date->format('m/d/y');
                $session_str = "--";
                $name_str = "--";
                if ($first) {
                    $session_str = $session;
                    $name_str = $purchase_fields->student_first;
                    $first = false;
                }
                $statement_rows[] = [
                    'date' => $formatted_date,
                    'session' => $session_str,
                    'name' => $name_str,
                    'description' => $item->event_description,
                    'amount' => $formatter->formatCurrency($charge - $payment, 'USD'),
                    'balance' => $formatter->formatCurrency($runningBalance, 'USD')
                ];
            }
        }
        $templateProcessor->cloneRowAndSetValues("date", $statement_rows);
        $templateProcessor->setValue("balance_due", $formatter->formatCurrency($runningBalance, 'USD'));
    }

    private function generate_clinic_roster_impl($templateProcessor, $clinic_id, $block_id)
    {
        $clinic_query = new Usctdp_Mgmt_Clinic_Query();
        $clinic_data = $clinic_query->get_clinic_data([
            'id' => $clinic_id,
            'number' => 1
        ]);
        if (empty($clinic_data['data'])) {
            throw new ErrorException('Clinic not found');
        }
        $clinic_fields = $clinic_data['data'][0];
        $session_name = $clinic_fields->session_name;
        $age_group = ucfirst($clinic_fields->product_age_group);
        $product_name = $clinic_fields->product_name;
        $session_title = $session_name . ": " . $product_name;
        $activity_meta = json_decode($clinic_fields->clinic_meta, true);
        if(isset($activity_meta['session_title'])) {
            $session_title = $activity_meta['session_title'];
        }

        $start_date_raw = $clinic_fields->session_start_date;
        $start_date = $start_date_raw ? DateTime::createFromFormat('Y-m-d', $start_date_raw)->format('m/d/Y') : '';
        $end_date_raw = $clinic_fields->session_end_date;
        $end_date = $end_date_raw ? DateTime::createFromFormat('Y-m-d', $end_date_raw)->format('m/d/Y') : '';

        $start_time_raw = $clinic_fields->clinic_start_time;
        $start_time = $start_time_raw ? DateTime::createFromFormat('H:i:s', $start_time_raw)->format('g:i A') : '';
        $end_time_raw = $clinic_fields->clinic_end_time;
        $end_time = $end_time_raw ? DateTime::createFromFormat('H:i:s', $end_time_raw)->format('g:i A') : '';

        $templateProcessor->setValue("session_title#$block_id", $session_title);
        $templateProcessor->setValue("dow#$block_id", $this->int_to_day($clinic_fields->clinic_day_of_week));
        $templateProcessor->setValue("stime#$block_id", $start_time);
        $templateProcessor->setValue("etime#$block_id", $end_time);
        $templateProcessor->setValue("clinic_level#$block_id", $clinic_fields->clinic_level);
        $templateProcessor->setValue("cap#$block_id", $clinic_fields->clinic_capacity);
        $templateProcessor->setValue("age_group#$block_id", $age_group);
        $templateProcessor->setValue("sdate#$block_id", $start_date);
        $templateProcessor->setValue("edate#$block_id", $end_date);

        $templateProcessor->setValue("insts#$block_id", $this->get_instructor_names($clinic_id));
        $templateProcessor->setValue("skipped_clinics#$block_id", '');
        $templateProcessor->setValue("session_short_code#$block_id", '');

        $this->fill_roster_students($templateProcessor, $clinic_id, $block_id);
        $this->fill_roster_waitlist($templateProcessor, $clinic_id, $block_id);
    }

    private function generate_tournament_roster_impl($templateProcessor, $tournament_id, $block_id)
    {
        $tournament_query = new Usctdp_Mgmt_Tournament_Query();
        $tournament_data = $tournament_query->get_tournament_data([
            'id' => $tournament_id,
            'number' => 1
        ]);
        if (empty($tournament_data['data'])) {
            throw new ErrorException('Tournament not found');
        }
        $tournament_fields = $tournament_data['data'][0];
        $session_name = $tournament_fields->session_name;
        $age_group = $tournament_fields->product_age_group;

        $start_date_raw = $tournament_fields->tournament_start_date;
        $start_date = $start_date_raw ? DateTime::createFromFormat('Y-m-d', $start_date_raw)->format('m/d/Y') : '';
        $end_date_raw = $tournament_fields->tournament_start_date_addtl;
        $end_date = $end_date_raw ? DateTime::createFromFormat('Y-m-d', $end_date_raw)->format('m/d/Y') : '';

        $templateProcessor->setValue("session_title#$block_id", $session_name);
        $templateProcessor->setValue("dow#$block_id", '');
        $templateProcessor->setValue("stime#$block_id", '');
        $templateProcessor->setValue("etime#$block_id", '');
        $templateProcessor->setValue("clinic_level#$block_id", $tournament_fields->activity_level);
        $templateProcessor->setValue("cap#$block_id", $tournament_fields->activity_capacity);
        $templateProcessor->setValue("age_group#$block_id", $this->$age_group);
        $templateProcessor->setValue("sdate#$block_id", $start_date);
        $templateProcessor->setValue("edate#$block_id", $end_date);

        $templateProcessor->setValue("insts#$block_id", $this->get_instructor_names($tournament_id));
        $templateProcessor->setValue("skipped_clinics#$block_id", '');
        $templateProcessor->setValue("session_short_code#$block_id", '');

        $this->fill_roster_students($templateProcessor, $tournament_id, $block_id);
    }

    private function fill_roster_students($templateProcessor, $activity_id, $block_id)
    {
        $registrations = $this->get_activity_registrations($activity_id);

        $student_table_data = [];
        $idx = 1;
        foreach ($registrations as $registration) {
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $registration->student_id,
                'number' => 1
            ]);
            if (empty($student_query->items)) {
                throw new ErrorException('Student ' . $registration->student_id . ' not found');
            }
            $student_data = $student_query->items[0];

            $family_query = new Usctdp_Mgmt_Family_Query([
                'id' => $student_data->family_id,
                'number' => 1
            ]);
            if (empty($family_query->items)) {
                throw new ErrorException('Family ' . $student_data->family_id . ' not found');
            }
            $family_data = $family_query->items[0];
            $phone = implode('/', $family_data->phone_numbers);
            $first_name = $student_data->first;
            $last_name = $student_data->last;
            $level = $registration->student_level;
            $student_age = $student_data->age;

            $student_table_data[] = [
                'att#' . $block_id => "___" . $idx,
                'last#' . $block_id => $last_name,
                'first#' . $block_id => $first_name,
                'age#' . $block_id => $student_age,
                'lvl#' . $block_id => $level,
                'phones#' . $block_id => $phone
            ];
            $idx++;
        }

        while ($idx < 32) {
            $student_table_data[] = [
                'att#' . $block_id => '',
                'last#' . $block_id => '',
                'first#' . $block_id => '',
                'age#' . $block_id => '',
                'lvl#' . $block_id => '',
                'phones#' . $block_id => ''
            ];
            $idx++;
        }
        $templateProcessor->cloneRowAndSetValues("att#$block_id", $student_table_data);
    }

    private function fill_roster_waitlist($templateProcessor, $activity_id, $block_id)
    {
        $waitlist_entries = $this->get_activity_waitlist($activity_id);
        $waitlist_table_data = [];
        $max_display = 10;
        $count = 0;
        $idx = 1;
        foreach ($waitlist_entries as $item) {
            $count += 1;
            if($count > $max_display) {
                break;
            }
            $student_query = new Usctdp_Mgmt_Student_Query([
                'id' => $item->student_id,
                'number' => 1
            ]);
            if (empty($student_query->items)) {
                throw new ErrorException('Student ' . $registration->student_id . ' not found');
            }
            $student_data = $student_query->items[0];
            $family_query = new Usctdp_Mgmt_Family_Query([
                'id' => $student_data->family_id,
                'number' => 1
            ]);
            if (empty($family_query->items)) {
                throw new ErrorException('Family ' . $student_data->family_id . ' not found');
            }
            $family_data = $family_query->items[0];
            $phone = implode('/', $family_data->phone_numbers);
            $first_name = $student_data->first;
            $last_name = $student_data->last;

            $waitlist_table_data[] = [
                'wl_last#' . $block_id => $last_name,
                'wl_first#' . $block_id => $first_name,
                'wl_phones#' . $block_id => $phone
            ];
            $idx++;
        }
        $templateProcessor->cloneRowAndSetValues("wl_last#$block_id", $waitlist_table_data);
    }
}
