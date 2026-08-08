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
    // Roster page setup (see new_roster_document()). Twips (1/20 pt) unless
    // noted otherwise.
    const ROSTER_MARGIN_TOP_BOTTOM = 200;
    const ROSTER_MARGIN_LEFT_RIGHT = 432;
    const ROSTER_HEADER_FOOTER_HEIGHT = 720;

    // Table 1 - the per-activity block built by add_roster_activity_block().
    // Its grid is 4 columns: one wide "schedule" column plus three narrower
    // ones (see that method's doc comment for why 4 and not the template's
    // original 5). Row heights are "at least" - real content can still grow
    // a row taller than this.
    const ROSTER_TABLE_WIDTH = 11520;
    const ROSTER_COL_WIDE = 5760;
    const ROSTER_COL_NARROW = 1920;
    const ROSTER_ROW_TITLE_HEIGHT = 432;
    const ROSTER_ROW_INFO_HEIGHT = 300;
    const ROSTER_ROW_ATTENDANCE_HEIGHT = 1200;
    const ROSTER_ROW_FOOTER_HEIGHT = 300;
    const ROSTER_ROW_WAITLIST_HEIGHT = 2547;
    const ROSTER_TITLE_BORDER_SIZE = 4; // eighths of a point
    const ROSTER_FIELD_SPACE_BEFORE = 120; // gap above each label group in row 2
    const BORDER_COLOR = '000000';

    // Font sizes (points).
    const FONT_SIZE_TITLE = 14;
    const FONT_SIZE_SCHEDULE = 10;
    const FONT_SIZE_DETAIL = 8.5;
    const FONT_SIZE_FOOTER = 10;    
    const FONT_SIZE_TABLE = 8.5;

    // Attendance table, nested inside Table 1's row 3 - see
    // add_attendance_table(). Column order: Attnd?, Last, First, Age, Level,
    // Phone Number(s).
    const ATTENDANCE_TABLE_WIDTH = 11322;
    const ATTENDANCE_COL_WIDTHS = [1008, 2448, 2448, 864, 864, 3690];
    const ATTENDANCE_ROW_HEIGHT = 300;
    // Total data rows (real registrants + blank padding), not counting the
    // header row - see add_attendance_table()'s doc comment for why this is
    // padded to a fixed count at all.
    const ATTENDANCE_DATA_ROWS = 32;

    // Waitlist table, nested inside Table 1's row 5 - see
    // add_waitlist_table(). Column order: Last, First, Phone.
    const WAITLIST_TABLE_WIDTH = 5535;
    const WAITLIST_COL_WIDTHS = [1365, 1530, 2640];
    const WAITLIST_MAX_ENTRIES = 10;

    const NOTES_LINE_COUNT = 6;

    // See auto_line_spacing() - PhpWord's 'auto' line-spacing rule adds this
    // many twips as an implicit baseline on top of whatever you pass it.
    const AUTO_LINE_SPACING_BASELINE = 240;

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
     * "(555)-123-4567/(555)-987-6543" from a family's raw phone_numbers
     * JSON column - same decoding Usctdp_Mgmt_Family_Row does, just without
     * needing a whole Row/Query round trip for it - see
     * add_attendance_table()/add_waitlist_table(), which get this straight
     * off a JOIN. Each number is independently normalized (see
     * format_phone_number()) - raw stored numbers come from years of manual
     * entry/import and aren't consistently formatted (some with dashes,
     * some without, some with a leading 1, etc.).
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
        return implode('/', array_map([$this, 'format_phone_number'], $numbers));
    }

    /**
     * Normalizes one US phone number to "(555)-123-4567" - strips
     * everything but digits, drops a leading "1" country code if present,
     * then reformats. Falls back to the original raw string, unchanged, for
     * anything that isn't cleanly a 10-digit US number once stripped (a
     * partial/malformed entry, an extension, an international number) -
     * printing something wrong-looking-but-recognizable on the roster is
     * better than silently mangling a real number into the wrong shape.
     */
    private function format_phone_number($raw)
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) !== 10) {
            return (string) $raw;
        }
        return sprintf(
            '(%s)-%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 4)
        );
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
     *
     * A clinic that's been merged into a shared reservation group (see
     * Usctdp_Mgmt_Reservation_Group_Table) only ever produces ONE block
     * here, not one per merged activity - the first member encountered
     * renders the group's combined block (add_roster_block_for_activity()),
     * and every other activity sharing that same group is skipped rather
     * than printed again.
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
        $rendered_group_ids = [];
        foreach ($activity_items as $item) {
            if ($item->type !== 'clinic' && $item->type !== 'tournament') {
                Usctdp_Mgmt::logger()->log_info(
                    'Skipping roster block for unsupported activity type: ' . $item->type . ' (activity ' . $item->id . ')'
                );
                continue;
            }

            $group_id = (int) $item->reservation_group_id;
            if (isset($rendered_group_ids[$group_id])) {
                continue;
            }
            $rendered_group_ids[$group_id] = true;

            if (!$is_first_block) {
                $section->addPageBreak();
            }
            $is_first_block = false;

            $this->add_roster_block_for_activity($section, $item);
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
     * Renders one activity's roster block - transparently expanding to the
     * MERGED block for its whole reservation group when that group has more
     * than one clinic member (add_merged_clinic_roster_block()), instead of
     * the plain single-activity block. Single source of truth for "does
     * this activity's group need the merged treatment", used by both
     * generate_roster_for_sessions() (which also has to dedupe - a merged
     * group must only print once, not once per member) and
     * generate_and_upload_reservation_group_roster() (generating a group's
     * roster directly).
     *
     * A tournament is never merged (no tournament equivalent of
     * add_merged_clinic_roster_block() exists - tournaments aren't part of
     * this feature), so it always gets its own plain block regardless of
     * how many activities share its group.
     */
    private function add_roster_block_for_activity($section, $activity)
    {
        if ($activity->type === 'tournament') {
            $this->add_tournament_roster_block($section, $activity->id);
            return;
        }

        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $member_ids = $group_query->get_member_activity_ids($activity->reservation_group_id);
        $clinic_ids = array_values(array_filter($member_ids, function ($member_id) {
            $member = Usctdp_Mgmt_Model::get_activity($member_id);
            return $member && $member->type === 'clinic';
        }));

        if (count($clinic_ids) > 1) {
            $this->add_merged_clinic_roster_block($section, $activity->reservation_group_id, $clinic_ids);
        } else {
            $this->add_clinic_roster_block($section, $activity->id);
        }
    }

    /**
     * Generates and uploads the roster for a reservation group - one
     * document, one block (add_roster_block_for_activity() above decides
     * whether that block is a plain single-clinic block or the merged
     * multi-clinic one). For a solo (unmerged) group this produces exactly
     * what generating that one activity's roster always has - no special
     * case needed.
     *
     * Persisted via the shared usctdp_roster_link table (entity_id =
     * reservation_group_id), same mechanism as
     * generate_and_upload_session_roster() above - deliberately NOT the
     * usctdp_roster_group/upload_roster_group_document() path, which
     * bypasses that table specifically to avoid an id collision with
     * activity/session/family ids already stored there; reservation groups
     * accept that same, already-established convention instead of adding a
     * fourth special case.
     */
    public function generate_and_upload_reservation_group_roster($reservation_group_id)
    {
        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $group = $group_query->get_group($reservation_group_id);
        if (!$group) {
            throw new Reservation_Group_Exception('Reservation group not found.');
        }

        $member_activity_ids = $group_query->get_member_activity_ids($reservation_group_id);
        if (empty($member_activity_ids)) {
            throw new Reservation_Group_Exception('This reservation group has no activities to generate a roster from.');
        }

        [$phpWord, $section] = $this->new_roster_document();
        foreach ($member_activity_ids as $activity_id) {
            $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
            if ($activity && ($activity->type === 'clinic' || $activity->type === 'tournament')) {
                // Every member shares the same reservation_group_id by
                // definition, so add_roster_block_for_activity() always
                // renders the full merged block on the first supported
                // activity it sees - nothing left to do after that.
                $this->add_roster_block_for_activity($section, $activity);
                break;
            }
        }

        $title = $group_query->get_roster_title($reservation_group_id);
        return $this->upload_to_google_drive($phpWord, $reservation_group_id, $title);
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
            'marginTop' => self::ROSTER_MARGIN_TOP_BOTTOM,
            'marginBottom' => self::ROSTER_MARGIN_TOP_BOTTOM,
            'marginLeft' => self::ROSTER_MARGIN_LEFT_RIGHT,
            'marginRight' => self::ROSTER_MARGIN_LEFT_RIGHT,
            'headerHeight' => self::ROSTER_HEADER_FOOTER_HEIGHT,
            'footerHeight' => self::ROSTER_HEADER_FOOTER_HEIGHT,
        ]);
        return [$phpWord, $section];
    }

    /**
     * Derives add_roster_activity_block()'s $fields array from one clinic's
     * data row (Usctdp_Mgmt_Clinic_Query::get_clinic_data()'s shape) -
     * shared by add_clinic_roster_block() (a single clinic's own block) and
     * add_merged_clinic_roster_block() (which uses one clinic - the
     * "primary" one - as the base for a merged group's block, then
     * overrides just the title and instructor list; see that method).
     */
    private function derive_clinic_roster_fields($clinic_fields, $clinic_id)
    {
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

        return [
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
        ];
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

        $fields = $this->derive_clinic_roster_fields($clinic_data['data'][0], $clinic_id);
        $this->add_roster_activity_block($section, $clinic_id, $fields);
    }

    /**
     * One combined block for every clinic sharing a reservation group -
     * same table/cell structure as a normal single-clinic block
     * (add_roster_activity_block() itself is untouched), just fed merged
     * data: the attendance and waitlist tables list registrants/waitlisters
     * from every $clinic_id at once (get_roster_students()/
     * get_roster_waitlist() both accept an array of activity ids - see
     * their doc comments), and the instructor list is the deduplicated
     * union across all of them.
     *
     * Level is also merged - a deduplicated, comma-joined list across every
     * clinic_id, same idea as the instructor list (real schedules do put
     * different-level clinics like "Yellow Ball" and "Yellow Ball Open" on
     * the same court at the same time, so this can't just be one string).
     *
     * Everything else (schedule, age group, dates, capacity) comes from
     * whichever clinic happens to be first in $clinic_ids - merged clinics
     * are assumed to be the same session's same time slot in practice
     * (capacity in particular already IS the shared group's capacity for
     * every member, by construction), so there's nothing meaningful left to
     * reconcile between them beyond the roster, title, instructors, and
     * level.
     *
     * The title is "<session>: <reservation group name>" - same "<session>:
     * <name>" shape a single clinic's block uses (see
     * derive_clinic_roster_fields()), just with the reservation group's
     * name in the second slot instead of one member's own product name,
     * since reservation_group_id is the thing tying these clinics together
     * and should identify the combined roster. Falls back to
     * Usctdp_Mgmt_Reservation_Group_Query::get_roster_title()'s own default
     * (the joined member titles) when the group has no explicit name.
     */
    private function add_merged_clinic_roster_block($section, $reservation_group_id, array $clinic_ids)
    {
        $clinic_query = new Usctdp_Mgmt_Clinic_Query();
        $primary_clinic_id = $clinic_ids[0];
        $clinic_data = $clinic_query->get_clinic_data([
            'id' => $primary_clinic_id,
            'number' => 1
        ]);
        if (empty($clinic_data['data'])) {
            throw new ErrorException('Clinic not found');
        }
        $primary_fields = $clinic_data['data'][0];

        $fields = $this->derive_clinic_roster_fields($primary_fields, $primary_clinic_id);

        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $session_no_parens = trim(preg_replace('/\([^)]*\)/', '', (string) $primary_fields->session_name));
        $fields['session_title'] = $session_no_parens . ': ' . $group_query->get_roster_title($reservation_group_id);

        $instructor_names = [];
        $levels = [];
        if (!empty($primary_fields->clinic_level)) {
            $levels[$primary_fields->clinic_level] = true;
        }
        foreach ($clinic_ids as $clinic_id) {
            foreach (explode(', ', $this->get_instructor_names($clinic_id)) as $name) {
                if ($name !== '') {
                    $instructor_names[$name] = true;
                }
            }

            if ($clinic_id === $primary_clinic_id) {
                continue; // already have its level, above, from $primary_fields
            }
            $other_data = $clinic_query->get_clinic_data(['id' => $clinic_id, 'number' => 1]);
            if (!empty($other_data['data'][0]->clinic_level)) {
                $levels[$other_data['data'][0]->clinic_level] = true;
            }
        }
        $fields['insts'] = implode(', ', array_keys($instructor_names));
        $fields['level'] = implode(', ', array_keys($levels));

        $this->add_roster_activity_block($section, $clinic_ids, $fields);
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
            'width' => self::ROSTER_TABLE_WIDTH,
            'unit' => 'dxa',
            'layout' => 'fixed',
        ]);

        // Row 1: session title, boxed.
        $row1 = $table->addRow(self::ROSTER_ROW_TITLE_HEIGHT);
        $titleCell = $row1->addCell(self::ROSTER_TABLE_WIDTH, array_merge(
            ['gridSpan' => 4, 'vAlign' => 'center'],
            $this->border_box(self::ROSTER_TITLE_BORDER_SIZE)
        ));
        $titleCell->addText(
            $fields['session_title'],
            ['name' => 'Arial', 'bold' => true, 'size' => self::FONT_SIZE_TITLE],
            ['alignment' => 'center']
        );

        // Row 2: schedule / instructors / level / dates. This row's 4 cells
        // (widths WIDE/NARROW/NARROW/NARROW) are what define the table's
        // actual grid - see the class doc comment above.
        $row2 = $table->addRow(self::ROSTER_ROW_INFO_HEIGHT);
        $detailStyle = ['size' => self::FONT_SIZE_DETAIL];
        $detailLabelStyle = ['italic' => true, 'size' => self::FONT_SIZE_DETAIL];
        $fieldStart = ['spaceBefore' => self::ROSTER_FIELD_SPACE_BEFORE];

        $scheduleCell = $row2->addCell(self::ROSTER_COL_WIDE);
        $scheduleCell->addText(
            "Clinic: {$fields['dow']} {$fields['stime']} - {$fields['etime']}",
            ['bold' => true, 'size' => self::FONT_SIZE_SCHEDULE],
            $fieldStart
        );
        $scheduleCell->addText(
            "Instructor(s): {$fields['insts']}",
            ['bold' => true, 'italic' => true, 'size' => self::FONT_SIZE_DETAIL],
            ['spaceBefore' => 0, 'spaceAfter' => 0]
        );
        $scheduleCell->addText(
            $fields['skipped_clinics'],
            $detailLabelStyle,
            ['spaceBefore' => 0, 'spaceAfter' => 0, 'alignment' => 'left']
        );

        $levelCell = $row2->addCell(self::ROSTER_COL_NARROW);
        $levelCell->addText(
            "{$fields['age_group']} Level {$fields['level']}",
            ['bold' => true, 'size' => self::FONT_SIZE_DETAIL],
            $fieldStart
        );
        $levelCell->addText('', ['bold' => true, 'size' => self::FONT_SIZE_SCHEDULE]);

        $dateLabelCell = $row2->addCell(self::ROSTER_COL_NARROW);
        $dateLabelCell->addText('Start Date:', $detailLabelStyle, $fieldStart);
        $dateLabelCell->addText('End Date:', $detailLabelStyle);
        $dateLabelCell->addText('Class Limit:', $detailLabelStyle);

        $dateValueCell = $row2->addCell(self::ROSTER_COL_NARROW);
        $dateValueCell->addText((string) $fields['sdate'], $detailStyle, $fieldStart);
        $dateValueCell->addText((string) $fields['edate'], $detailStyle);
        $dateValueCell->addText((string) $fields['cap'], $detailStyle);

        // Row 3: attendance table (borderless in the template).
        $row3 = $table->addRow(self::ROSTER_ROW_ATTENDANCE_HEIGHT);
        $attendanceCell = $row3->addCell(self::ROSTER_TABLE_WIDTH, ['gridSpan' => 4]);
        $this->add_attendance_table($attendanceCell, $activity_id);

        // Row 4: attendance total / signature line. Split 50/50 rather than
        // the template's 3840/7680 - see the class doc comment above.
        $footerStyle = ['bold' => true, 'size' => self::FONT_SIZE_FOOTER];
        $row4 = $table->addRow(self::ROSTER_ROW_FOOTER_HEIGHT);
        $row4->addCell(self::ROSTER_COL_WIDE)->addText('Attendance Total ___________', $footerStyle);
        $row4->addCell(self::ROSTER_COL_WIDE, ['gridSpan' => 3])->addText(
            "Attendance Taker\u{2019}s Signature ____________________",
            $footerStyle
        );

        // Row 5: waitlist / absentees & notes. See auto_line_spacing() for
        // why the heading/notes-line spacing goes through that helper
        // instead of a plain 'spacing' value.
        $row5 = $table->addRow(self::ROSTER_ROW_WAITLIST_HEIGHT);
        $headingStyle = ['bold' => true, 'size' => self::FONT_SIZE_FOOTER];
        $headingSpacing = $this->auto_line_spacing(240); // single-spaced

        $waitlistCell = $row5->addCell(self::ROSTER_COL_WIDE);
        $waitlistCell->addText('Waitlist', $headingStyle, $headingSpacing);
        $this->add_waitlist_table($waitlistCell, $activity_id);

        $notesCell = $row5->addCell(self::ROSTER_COL_WIDE, ['gridSpan' => 3]);
        $notesCell->addText('Absentees/Notes', $headingStyle, $headingSpacing);
        $notesLineSpacing = $this->auto_line_spacing(276);
        for ($i = 0; $i < self::NOTES_LINE_COUNT; $i++) {
            $notesCell->addText(
                '________________________________________________',
                ['size' => self::FONT_SIZE_FOOTER],
                $notesLineSpacing
            );
        }

        $section->addText(
            $fields['session_short_code'],
            ['name' => 'Arial', 'size' => self::FONT_SIZE_FOOTER],
            ['alignment' => 'center']
        );
    }

    /**
     * PhpWord's 'spacingLineRule' => 'auto' treats the 'spacing' value as
     * *extra* space added on top of a fixed AUTO_LINE_SPACING_BASELINE-twip
     * single-line baseline, not the final w:line value (see
     * Writer\Word2007\Style\Spacing::write()). This takes the actual w:line
     * value you want - i.e. what you'd read directly off a template's XML -
     * and does that conversion, so call sites work in those familiar units
     * instead of pre-computing "minus 240" by hand. Passing a template's raw
     * w:line value straight through as 'spacing' silently doubles it.
     */
    private function auto_line_spacing($target_line_twips)
    {
        return [
            'spacing' => $target_line_twips - self::AUTO_LINE_SPACING_BASELINE,
            'spacingLineRule' => 'auto',
        ];
    }

    /**
     * Attendance table for one activity: a header row plus a fixed
     * ATTENDANCE_DATA_ROWS data rows, blank-padded when there aren't that
     * many registrants, so every activity's block takes up the same page
     * space regardless of how many students actually registered - this
     * padding is deliberate, not something left over from the template.
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
        $columnWidths = self::ATTENDANCE_COL_WIDTHS;
        $table = $cell->addTable(['width' => self::ATTENDANCE_TABLE_WIDTH, 'unit' => 'dxa', 'layout' => 'fixed']);

        $headerStyle = ['bold' => true, 'underline' => 'single', 'size' => self::FONT_SIZE_TABLE];
        $headerRow = $table->addRow(self::ATTENDANCE_ROW_HEIGHT);
        foreach (['Attnd?', 'Last', 'First', 'Age', 'Level', 'Phone Number(s)'] as $i => $label) {
            $headerRow->addCell($columnWidths[$i])->addText($label, $headerStyle);
        }

        $dataStyle = ['size' => self::FONT_SIZE_TABLE];
        $idx = 1;
        foreach ($registrants as $registrant) {
            $row = $table->addRow(self::ATTENDANCE_ROW_HEIGHT);
            $row->addCell($columnWidths[0])->addText('___' . $idx, $dataStyle);
            $row->addCell($columnWidths[1])->addText((string) $registrant->student_last, $dataStyle);
            $row->addCell($columnWidths[2])->addText((string) $registrant->student_first, $dataStyle);
            $row->addCell($columnWidths[3])->addText((string) $registrant->student_age, $dataStyle);
            $row->addCell($columnWidths[4])->addText((string) $registrant->student_level, $dataStyle);
            $row->addCell($columnWidths[5])->addText($this->format_phone_numbers($registrant->family_phone_numbers), $dataStyle);
            $idx++;
        }
        while ($idx <= self::ATTENDANCE_DATA_ROWS) {
            $row = $table->addRow(self::ATTENDANCE_ROW_HEIGHT);
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
        $waitlisters = $waitlist_query->get_roster_waitlist($activity_id, self::WAITLIST_MAX_ENTRIES);
        if (empty($waitlisters)) {
            return;
        }

        $columnWidths = self::WAITLIST_COL_WIDTHS;
        $table = $cell->addTable(['width' => self::WAITLIST_TABLE_WIDTH, 'unit' => 'dxa', 'layout' => 'fixed']);

        $style = ['bold' => true, 'size' => self::FONT_SIZE_TABLE];
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
            'borderTopSize' => $size, 'borderTopColor' => self::BORDER_COLOR, 'borderTopStyle' => 'single',
            'borderLeftSize' => $size, 'borderLeftColor' => self::BORDER_COLOR, 'borderLeftStyle' => 'single',
            'borderBottomSize' => $size, 'borderBottomColor' => self::BORDER_COLOR, 'borderBottomStyle' => 'single',
            'borderRightSize' => $size, 'borderRightColor' => self::BORDER_COLOR, 'borderRightStyle' => 'single',
        ];
    }

}
