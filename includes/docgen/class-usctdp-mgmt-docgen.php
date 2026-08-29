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

    /**
     * Font sizes (points) and attendance-table row padding aren't one fixed
     * set of numbers - they're picked per activity, at runtime, based on how
     * many students are actually registered (see select_roster_preset()).
     * Most rosters run 10-20 registrants; a handful run as large as ~40.
     * Sizing every roster for that rare max would make the common case look
     * unnecessarily cramped, so smaller rosters get a preset with bigger
     * fonts and less blank padding, and larger ones fall back to smaller
     * fonts - the same tradeoff a human laying this out by hand would make.
     *
     * Ordered smallest 'max_registrants' first; select_roster_preset() picks
     * the first entry whose 'max_registrants' the actual count fits under.
     * The last entry's null 'max_registrants' makes it the catch-all for
     * anything larger - including beyond the ~40 this was tuned for, since
     * real registrant count always wins over any fixed row target (see
     * add_attendance_table(), which never truncates real registrants, only
     * pads up to a minimum).
     *
     * The specific thresholds and sizes here are a first pass, not a final
     * answer - see the font_experiment_*.docx/dynamic_preset_*.docx samples
     * this was tuned against and adjust freely; every other roster
     * dimension (widths, spacing, borders) lives in the constants above and
     * stays fixed across presets.
     */
    const ROSTER_SIZE_PRESETS = [
        [
            'max_registrants' => 15,
            'attendance_data_rows' => 18,
            'font_title' => 16,
            'font_schedule' => 12,
            'font_detail' => 10,
            'font_footer' => 10,
            'font_table' => 11,
            'font_waitlist' => 9,
        ],
        [
            'max_registrants' => 20,
            'attendance_data_rows' => 28,
            'font_title' => 16,
            'font_schedule' => 10.5,
            'font_detail' => 10,
            'font_footer' => 10,
            'font_table' => 11,
            'font_waitlist' => 9,
        ],
        [
            // Catch-all for anything larger. 'attendance_data_rows' => null
            // means "no blank padding - just the real registrant count":
            // padding a rare large roster out to a round number wouldn't
            // buy consistency with anything (every other tier already
            // varies in height), so there's no reason to spend the extra
            // page space on it here.
            'max_registrants' => null,
            'attendance_data_rows' => null,
            'font_title' => 16,
            'font_schedule' => 10,
            'font_detail' => 10,
            'font_footer' => 10,
            'font_table' => 10,
            'font_waitlist' => 9,
        ],
    ];

    /**
     * Attendance table columns, nested inside Table 1's row 3 - see
     * add_attendance_table(). One entry per column, in print order; each
     * pairs its header label with its own width (twips) so there's a
     * single place to look to change how wide a column is - no counting
     * positions in a separate flat array against a comment listing what
     * they mean. To resize a column, just edit the number next to its
     * label here; nothing else needs to change. To add/remove a column,
     * add/remove an entry here AND update format_registrant_row() (which
     * supplies the actual per-registrant values in this same order,
     * skipping 'attnd' - see add_attendance_table()).
     *
     * Widths are deliberately narrow, not stretched to fill the full row -
     * a wide gap between the name columns and Phone made it easy to lose
     * track of which phone number belongs to which row while scanning
     * across, so the table only takes up as much of the row as it needs
     * and the rest stays blank rather than stretched.
     */
    const ATTENDANCE_COLUMNS = [
        'attnd' => ['label' => 'Attnd?', 'width' => 1000],
        'last' => ['label' => 'Last', 'width' => 2000],
        'first' => ['label' => 'First', 'width' => 2000],
        'age' => ['label' => 'Age', 'width' => 800],
        'level' => ['label' => 'Lvl', 'width' => 800],
        'phone' => ['label' => 'Phone Number(s)', 'width' => 4200],
    ];
    const ATTENDANCE_ROW_HEIGHT = 300;
    // Zebra-striping fill for every other data row (see add_attendance_table())
    // - deliberately barely-there (light grey, not a strong color) so it
    // helps the eye track a row across the page without competing with the
    // text itself. Hex, no leading '#'.
    const ATTENDANCE_STRIPE_COLOR = 'FFFFFF';

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
        return implode(' / ', array_map([$this, 'format_phone_number'], $numbers));
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
            '(%s) %s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 4)
        );
    }

    /**
     * "__N." for one attendance-table row's own Attnd column - see
     * add_attendance_table(). Two underscores (a blank to mark someone
     * checked in), then their 1-based attendance number.
     */
    private function format_attendance_number($number)
    {
        return sprintf('___%d', $number);
    }

    /**
     * One registrant's Last/First/Age/Level/Phone values, in that order,
     * for their own separate cells in the attendance table (see
     * add_attendance_table()) - name casing is normalized (raw stored
     * names come from years of manual entry/import and aren't consistent -
     * some are all-caps, some all-lowercase); age (birth_date unset - see
     * get_roster_students()'s TIMESTAMPDIFF) and level (a free-text field,
     * not always filled in) each print "--" rather than a blank cell when
     * missing, so a genuinely blank cell only ever means "this is a
     * padding row", not "we don't know".
     *
     * The Phone value has this registration's note (purchase_notes - see
     * get_roster_students()'s doc comment for why that's the single source
     * of truth, not usctdp_registration.notes) appended after the phone
     * numbers, in that same cell, rather than a column of its own - a note
     * is the exception, not something every roster needs a column for, so
     * it only takes up space on the rows that actually have one.
     */
    private function format_registrant_row($registrant)
    {
        $age = ($registrant->student_age !== null && $registrant->student_age !== '')
            ? (string) $registrant->student_age
            : '--';
        $level = ($registrant->student_level !== null && $registrant->student_level !== '')
            ? $this->format_level($registrant->student_level)
            : '--';
        $phone = $this->format_phone_numbers($registrant->family_phone_numbers);
        if (!empty($registrant->purchase_notes)) {
            $phone = trim($phone . '  — Note: ' . $registrant->purchase_notes);
        }
        return [
            ucwords(strtolower($registrant->student_last)),
            ucwords(strtolower($registrant->student_first)),
            $age,
            $level,
            $phone,
        ];
    }

    /**
     * "5.0", "1.5" - student_level is free text (not always a plain
     * number - see get_roster_students()), so this only reformats it to one
     * decimal place when it actually is one; anything else (a level name, a
     * malformed entry) prints as-is rather than being dropped or mangled.
     */
    private function format_level($raw)
    {
        if (!is_numeric($raw)) {
            return (string) $raw;
        }
        return number_format((float) $raw, 1);
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
     *
     * Ordering: by default every activity mixes into one day/time-sorted
     * flow (Monday's clinics first, then Tuesday's, etc. - see
     * sort_roster_activities_by_day_time()). An individual activity whose
     * own meta has "segregate_roster" truthy (an ad hoc key, same
     * convention as this column's "session_title" key - see
     * derive_clinic_roster_fields()) is pulled out into its own leading
     * section instead, still day/time-sorted among the other segregated
     * activities - e.g. the client wanting "Tiny Tots" clinics listed
     * ahead of everything else, regardless of which session(s) they're
     * actually in. This is a per-activity flag, not a per-session one - a
     * session can have some clinics segregated and others in the normal
     * flow.
     */
    public function generate_roster_for_sessions(array $session_ids)
    {
        $activity_query = new Usctdp_Mgmt_Activity_Query();
        $ordering_data = $activity_query->get_roster_ordering_data($session_ids);

        $segregated = [];
        $main = [];
        foreach ($ordering_data as $item) {
            if ($item->type !== 'clinic' && $item->type !== 'tournament') {
                Usctdp_Mgmt::logger()->log_info(
                    'Skipping roster block for unsupported activity type: ' . $item->type . ' (activity ' . $item->id . ')'
                );
                continue;
            }

            $activity_meta = json_decode((string) $item->activity_meta, true);
            if (!empty($activity_meta['segregate_roster'])) {
                $segregated[] = $item;
            } else {
                $main[] = $item;
            }
        }
        $this->sort_roster_activities_by_day_time($segregated);
        $this->sort_roster_activities_by_day_time($main);

        [$phpWord, $section] = $this->new_roster_document();
        $is_first_block = true;
        $rendered_group_ids = [];
        foreach ([$segregated, $main] as $activity_items) {
            foreach ($activity_items as $item) {
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
        }
        return $phpWord;
    }

    /**
     * Sorts $activities in place by day-of-week then start time (see
     * Usctdp_Mgmt_Activity_Query::get_roster_ordering_data()) - Monday's
     * clinics first, then Tuesday's, earliest start time within a day
     * first, and so on. Tournaments have no day/time, so they sort after
     * every clinic; PHP's usort() has been stable since 8.0, so activities
     * that tie on day/time - including every tournament, which all tie at
     * "no day" - keep their original relative order (primary/
     * secondary_sort_order, per the query's own ORDER BY) rather than being
     * shuffled. Multiple tournaments aren't expected to need any finer
     * ordering than that in practice.
     */
    private function sort_roster_activities_by_day_time(array &$activities)
    {
        usort($activities, function ($a, $b) {
            $a_key = [$a->day_of_week !== null ? (int) $a->day_of_week : 8, (string) $a->start_time];
            $b_key = [$b->day_of_week !== null ? (int) $b->day_of_week : 8, (string) $b->start_time];
            return $a_key <=> $b_key;
        });
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
     * - see generate_roster_for_sessions()); each saves differently AND
     * uploads as a different Drive file type:
     *
     * - Financial statements upload as .docx with mimeType
     *   'application/vnd.google-apps.document', which asks Drive to
     *   convert them into a native Google Doc on upload.
     * - Rosters upload as real PDFs (mimeType 'application/pdf', no
     *   further Drive conversion). Google Docs' conversion is a
     *   live-editing/reflow engine that's slow to open for documents
     *   shaped like these (many full-width tables, a hard page break
     *   between every activity block - see roster-docgen-objectmodel-
     *   rewrite memory); a PDF is just pre-rendered static pages, and
     *   Drive's PDF viewer opens and prints it directly, unlike the
     *   raw-.docx preview clients had trouble printing from.
     *
     *   The PDF itself is produced via convert_docx_to_pdf_via_google() -
     *   NOT PhpWord's own built-in PDF writer. That writer's HTML
     *   translation layer silently drops table row heights
     *   (vendor/phpoffice/phpword/src/PhpWord/Writer/HTML/Element/
     *   Table.php - $height is fetched from the row and never used),
     *   which rendered the roster's blank attendance rows - real
     *   writable space in the actual .docx - as collapsed hairlines.
     *   Routing the .docx through Google's own importer instead (the
     *   same conversion Drive already did reliably before this change)
     *   avoids that whole class of fidelity bug.
     */
    private function upload_document_to_drive($document, $existing_drive_id, $title)
    {
        $client = $this->create_google_client();
        $drive = new Drive($client);
        $destinationFolderId = env('GOOGLE_DRIVE_FOLDER_ID');
        $is_roster = !($document instanceof TemplateProcessor);

        ob_start();
        if ($is_roster) {
            IOFactory::createWriter($document, 'Word2007')->save('php://output');
        } else {
            $document->saveAs('php://output');
        }
        $docx_content = ob_get_clean();

        if ($is_roster) {
            $content = $this->convert_docx_to_pdf_via_google($drive, $docx_content, $title);
            $mime_type = 'application/pdf';
            $drive_mime_type = 'application/pdf';
        } else {
            $content = $docx_content;
            $mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            $drive_mime_type = 'application/vnd.google-apps.document';
        }

        $clean_title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $metadata_args = [
            'name' => $clean_title,
            'mimeType' => $drive_mime_type,
        ];

        if ($existing_drive_id !== null) {
            // A file's underlying Drive type (native Google Doc vs. a real
            // binary like PDF) isn't something files->update() can change -
            // it stays whatever type the file was created as. This matters
            // here because rosters switched from uploading as Google Docs to
            // uploading as PDFs; any $existing_drive_id from before that
            // switch still points at an old Google Doc. Rather than trust
            // update() to silently no-op or error on the mismatch, check the
            // existing file's real type first and recreate it if it doesn't
            // match what we're about to upload.
            if ($this->drive_file_matches_mime_type($drive, $existing_drive_id, $drive_mime_type)) {
                $fileMetadata = new DriveFile($metadata_args);
                return $drive->files->update($existing_drive_id, $fileMetadata, [
                    'data' => $content,
                    'mimeType' => $mime_type,
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink'
                ]);
            }
            $drive->files->delete($existing_drive_id);
        }

        if (!empty($destinationFolderId)) {
            $metadata_args['parents'] = [$destinationFolderId];
        }
        $fileMetadata = new DriveFile($metadata_args);
        $file = $drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mime_type,
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink'
        ]);

        $drive->permissions->create($file->id, new Permission([
            'type' => 'anyone',
            'role' => 'writer'
        ]), ['fields' => 'id']);

        return $file;
    }

    /**
     * Converts .docx bytes to PDF bytes by round-tripping through a
     * throwaway Google Doc: upload as a converted Google Doc (Google's
     * own docx importer - the same one Drive already used for every
     * roster before this change, known to render these documents
     * correctly), export that to PDF, then delete the scratch Doc. The
     * scratch file is never given a destinationFolderId and never shared
     * (no Permission::create call) - it only exists for however long this
     * one call takes, and is removed in a finally block so a failed
     * export doesn't leave it behind.
     *
     * files->export() is capped at 10MB of exported content (Drive API
     * limit for Google Workspace document exports) - comfortably above
     * what these rosters produce, but worth knowing if a roster ever
     * grows enormous.
     */
    private function convert_docx_to_pdf_via_google($drive, $docx_content, $title)
    {
        $scratchMetadata = new DriveFile([
            'name' => 'usctdp_roster_pdf_scratch_' . $title,
            'mimeType' => 'application/vnd.google-apps.document',
        ]);
        $scratch = $drive->files->create($scratchMetadata, [
            'data' => $docx_content,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);

        try {
            $response = $drive->files->export($scratch->id, 'application/pdf', ['alt' => 'media']);
            return $response instanceof \Psr\Http\Message\ResponseInterface
                ? (string) $response->getBody()
                : (string) $response;
        } finally {
            $drive->files->delete($scratch->id);
        }
    }

    /**
     * True if $drive_id's file already has $expected_mime_type. Used to
     * decide update-in-place vs. delete-and-recreate when a roster's stored
     * drive_id predates the switch to PDF uploads (see
     * upload_document_to_drive()). A file that no longer exists (deleted or
     * trashed out-of-band in Drive) counts as "doesn't match" so callers
     * fall through to creating a fresh file instead of erroring the whole
     * roster generation over a missing Drive file.
     */
    private function drive_file_matches_mime_type($drive, $drive_id, $expected_mime_type)
    {
        try {
            $existing = $drive->files->get($drive_id, ['fields' => 'mimeType']);
            return $existing->mimeType === $expected_mime_type;
        } catch (\Exception $e) {
            return false;
        }
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
        // PhpWord's object-model API (unlike TemplateProcessor::setValue(),
        // which always runs text through its own Escaper\Xml regardless of
        // this) defaults to NOT XML-escaping text passed to addText() -
        // Settings::$outputEscapingEnabled starts false. Any real title,
        // name, or note containing &, <, or > would otherwise write invalid
        // XML and corrupt the whole document; this is a process-wide static
        // flag, not something scoped to one PhpWord instance, but it's safe
        // to force on here since it only affects this escaping decision and
        // financial statements' TemplateProcessor path never consults it.
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);

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
            'level' => "--",
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
        // Fetched once, up front, rather than inside add_attendance_table()
        // as before - the preset this whole block renders with (fonts,
        // title through footer, not just the attendance table itself)
        // depends on the registrant count, so it has to be known before any
        // of that gets built. $activity_id may be a single id or an array
        // of merged clinic ids (see add_merged_clinic_roster_block()) -
        // get_roster_students() accepts either.
        $registration_query = new Usctdp_Mgmt_Registration_Query();
        $registrants = $registration_query->get_roster_students($activity_id);
        $preset = $this->select_roster_preset(count($registrants));

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
            ['name' => 'Arial', 'bold' => true, 'size' => $preset['font_title']],
            ['alignment' => 'center']
        );

        // Row 2: schedule / instructors / level / dates. This row's 4 cells
        // (widths WIDE/NARROW/NARROW/NARROW) are what define the table's
        // actual grid - see the class doc comment above.
        $row2 = $table->addRow(self::ROSTER_ROW_INFO_HEIGHT);
        $detailStyle = ['size' => $preset['font_detail']];
        $detailLabelStyle = ['italic' => true, 'size' => $preset['font_detail']];
        $fieldStart = ['spaceBefore' => self::ROSTER_FIELD_SPACE_BEFORE];

        $scheduleCell = $row2->addCell(self::ROSTER_COL_WIDE);
        $scheduleCell->addText(
            "Clinic: {$fields['dow']} {$fields['stime']} - {$fields['etime']}",
            ['bold' => true, 'size' => $preset['font_schedule']],
            $fieldStart
        );
        $scheduleCell->addText(
            "Instructor(s): {$fields['insts']}",
            ['bold' => true, 'italic' => true, 'size' => $preset['font_detail']],
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
            ['bold' => true, 'size' => $preset['font_detail']],
            $fieldStart
        );
        $levelCell->addText('', ['bold' => true, 'size' => $preset['font_schedule']]);

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
        $this->add_attendance_table($attendanceCell, $registrants, $preset);

        // Row 4: attendance total / signature line. Split 50/50 rather than
        // the template's 3840/7680 - see the class doc comment above.
        $footerStyle = ['bold' => true, 'size' => $preset['font_footer']];
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
        $headingStyle = ['bold' => true, 'size' => $preset['font_footer']];
        $headingSpacing = $this->auto_line_spacing(240); // single-spaced

        $waitlistCell = $row5->addCell(self::ROSTER_COL_WIDE);
        $waitlistCell->addText('Waitlist', $headingStyle, $headingSpacing);
        $this->add_waitlist_table($waitlistCell, $activity_id, $preset);

        $notesCell = $row5->addCell(self::ROSTER_COL_WIDE, ['gridSpan' => 3]);
        $notesCell->addText('Absentees/Notes', $headingStyle, $headingSpacing);
        $notesLineSpacing = $this->auto_line_spacing(276);
        for ($i = 0; $i < self::NOTES_LINE_COUNT; $i++) {
            $notesCell->addText(
                '________________________________________________',
                ['size' => $preset['font_footer']],
                $notesLineSpacing
            );
        }

        $section->addText(
            $fields['session_short_code'],
            ['name' => 'Arial', 'size' => $preset['font_footer']],
            ['alignment' => 'center']
        );
    }

    /**
     * Picks the ROSTER_SIZE_PRESETS entry to render a block with, based on
     * its actual registrant count - the first entry (smallest
     * 'max_registrants' first) the count fits under. Always returns
     * something: the last preset's null 'max_registrants' matches anything,
     * so the loop can't fall through without returning.
     */
    private function select_roster_preset($registrant_count)
    {
        foreach (self::ROSTER_SIZE_PRESETS as $preset) {
            if ($preset['max_registrants'] === null || $registrant_count <= $preset['max_registrants']) {
                return $preset;
            }
        }
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
     * Attendance table for one activity: a header row plus enough data rows
     * to cover $preset['attendance_data_rows'] registrant slots, blank-
     * padded when there aren't that many real registrants (null means no
     * padding - see ROSTER_SIZE_PRESETS) - deliberate, not something left
     * over from the template, so every "normal"-sized activity's block
     * takes up about the same page space regardless of exactly how many
     * students registered.
     *
     * Takes the registrant list pre-fetched by add_roster_activity_block()
     * (needed there already, to pick $preset) rather than querying again -
     * see Usctdp_Mgmt_Registration_Query::get_roster_students() for that
     * query. It inner-joins student/family, so a registration whose student
     * or family row has gone missing is silently left off the roster rather
     * than throwing like this used to - a real behavior change, but
     * throwing per-row is exactly the per-row cost that query was written to
     * avoid.
     */
    private function add_attendance_table($cell, array $registrants, array $preset)
    {
        $widths = array_column(self::ATTENDANCE_COLUMNS, 'width');
        $labels = array_column(self::ATTENDANCE_COLUMNS, 'label');

        $table = $cell->addTable(['width' => array_sum($widths), 'unit' => 'dxa', 'layout' => 'fixed']);

        $headerStyle = ['bold' => true, 'underline' => 'single', 'size' => $preset['font_table']];
        $headerRow = $table->addRow(self::ATTENDANCE_ROW_HEIGHT);
        $this->add_attendance_person_cells($headerRow, $widths, $labels, $headerStyle);

        $dataStyle = ['size' => $preset['font_table']];
        $stripeStyle = ['bgColor' => self::ATTENDANCE_STRIPE_COLOR];
        // null 'attendance_data_rows' => no padding, just the real registrants.
        $row_count = max(count($registrants), $preset['attendance_data_rows'] ?? count($registrants));

        for ($i = 0; $i < $row_count; $i++) {
            $registrant = $registrants[$i] ?? null;
            $values = $registrant
                ? array_merge([$this->format_attendance_number($i + 1)], $this->format_registrant_row($registrant))
                : array_fill(0, count($widths), '');
            $row = $table->addRow(self::ATTENDANCE_ROW_HEIGHT);
            // Every other row with a real registrant - blank padding rows
            // never stripe, so the pattern stops as soon as the actual
            // roster does instead of continuing down through empty rows.
            $cellStyle = ($registrant && $i % 2 === 1) ? $stripeStyle : [];
            $this->add_attendance_person_cells($row, $widths, $values, $dataStyle, $cellStyle);
        }
    }

    /**
     * Adds one registrant's (or the header's) cells to $row, one value per
     * ATTENDANCE_COLUMNS width, in order - shared between the header row
     * and every data row in add_attendance_table(), so the cells-with-
     * these-widths shape only needs to be written once. $cellStyle is the
     * cell's own style (e.g. background fill for zebra-striping - see
     * add_attendance_table()), separate from $style, which is the text's.
     */
    private function add_attendance_person_cells($row, array $widths, array $values, array $style, array $cellStyle = [])
    {
        foreach ($values as $i => $value) {
            $row->addCell($widths[$i], $cellStyle)->addText($value, $style);
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
    private function add_waitlist_table($cell, $activity_id, array $preset)
    {
        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query();
        $waitlisters = $waitlist_query->get_roster_waitlist($activity_id, self::WAITLIST_MAX_ENTRIES);
        if (empty($waitlisters)) {
            return;
        }

        $columnWidths = self::WAITLIST_COL_WIDTHS;
        $table = $cell->addTable(['width' => self::WAITLIST_TABLE_WIDTH, 'unit' => 'dxa', 'layout' => 'fixed']);

        $style = ['bold' => true, 'size' => $preset['font_waitlist']];
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
            'borderTopSize' => $size,
            'borderTopColor' => self::BORDER_COLOR,
            'borderTopStyle' => 'single',
            'borderLeftSize' => $size,
            'borderLeftColor' => self::BORDER_COLOR,
            'borderLeftStyle' => 'single',
            'borderBottomSize' => $size,
            'borderBottomColor' => self::BORDER_COLOR,
            'borderBottomStyle' => 'single',
            'borderRightSize' => $size,
            'borderRightColor' => self::BORDER_COLOR,
            'borderRightStyle' => 'single',
        ];
    }

}
