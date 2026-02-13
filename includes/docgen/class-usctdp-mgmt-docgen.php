<?php

if (!defined('ABSPATH')) {
    exit;
}

use Google\Client;
use Google\Service\Docs;
use Google\Service\Docs\Request as DocsRequest;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Google\Service\Docs\InsertTextRequest;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Settings;

define('PCLZIP_TEMPORARY_DIR', plugin_dir_path(__FILE__) . '/templates/tmp');

class Usctdp_Mgmt_Docgen
{
    private $template_file;

    public function __construct()
    {
        $docgen_dir = plugin_dir_path(__FILE__);
        $this->template_file = $docgen_dir . 'templates/roster_template.docx';
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

    private function get_class_registrations($class_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $class_id
        ]);
        return $reg_query->items;
    }

    private function get_drive_id($entity_id)
    {
        $reg_query = new Usctdp_Mgmt_Roster_Link_Query([
            'entity_id' => $entity_id,
            'number' => 1
        ]);
        if (empty($reg_query->items)) {
            return null;
        }
        return $reg_query->items[0]->drive_id;
    }

    public function generate_class_roster($class_id)
    {
        $templateProcessor = new TemplateProcessor($this->template_file);
        $templateProcessor->cloneBlock('roster', 1, true, true);
        $this->generate_class_roster_impl($templateProcessor, $class_id, '1');
        return $templateProcessor;
    }

    public function generate_session_roster($session_id)
    {
        WP_CLI::log("Generating session roster for session " . $session_id);
        $class_query = new Usctdp_Mgmt_Activity_Query([
            'session_id' => $session_id
        ]);
        $templateProcessor = new TemplateProcessor($this->template_file);
        $templateProcessor->cloneBlock('roster', count($class_query->items), true, true);
        $index = 1;
        foreach ($class_query->items as $item) {
            WP_CLI::log("Generating class roster for class " . $item->id);
            $this->generate_class_roster_impl($templateProcessor, $item->id, $index);
            $index++;
        }
        return $templateProcessor;
    }

    public function upload_to_google_drive($templateProcessor, $entity_id, $title)
    {
        $client = $this->create_google_client();
        $drive = new Drive($client);

        $drive_id = $this->get_drive_id($entity_id);
        $destinationFolderId = env('GOOGLE_DRIVE_FOLDER_ID');

        ob_start();
        $templateProcessor->saveAs('php://output');
        $content = ob_get_clean();

        $clean_title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $metadata_args = [
            'name' => 'Roster: ' . $clean_title,
            'mimeType' => 'application/vnd.google-apps.document',
        ];

        if ($drive_id !== null) {
            $fileMetadata = new DriveFile($metadata_args);
            $file = $drive->files->update($drive_id, $fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink'
            ]);
        } else {
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

            $link_query = new Usctdp_Mgmt_Roster_Link_Query([]);
            $link_query->add_item([
                'entity_id' => $entity_id,
                'drive_id' => $file->id
            ]);
        }
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

    private function generate_class_roster_impl($templateProcessor, $class_id, $block_id)
    {
        $class_query = new Usctdp_Mgmt_Clinic_Query();
        $class_data = $class_query->get_clinic_data([
            'id' => $class_id,
            'number' => 1
        ]);
        if (empty($class_data['data'])) {
            throw new ErrorException('Class not found');
        }
        $class_fields = $class_data['data'][0];
        $registrations = $this->get_class_registrations($class_id);
        $session_name = $class_fields->session_name;
        $age_group = get_field('usctdp_clinic_age_group', $class_fields->clinic_id);
        $start_date_raw = $class_fields->session_start_date;
        $start_date = $start_date_raw ? DateTime::createFromFormat('Y-m-d', $start_date_raw)->format('m/d/Y') : '';
        $end_date_raw = $class_fields->session_end_date;
        $end_date = $end_date_raw ? DateTime::createFromFormat('Y-m-d', $end_date_raw)->format('m/d/Y') : '';
        $start_time_raw = $class_fields->class_start_time;
        $start_time = $start_time_raw ? DateTime::createFromFormat('H:i:s', $start_time_raw)->format('g:i A') : '';
        $end_time_raw = $class_fields->class_end_time;
        $end_time = $end_time_raw ? DateTime::createFromFormat('H:i:s', $end_time_raw)->format('g:i A') : '';

        $templateProcessor->setValue("session_title#$block_id", $session_name);
        $templateProcessor->setValue("dow#$block_id", $this->int_to_day($class_fields->class_day_of_week));
        $templateProcessor->setValue("stime#$block_id", $start_time);
        $templateProcessor->setValue("etime#$block_id", $end_time);
        $templateProcessor->setValue("clinic_level#$block_id", $class_fields->class_level);
        $templateProcessor->setValue("cap#$block_id", $class_fields->class_capacity);
        $templateProcessor->setValue("age_group#$block_id", $age_group);
        $templateProcessor->setValue("sdate#$block_id", $start_date);
        $templateProcessor->setValue("edate#$block_id", $end_date);

        // TODO: Add instructors
        $templateProcessor->setValue("insts#$block_id", '');
        $templateProcessor->setValue("skipped_clinics#$block_id", '');
        $templateProcessor->setValue("session_short_code#$block_id", '');

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

        while ($idx < 27) {
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
}
