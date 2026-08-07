<?php

if (!defined('ABSPATH')) {
    exit;
}

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
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

    /**
     * "555-1234/555-5678" from a family's raw phone_numbers JSON column -
     * same decoding Usctdp_Mgmt_Family_Row does, just without needing a
     * whole Row/Query round trip for it - see add_attendance_table()/
     * add_waitlist_table(), which get this straight off a JOIN.
     */
    private function format_phone_numbers($phone_numbers_json)
    {
        if (empty($phone_numbers_json)) {
            return '';
        }
        $numbers = json_decode($phone_numbers_json);
        if (empty($numbers)) {
            return '';
        }
        return implode('/', $numbers);
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
        [$phpWord, $section] = $this->new_roster_document();
        $this->add_clinic_roster_block($section, $clinic_id);
        return $phpWord;
    }

    public function generate_tournament_roster($tournament_id)
    {
        [$phpWord, $section] = $this->new_roster_document();
        $this->add_tournament_roster_block($section, $tournament_id);
        return $phpWord;
    }

    public function generate_session_roster($session_id)
    {
        return $this->generate_roster_for_sessions([$session_id]);
    }

    /**
     * Builds one roster document spanning every activity across all of the
     * given sessions. Each activity block already stamps its own session's
     * title (see add_clinic_roster_block/add_tournament_roster_block), so
     * blocks from different sessions can simply be appended in order - no
     * template changes needed to support multi-session rosters.
     *
     * Built with PhpWord's object-model API (PhpWord/Section/Table/Cell)
     * rather than TemplateProcessor. TemplateProcessor fills a document by
     * repeatedly doing macro string-replacement over the ENTIRE shared
     * document XML, so stamping N activities into one growing document costs
     * O(N^2) - fine for one or two activities, but it's what made rosters for
     * a few dozen activities slow enough to hit the request's execution time
     * limit. Building the document as an in-memory element tree and
     * serializing once avoids that; see add_roster_activity_block() for the
     * per-activity content, hand-ported from templates/roster_template.docx.
     *
     * Each block's fixed row padding keeps it close to a page tall, but
     * "close to" isn't "exactly" - actual content (e.g. an instructor list
     * long enough to wrap) still nudges real height up or down a bit, so a
     * shorter-than-usual block can leave enough leftover room on the page
     * for the next one to start creeping onto it. An explicit page break
     * between blocks is what actually guarantees one activity per page,
     * rather than leaning on padding alone to get there by coincidence.
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

        [$phpWord, $section] = $this->new_roster_document();
        $is_first_block = true;
        foreach ($activity_items as $item) {
            if ($item->type !== 'clinic' && $item->type !== 'tournament') {
                Usctdp_Mgmt::logger()->log_info(
                    'Skipping roster block for unsupported activity type: ' . $item->type . ' (activity ' . $item->id . ')'
                );
                continue;
            }

            if (!$is_first_block) {
                $section->addPageBreak();
            }
            $is_first_block = false;

            if ($item->type === 'clinic') {
                $this->add_clinic_roster_block($section, $item->id);
            } else {
                $this->add_tournament_roster_block($section, $item->id);
            }
        }
        return $phpWord;
    }

    /**
     * Generates and uploads the roster for a single session, and only that
     * session - no roster-group inference. Persisted via the shared
     * usctdp_roster_link table (entity_id = session id). Use
     * generate_and_upload_roster_group() to generate a roster group's
     * (possibly multi-session) document instead.
     */
    public function generate_and_upload_session_roster($session_id, $title)
    {
        $document = $this->generate_session_roster($session_id);
        return $this->upload_to_google_drive($document, $session_id, $title);
    }

    /**
     * Generates and uploads the combined roster for every member session of
     * a roster group, persisted directly on the usctdp_roster_group row -
     * see upload_roster_group_document(). Throws Roster_Group_Exception if
     * the group doesn't exist or has no member sessions to build a roster
     * from.
     */
    public function generate_and_upload_roster_group($roster_group_id)
    {
        $group_query = new Usctdp_Mgmt_Roster_Group_Query();
        $roster_group = $group_query->get_group($roster_group_id);
        if (!$roster_group) {
            throw new Roster_Group_Exception('Roster group not found.');
        }

        $member_session_ids = $group_query->get_member_session_ids($roster_group_id);
        if (empty($member_session_ids)) {
            throw new Roster_Group_Exception('This roster has no sessions to generate a document from.');
        }

        $document = $this->generate_roster_for_sessions($member_session_ids);
        $title = $roster_group->name ?: 'Untitled Roster';
        return $this->upload_roster_group_document($document, $roster_group, $title);
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
     * Pure Drive create-or-update call: writes $document's content to
     * $existing_drive_id if given, otherwise creates a new file. Doesn't
     * know or care how the caller persists the resulting file id.
     *
     * $document is either a TemplateProcessor (financial statements, still
     * template-based) or a PhpWord (rosters, built via the object-model API
     * - see generate_roster_for_sessions()); each saves differently.
     */
    private function upload_document_to_drive($document, $existing_drive_id, $title)
    {
        $client = $this->create_google_client();
        $drive = new Drive($client);
        $destinationFolderId = env('GOOGLE_DRIVE_FOLDER_ID');

        ob_start();
        if ($document instanceof TemplateProcessor) {
            $document->saveAs('php://output');
        } else {
            IOFactory::createWriter($document, 'Word2007')->save('php://output');
        }
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

    /**
     * Fresh PhpWord + a single Section, styled to match
     * templates/roster_template.docx's page setup (see
     * add_roster_activity_block() for the per-activity content).
     */
    private function new_roster_document()
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);
        $section = $phpWord->addSection([
            'marginTop' => 200,
            'marginBottom' => 200,
            'marginLeft' => 432,
            'marginRight' => 432,
            'headerHeight' => 720,
            'footerHeight' => 720,
        ]);
        return [$phpWord, $section];
    }

    private function add_clinic_roster_block($section, $clinic_id)
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

        $activity_meta = json_decode($clinic_fields->clinic_meta, true);
        if (isset($activity_meta['session_title'])) {
            $session_title = $activity_meta['session_title'];
        } else {
            $session_no_parens = trim(preg_replace('/\([^)]*\)/', '', $session_name));
            $session_title = $session_no_parens . ": " . $product_name;
        }

        $start_date_raw = $clinic_fields->session_start_date;
        $start_date = $start_date_raw ? DateTime::createFromFormat('Y-m-d', $start_date_raw)->format('m/d/Y') : '';
        $end_date_raw = $clinic_fields->session_end_date;
        $end_date = $end_date_raw ? DateTime::createFromFormat('Y-m-d', $end_date_raw)->format('m/d/Y') : '';

        $start_time_raw = $clinic_fields->clinic_start_time;
        $start_time = $start_time_raw ? DateTime::createFromFormat('H:i:s', $start_time_raw)->format('g:i A') : '';
        $end_time_raw = $clinic_fields->clinic_end_time;
        $end_time = $end_time_raw ? DateTime::createFromFormat('H:i:s', $end_time_raw)->format('g:i A') : '';

        $this->add_roster_activity_block($section, $clinic_id, [
            'session_title' => $session_title,
            'dow' => $this->int_to_day($clinic_fields->clinic_day_of_week),
            'stime' => $start_time,
            'etime' => $end_time,
            'age_group' => $age_group,
            'level' => $clinic_fields->clinic_level,
            'cap' => $clinic_fields->clinic_capacity,
            'sdate' => $start_date,
            'edate' => $end_date,
            'insts' => $this->get_instructor_names($clinic_id),
            'skipped_clinics' => '',
            'session_short_code' => '',
        ]);
    }

    private function add_tournament_roster_block($section, $tournament_id)
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
        $age_group = ucfirst($tournament_fields->product_age_group);

        $start_date_raw = $tournament_fields->tournament_start_date;
        $start_date = $start_date_raw ? DateTime::createFromFormat('Y-m-d', $start_date_raw)->format('m/d/Y') : '';
        $end_date_raw = $tournament_fields->tournament_start_date_addtl;
        $end_date = $end_date_raw ? DateTime::createFromFormat('Y-m-d', $end_date_raw)->format('m/d/Y') : '';

        $this->add_roster_activity_block($section, $tournament_id, [
            'session_title' => $tournament_fields->session_name,
            'dow' => '',
            'stime' => '',
            'etime' => '',
            'age_group' => $age_group,
            'level' => $tournament_fields->activity_level,
            'cap' => $tournament_fields->activity_capacity,
            'sdate' => $start_date,
            'edate' => $end_date,
            'insts' => $this->get_instructor_names($tournament_id),
            'skipped_clinics' => '',
            'session_short_code' => '',
        ]);
    }

    /**
     * Renders one activity's roster block (the repeating "roster" block in
     * templates/roster_template.docx) directly as PhpWord table/cell/text
     * elements - structure, fonts, sizes and borders hand-ported from that
     * template's document.xml. Any visual change to the template needs to be
     * re-ported here too; there's no longer a live link between the two.
     *
     * On borders: Google Docs' docx export writes fully-explicit border
     * properties on every single cell, and a cell's own border always wins
     * over the table's default for that side. Reading the template XML shows
     * nearly every cell in this block (and the whole waitlist table) turns
     * its own borders off ("nil") even though the table-level default
     * declares one - so the *visible* result is much sparser than those
     * declarations suggest: only the title cell (row 1) is actually boxed;
     * nothing else in the block has a border. That's what's reproduced
     * below - the table itself is left with no declared border at all
     * (a cell with no border override falls back to the table's default, so
     * leaving the table style borderless is what keeps rows 2-5 clean; only
     * row 1's cell gets an explicit border).
     *
     * On the grid: the template's underlying grid is 5 columns (3840, 1920,
     * 1920, 1920, 1920), with different rows merging different spans of it
     * via gridSpan. PhpWord's Word2007 writer doesn't compute <w:tblGrid>
     * from a table style - it derives it from whichever *row* has the most
     * actual cell objects (see Element\Table::findFirstDefinedCellWidths()),
     * oblivious to gridSpan. None of the 5 rows below has 5 real (unmerged)
     * cells, so that row would end up being row 2 (4 real cells: schedule,
     * level, date labels, date values), collapsing the grid to 4 columns:
     * [5760, 1920, 1920, 1920] - i.e. the template's first two columns
     * (3840 + 1920) merged into one. Every row happens to divide cleanly on
     * that 4-column grid *except* row 4 (attendance total / signature),
     * which the template splits at 3840 instead of 5760; that one split is
     * nudged to match (5760 / 5760) rather than fighting the writer with an
     * invisible spacer row just to preserve an exact 1/3-2/3 proportion on a
     * signature line.
     */
    private function add_roster_activity_block($section, $activity_id, array $fields)
    {
        $table = $section->addTable([
            'width' => 11520,
            'unit' => 'dxa',
            'layout' => 'fixed',
        ]);

        // Row 1: session title, boxed.
        $row1 = $table->addRow(432);
        $titleCell = $row1->addCell(11520, array_merge(
            ['gridSpan' => 4, 'vAlign' => 'center'],
            $this->border_box(4)
        ));
        $titleCell->addText($fields['session_title'], ['name' => 'Arial', 'bold' => true, 'size' => 14], ['alignment' => 'center']);

        // Row 2: schedule / instructors / level / dates. This row's 4 cells
        // (widths 5760/1920/1920/1920) are what define the table's actual
        // grid - see the class doc comment above.
        $row2 = $table->addRow(300);

        $scheduleCell = $row2->addCell(5760);
        $scheduleCell->addText(
            "Clinic: {$fields['dow']} {$fields['stime']} - {$fields['etime']}",
            ['bold' => true, 'size' => 11],
            ['spaceBefore' => 120]
        );
        $scheduleCell->addText(
            "Instructor(s): {$fields['insts']}",
            ['bold' => true, 'italic' => true, 'size' => 9],
            ['spaceBefore' => 0, 'spaceAfter' => 0]
        );
        $scheduleCell->addText(
            $fields['skipped_clinics'],
            ['italic' => true, 'size' => 9],
            ['spaceBefore' => 0, 'spaceAfter' => 0, 'alignment' => 'left']
        );

        $levelCell = $row2->addCell(1920);
        $levelCell->addText(
            "{$fields['age_group']} Level {$fields['level']}",
            ['bold' => true, 'size' => 9],
            ['spaceBefore' => 120]
        );
        $levelCell->addText('', ['bold' => true, 'size' => 11]);

        $dateLabelCell = $row2->addCell(1920);
        $dateLabelCell->addText('Start Date:', ['italic' => true, 'size' => 9], ['spaceBefore' => 120]);
        $dateLabelCell->addText('End Date:', ['italic' => true, 'size' => 9]);
        $dateLabelCell->addText('Class Limit:', ['italic' => true, 'size' => 9]);

        $dateValueCell = $row2->addCell(1920);
        $dateValueCell->addText((string) $fields['sdate'], ['size' => 9], ['spaceBefore' => 120]);
        $dateValueCell->addText((string) $fields['edate'], ['size' => 9]);
        $dateValueCell->addText((string) $fields['cap'], ['size' => 9]);

        // Row 3: attendance table (borderless in the template).
        $row3 = $table->addRow(1200);
        $attendanceCell = $row3->addCell(11520, ['gridSpan' => 4]);
        $this->add_attendance_table($attendanceCell, $activity_id);

        // Row 4: attendance total / signature line. Split 50/50 rather than
        // the template's 3840/7680 - see the class doc comment above.
        $row4 = $table->addRow(300);
        $row4->addCell(5760)->addText('Attendance Total ___________', ['bold' => true, 'size' => 10]);
        $row4->addCell(5760, ['gridSpan' => 3])->addText(
            "Attendance Taker\u{2019}s Signature ____________________",
            ['bold' => true, 'size' => 10]
        );

        // Row 5: waitlist / absentees & notes.
        //
        // Note on the spacing values below: when spacingLineRule is 'auto',
        // PhpWord's writer treats 'spacing' as *extra* space added on top of
        // a single line (it adds a fixed 240-twip baseline - see
        // Writer\Word2007\Style\Spacing::write()), not the final w:line
        // value. The template's own XML declares w:line values of 240 (the
        // two headings below - i.e. no extra, single spacing) and 276 (the
        // notes lines - 36 twips of extra), so that's 0 and 36 here, not
        // 240 and 276 - passing the template's raw values directly nearly
        // doubles every one of these lines and was what pushed row 5 (and
        // the page as a whole) taller than intended.
        $row5 = $table->addRow(2547);
        $waitlistCell = $row5->addCell(5760);
        $waitlistCell->addText('Waitlist', ['bold' => true, 'size' => 10], ['spacing' => 0, 'spacingLineRule' => 'auto']);
        $this->add_waitlist_table($waitlistCell, $activity_id);

        $notesCell = $row5->addCell(5760, ['gridSpan' => 3]);
        $notesCell->addText('Absentees/Notes', ['bold' => true, 'size' => 10], ['spacing' => 0, 'spacingLineRule' => 'auto']);
        for ($i = 0; $i < 6; $i++) {
            $notesCell->addText(
                '________________________________________________',
                ['size' => 10],
                ['spacing' => 36, 'spacingLineRule' => 'auto']
            );
        }

        $section->addText(
            $fields['session_short_code'],
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => 'center']
        );
    }

    /**
     * Attendance table for one activity: a header row plus a fixed 31 data
     * rows, blank-padded when there aren't 31 registrants, so every
     * activity's block takes up the same page space regardless of how many
     * students actually registered - this padding is deliberate, not
     * something left over from the template.
     *
     * Used to be a query per registrant (student, then family) - now one
     * JOIN query for the whole activity, see
     * Usctdp_Mgmt_Registration_Query::get_roster_students(). That query
     * inner-joins student/family, so a registration whose student or family
     * row has gone missing is silently left off the roster rather than
     * throwing like this used to - a real behavior change, but throwing
     * per-row is exactly the per-row cost this was rewritten to avoid.
     */
    private function add_attendance_table($cell, $activity_id)
    {
        $registration_query = new Usctdp_Mgmt_Registration_Query();
        $registrants = $registration_query->get_roster_students($activity_id);

        // Every row below declares all 6 cells explicitly (no gridSpan), so
        // the writer's per-row-derived <w:tblGrid> (see the class doc
        // comment on add_roster_activity_block()) naturally comes out right
        // without needing anything special here.
        $columnWidths = [1008, 2448, 2448, 864, 864, 3690];
        $table = $cell->addTable(['width' => 11322, 'unit' => 'dxa', 'layout' => 'fixed']);

        $headerStyle = ['bold' => true, 'underline' => 'single', 'size' => 8.5];
        $headerRow = $table->addRow(300);
        foreach (['Attnd?', 'Last', 'First', 'Age', 'Level', 'Phone Number(s)'] as $i => $label) {
            $headerRow->addCell($columnWidths[$i])->addText($label, $headerStyle);
        }

        $dataStyle = ['size' => 8.5];
        $idx = 1;
        foreach ($registrants as $registrant) {
            $row = $table->addRow(300);
            $row->addCell($columnWidths[0])->addText('___' . $idx, $dataStyle);
            $row->addCell($columnWidths[1])->addText((string) $registrant->student_last, $dataStyle);
            $row->addCell($columnWidths[2])->addText((string) $registrant->student_first, $dataStyle);
            $row->addCell($columnWidths[3])->addText((string) $registrant->student_age, $dataStyle);
            $row->addCell($columnWidths[4])->addText((string) $registrant->student_level, $dataStyle);
            $row->addCell($columnWidths[5])->addText($this->format_phone_numbers($registrant->family_phone_numbers), $dataStyle);
            $idx++;
        }
        while ($idx <= 32) {
            $row = $table->addRow(300);
            foreach ($columnWidths as $width) {
                $row->addCell($width)->addText('', $dataStyle);
            }
            $idx++;
        }
    }

    /**
     * Waitlist table for one activity: up to the first 10 waitlisters
     * (oldest first, no blank-row padding - unlike the attendance table
     * above, this one was never padded). See
     * Usctdp_Mgmt_Waitlist_Query::get_roster_waitlist() for the "first 10,
     * oldest first" query. Omitted entirely when there are none, since a
     * table with zero rows isn't something Word can open.
     */
    private function add_waitlist_table($cell, $activity_id)
    {
        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query();
        $waitlisters = $waitlist_query->get_roster_waitlist($activity_id, 10);
        if (empty($waitlisters)) {
            return;
        }

        $columnWidths = [1365, 1530, 2640];
        $table = $cell->addTable(['width' => 5535, 'unit' => 'dxa', 'layout' => 'fixed']);

        $style = ['bold' => true, 'size' => 8.5];
        $cellStyle = ['vAlign' => 'top'];
        foreach ($waitlisters as $waitlister) {
            $row = $table->addRow();
            $row->addCell($columnWidths[0], $cellStyle)->addText((string) $waitlister->student_last, $style);
            $row->addCell($columnWidths[1], $cellStyle)->addText((string) $waitlister->student_first, $style);
            $row->addCell($columnWidths[2], $cellStyle)->addText($this->format_phone_numbers($waitlister->family_phone_numbers), $style);
        }
    }

    private function border_box($size)
    {
        return [
            'borderTopSize' => $size, 'borderTopColor' => '000000', 'borderTopStyle' => 'single',
            'borderLeftSize' => $size, 'borderLeftColor' => '000000', 'borderLeftStyle' => 'single',
            'borderBottomSize' => $size, 'borderBottomColor' => '000000', 'borderBottomStyle' => 'single',
            'borderRightSize' => $size, 'borderRightColor' => '000000', 'borderRightStyle' => 'single',
        ];
    }

}
