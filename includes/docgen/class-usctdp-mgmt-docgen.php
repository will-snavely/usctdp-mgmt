<?php

if (! defined('ABSPATH')) {
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

    private function get_class_registrations($class_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $class_id
        ]);

        $result = [];
        foreach ($reg_query->items as $item) {
            $result[] = [
                'student_id' => $item->student_id,
                'activity_id' => $item->activity_id,
                'starting_level' => $item->starting_level,
                'balance' => $item->balance,
                'notes' => $item->notes
            ];
        }
        return $result;
    }

    private function get_drive_id($post_id)
    {
        $reg_query = new Usctdp_Mgmt_Roster_Link_Query([
            'entity_id' => $post_id,
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
        $activity_query = new Usctdp_Mgmt_Activity_Link_Query([
            'session_id' => $session_id
        ]);
        $templateProcessor = new TemplateProcessor($this->template_file);
        $class_ids = [];
        foreach ($activity_query->items as $item) {
            $class_ids[] = $item->activity_id;
        }
        $templateProcessor->cloneBlock('roster', count($class_ids), true, true);
        foreach ($class_ids as $index => $class_id) {
            $this->generate_class_roster_impl($templateProcessor, $class_id, $index + 1);
        }
        return $templateProcessor;
    }

    public function generate_clinic_roster($clinic_id, $session_id)
    {
        $activity_query = new Usctdp_Mgmt_Activity_Link_Query([
            'clinic_id' => $clinic_id,
            'session_id' => $session_id
        ]);
        $templateProcessor = new TemplateProcessor($this->template_file);
        $class_ids = [];
        foreach ($activity_query->items as $item) {
            $class_ids[] = $item->activity_id;
        }
        $templateProcessor->cloneBlock('roster', count($class_ids), true, true);
        foreach ($class_ids as $index => $class_id) {
            $this->generate_class_roster_impl($templateProcessor, $class_id, $index + 1);
        }
        return $templateProcessor;
    }

    public function upload_to_google_drive($templateProcessor, $post_id)
    {
        $client = $this->create_google_client();
        $drive = new Drive($client);

        $drive_id = $this->get_drive_id($post_id);
        $destinationFolderId = env('GOOGLE_DRIVE_FOLDER_ID');

        ob_start();
        $templateProcessor->saveAs('php://output');
        $content = ob_get_clean();

        $metadata_args = [
            'name' => 'Roster: ' . get_the_title($post_id),
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
                'entity_id' => $post_id,
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

    private function age_from_birthdate($birthdate)
    {
        $today = new DateTime('today');
        $age = $birthdate->diff($today)->y;
        return $age;
    }

    private function generate_class_roster_impl($templateProcessor, $class_id, $block_id)
    {
        $class_fields = get_fields($class_id);
        $registrations = $this->get_class_registrations($class_id);
        $session_name = get_field('name', $class_fields['session']);
        $age_group = get_field('age_group', $class_fields['clinic']);
        $start_date_raw = get_field('start_date', $class_fields['session']);
        $start_date = $start_date_raw ? DateTime::createFromFormat('Ymd', $start_date_raw)->format('m/d/Y') : '';
        $end_date_raw = get_field('end_date', $class_fields['session']);
        $end_date = $end_date_raw ? DateTime::createFromFormat('Ymd', $end_date_raw)->format('m/d/Y') : '';
        $templateProcessor->setValue("session_title#$block_id", $session_name);
        $templateProcessor->setValue("dow#$block_id", $class_fields['day_of_week']);
        $templateProcessor->setValue("stime#$block_id", $class_fields['start_time']);
        $templateProcessor->setValue("etime#$block_id", $class_fields['end_time']);
        $templateProcessor->setValue("clinic_level#$block_id", $class_fields['level']);
        $templateProcessor->setValue("cap#$block_id", $class_fields['capacity']);
        $templateProcessor->setValue("age_group#$block_id", $age_group);
        $templateProcessor->setValue("sdate#$block_id", $start_date);
        $templateProcessor->setValue("edate#$block_id", $end_date);

        if (isset($class_fields['instructors'])) {
            $templateProcessor->setValue("insts#$block_id", '');
        } else {
            $templateProcessor->setValue("insts#$block_id", '');
        }

        if (isset($class_fields['instructors'])) {
            $templateProcessor->setValue("insts#$block_id", '');
        } else {
            $templateProcessor->setValue("insts#$block_id", '');
        }
        $templateProcessor->setValue("skipped_clinics#$block_id", '');
        $templateProcessor->setValue("session_short_code#$block_id", '');

        $student_data = [];
        $idx = 1;
        foreach ($registrations as $registration) {
            $family = get_field('field_usctdp_student_family', $registration['student_id']);
            $student_id = $registration['student_id'];
            $phone = get_field('field_usctdp_family_phone_number', $family);
            $first_name = get_field('field_usctdp_student_first_name', $student_id);
            $last_name = get_field('field_usctdp_student_last_name', $student_id);
            $student_birthdate_raw = get_field('field_usctdp_student_birth_date', $student_id);
            $student_birthdate = DateTime::createFromFormat('Ymd', $student_birthdate_raw);
            $level = $registration['starting_level'];
            $student_age = $this->age_from_birthdate($student_birthdate);

            $student_data[] = [
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
            $student_data[] = [
                'att#' . $block_id => '',
                'last#' . $block_id => '',
                'first#' . $block_id => '',
                'age#' . $block_id => '',
                'lvl#' . $block_id => '',
                'phones#' . $block_id => ''
            ];
            $idx++;
        }
        $templateProcessor->cloneRowAndSetValues("att#$block_id", $student_data);
    }
}
