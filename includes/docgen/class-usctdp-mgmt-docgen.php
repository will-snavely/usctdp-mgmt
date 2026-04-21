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

    private function int_to_age_group($age_group)
    {
        switch ($age_group) {
            case 1:
                return 'Junior';
            case 2:
                return 'Adult';
            default:
                return 'Unknown';
        }
    }

    private function get_clinic_registrations($clinic_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $clinic_id
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

    public function generate_clinic_roster($clinic_id)
    {
        $templateProcessor = new TemplateProcessor($this->roster_template_file);
        $templateProcessor->cloneBlock('roster', 1, true, true);
        $this->generate_clinic_roster_impl($templateProcessor, $clinic_id, '1');
        return $templateProcessor;
    }

    public function generate_session_roster($session_id)
    {
        $activity_query = new Usctdp_Mgmt_Activity_Query([
            'session_id' => $session_id
        ]);
        $templateProcessor = new TemplateProcessor($this->roster_template_file);
        $templateProcessor->cloneBlock('roster', count($activity_query->items), true, true);
        $index = 1;
        foreach ($activity_query->items as $item) {
            if ($item->type === 'clinic') {
                $this->generate_clinic_roster_impl($templateProcessor, $item->id, $index);
            }
            $index++;
        }
        return $templateProcessor;
    }

    public function generate_purchase_statement($purchase_id)
    {
        $templateProcessor = new TemplateProcessor($this->statement_template_file);
        $this->generate_statement_impl($templateProcessor, $purchase_id);
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

    private function generate_statement_impl($templateProcessor, $purchase_id)
    {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $purchase_query = new Usctdp_Mgmt_Purchase_Query(); 
        $purchase_data = $purchase_query->get_purchase_data([
            'purchase_id' => $purchase_id
        ])['data'];
        if (empty($purchase_data)) {
            throw new ErrorException('Purchase not found');
        }
        $purchase_fields = $purchase_data[0];

        $templateProcessor->setValue("statement_title", "Financial Statement");
        $templateProcessor->setValue("family_name", $purchase_fields->family_name);
        $templateProcessor->setValue("student_name", $purchase_fields->student_first . ' ' . $purchase_fields->student_last);

        if($purchase_fields->purchase_type)
        $templateProcessor->setValue("activity_name", $purchase_fields->activity_name);

        $ledger_query = new Usctdp_Mgmt_Ledger_Query();
        $ledger_events = $ledger_query->get_ledger_events([
            'purchase_id' => $purchase_id,
            'account' => $purchase_fields->purchase_type . '_fees'
        ])['data'];
        $runningBalance = 0;
        $statement_rows = [];
        foreach ($ledger_events as $item) {
            $charge = floatval($item->charge_amount);
            $payment = floatval($item->payment_amount);
            $runningBalance += ($charge - $payment);
            $item->calculated_balance = $runningBalance;
            $date = new DateTime($item->event_date);
            $date->setTimezone(new DateTimeZone('America/New_York'));
            $formatted_date = $date->format('m/d/y');
            $statement_rows[] = [
                'date' => $formatted_date,
                'event' => $item->entry_type,
                'description' => $item->event_description,
                'debit' => $formatter->formatCurrency($charge, 'USD'),
                'credit' => $formatter->formatCurrency($payment, 'USD'),
                'balance' => $formatter->formatCurrency($runningBalance, 'USD')
            ];
        }
        $templateProcessor->cloneRowAndSetValues("date", $statement_rows);

        if ($runningBalance <= 0) {
            $status = 'PAID';
        } else {
            $status = 'BALANCE DUE';
        }

        $templateProcessor->setValue("statement_status", $status);
        $templateProcessor->setValue(
            "statement_balance", 
            $formatter->formatCurrency($runningBalance, 'USD')
        );
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
        $registrations = $this->get_clinic_registrations($clinic_id);
        $session_name = $clinic_fields->session_name;
        $age_group = $clinic_fields->product_age_group;

        $start_date_raw = $clinic_fields->session_start_date;
        $start_date = $start_date_raw ? DateTime::createFromFormat('Y-m-d', $start_date_raw)->format('m/d/Y') : '';
        $end_date_raw = $clinic_fields->session_end_date;
        $end_date = $end_date_raw ? DateTime::createFromFormat('Y-m-d', $end_date_raw)->format('m/d/Y') : '';

        $start_time_raw = $clinic_fields->clinic_start_time;
        $start_time = $start_time_raw ? DateTime::createFromFormat('H:i:s', $start_time_raw)->format('g:i A') : '';
        $end_time_raw = $clinic_fields->clinic_end_time;
        $end_time = $end_time_raw ? DateTime::createFromFormat('H:i:s', $end_time_raw)->format('g:i A') : '';

        $templateProcessor->setValue("session_title#$block_id", $session_name);
        $templateProcessor->setValue("dow#$block_id", $this->int_to_day($clinic_fields->clinic_day_of_week));
        $templateProcessor->setValue("stime#$block_id", $start_time);
        $templateProcessor->setValue("etime#$block_id", $end_time);
        $templateProcessor->setValue("clinic_level#$block_id", $clinic_fields->clinic_level);
        $templateProcessor->setValue("cap#$block_id", $clinic_fields->clinic_capacity);
        $templateProcessor->setValue("age_group#$block_id", $this->int_to_age_group($age_group));
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
