<?php

class Usctdp_Mgmt_Admin_Ajax
{
    public static $ajax_handlers = [
        'activity_add_instructor' => 'ajax_activity_add_instructor',
        'activity_preregistration' => 'ajax_activity_preregistration',
        'activity_remove_instructor' => 'ajax_activity_remove_instructor',
        'commit_merchandise' => 'ajax_commit_merchandise',
        'create_activity_group' => 'ajax_create_activity_group',
        'create_family' => 'ajax_create_family',
        'create_ledger_entries' => 'ajax_create_ledger_entries',
        'create_student' => 'ajax_create_student',
        'datatable_balances' => 'ajax_datatable_balances',
        'datatable_balances_detail' => 'ajax_datatable_balances_detail',
        'earnings_rollup' => 'ajax_earnings_rollup',
        'earnings_session_detail' => 'ajax_earnings_session_detail',
        'gen_roster' => 'ajax_gen_roster',
        'gen_statement' => 'ajax_gen_statement',
        'get_activity_details' => 'ajax_get_activity_details',
        'get_family' => 'ajax_get_family',
        'get_family_balance' => 'ajax_get_family_balance',
        'get_session_pricing' => 'ajax_get_session_pricing',
        'issue_house_credit' => 'ajax_issue_house_credit',
        'ledger_datatable' => 'ajax_ledger_datatable',
        'ledger_events_datatable' => 'ajax_ledger_events_datatable',
        'move_activity_to_group' => 'ajax_move_activity_to_group',
        'preview_registration_activity_change' => 'ajax_preview_registration_activity_change',
        'purchase_history_datatable' => 'ajax_purchase_history_datatable',
        'recent_registrations' => 'ajax_recent_registrations',
        'registrations_datatable' => 'ajax_registrations_datatable',
        'roster_add_session' => 'ajax_roster_add_session',
        'roster_create' => 'ajax_roster_create',
        'roster_delete_group' => 'ajax_roster_delete_group',
        'roster_link' => 'ajax_get_roster_link',
        'roster_regenerate_all' => 'ajax_roster_regenerate_all',
        'roster_remove_session' => 'ajax_roster_remove_session',
        'roster_rename' => 'ajax_roster_rename',
        'save_activity_group_details' => 'ajax_save_activity_group_details',
        'select2_search' => 'ajax_select2_search',
        'session_rosters' => 'ajax_session_rosters',
        'session_rosters_datatable' => 'ajax_session_rosters_datatable',
        'sessions_datatable' => 'ajax_sessions_datatable',
        'set_registration_status' => 'ajax_set_registration_status',
        'student_datatable' => 'ajax_student_datatable',
        'submit_payment' => 'ajax_submit_payment',
        'update_activity' => 'ajax_update_activity',
        'update_clinic_schedule' => 'ajax_update_clinic_schedule',
        'update_family' => 'ajax_update_family',
        'update_family_name' => 'ajax_update_family_name',
        'update_registration' => 'ajax_update_registration',
        'update_pricing' => 'ajax_update_pricing',
        'update_purchase' => 'ajax_update_purchase',
        'update_session_status' => 'ajax_update_session_status',
        'update_student' => 'ajax_update_student',
        'waitlist_add' => 'ajax_waitlist_add',
        'waitlist_remove' => 'ajax_waitlist_remove',
        'waitlist_datatable' => 'ajax_waitlist_datatable',
    ];

    private function is_student_enrolled($student_id, $activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'student_id' => $student_id,
            'activity_id' => $activity_id,
            'number' => 1,
            'status' => 'active'
        ]);
        return !empty($reg_query->items);
    }

    private function is_student_waitlisted($student_id, $activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Waitlist_Query([
            'student_id' => $student_id,
            'activity_id' => $activity_id,
            'number' => 1
        ]);
        return !empty($reg_query->items);
    }

    private function remove_waitlist_entry($student_id, $activity_id)
    {
        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query([
            'activity_id' => $activity_id,
            'student_id' => $student_id,
            'number' => 1,
        ]);
        if (!empty($waitlist_query->items)) {
            $id = $waitlist_query->items[0]->id;
            $result = $waitlist_query->delete_item($id);
            if (!$result) {
                throw new Web_Request_Exception('Failed to remove student from waitlist.');
            }
        }
    }

    /**
     * 'active' is a group-scoped sum, not a plain per-activity count - two
     * activities sharing a physical space/time slot draw down the same
     * capacity pool (see Usctdp_Mgmt_Reservation_Group_Table), matching
     * after_checkout_validation()'s capacity-check scope.
     *
     * It also counts 'pending' registrations still inside the checkout hold
     * window (same status/interval condition as
     * after_checkout_validation()'s own count query - Usctdp_Mgmt_Woocommerce_Hooks::HOLD_MINUTES
     * is the shared source of truth for both). This admin flow itself never
     * creates 'pending' rows - create_purchase_and_registration() writes
     * straight to 'active' - but a *different* customer's WooCommerce
     * checkout can be mid-transaction holding the last seat in this exact
     * group right now. lock_registrations() (see create_order_records())
     * already takes the same reservation_group row lock checkout does, so
     * the two paths are correctly serialized against each other - but
     * without this hold-window branch, this count would undercount what's
     * actually spoken for and let an admin register past a seat a customer
     * is actively in the middle of paying for, overbooking the group the
     * moment that customer's order confirms.
     *
     * 'waitlist' deliberately stays scoped to this one activity_id - a
     * student waitlisted for the Tuesday 4pm slot of a shared room isn't
     * waitlisted for every other slot sharing that room.
     */
    private function get_activity_enrollment_counts($activity_id)
    {
        global $wpdb;
        $active_registrations = 0;
        $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
        if ($activity) {
            $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
            $member_activity_ids = $group_query->get_member_activity_ids($activity->reservation_group_id);
            if (!empty($member_activity_ids)) {
                $placeholders = implode(',', array_fill(0, count($member_activity_ids), '%d'));
                $active_registrations = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}usctdp_registration
                     WHERE activity_id IN ($placeholders)
                     AND (status = %s
                         OR (status = %s AND created_at > NOW() - INTERVAL %d MINUTE))",
                    array_merge($member_activity_ids, ['active', 'pending', Usctdp_Mgmt_Woocommerce_Hooks::HOLD_MINUTES])
                ));
            }
        }

        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query([
            'activity_id' => $activity_id,
            'count' => true
        ]);
        $waitlist_registrations = $waitlist_query->found_items;

        return [
            'active' => $active_registrations,
            'waitlist' => $waitlist_registrations,
            'total' => $active_registrations + $waitlist_registrations
        ];
    }

    private function get_activity_capacity($activity_id)
    {
        $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
        if (!$activity) {
            return null;
        }
        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $group = $group_query->get_group($activity->reservation_group_id);
        return $group ? $group->capacity : null;
    }

    /**
     * The other activities (if any) sharing this one's reservation group -
     * {id, title} pairs, not just titles, since the Activities page's
     * "Manage Group" modal needs ids to offer removing a specific sibling
     * (see ajax_create_activity_group()). Also feeds the register page's
     * explanation of why the capacity badge and the single-activity
     * roster/waitlist views don't agree (see Usctdp_Mgmt_Reservation_Group_Table):
     * the badge reflects everyone in the shared group, but View
     * Roster/Waitlist only ever show this specific activity's registrants -
     * that caller only reads .title (see usctdp-mgmt-admin-register.js).
     */
    private function get_shared_activities($activity_id)
    {
        $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
        if (!$activity) {
            return [];
        }
        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        $sibling_ids = array_values(array_diff(
            $group_query->get_member_activity_ids($activity->reservation_group_id),
            [(int) $activity_id]
        ));
        if (empty($sibling_ids)) {
            return [];
        }
        $sibling_query = new Usctdp_Mgmt_Activity_Query(['id__in' => $sibling_ids, 'number' => 0]);
        return array_map(function ($sibling) {
            return ['id' => (int) $sibling->id, 'title' => $sibling->title];
        }, $sibling_query->items);
    }

    /**
     * The register page's "View Roster" modal heading - same combined-name
     * resolution roster generation uses (Usctdp_Mgmt_Reservation_Group_Query::
     * get_roster_title()), so the modal's title matches whatever the
     * generated .docx would be titled, whether or not the group has
     * actually been merged.
     */
    private function get_roster_title_for_activity($activity_id)
    {
        $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
        if (!$activity) {
            return null;
        }
        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
        return $group_query->get_roster_title($activity->reservation_group_id);
    }

    private function get_sanitized_post_field_text($field)
    {
        if (array_key_exists($field, $_POST)) {
            return sanitize_text_field($_POST[$field]);
        }
        return null;
    }

    private function get_sanitized_post_field_int($field)
    {
        if (array_key_exists($field, $_POST)) {
            return intval($_POST[$field]);
        }
        return null;
    }

    private function create_entity($source, $query_object, $fields)
    {
        $args = [];
        foreach ($fields as $field => $transform) {
            $raw = $source[$field] ?? null;
            $args[$field] = $transform($raw);
        }
        $query = new $query_object();
        return $query->add_item($args);
    }

    private function save_entity($entity_id, $source, $query_object, $fields, $id_field = 'id')
    {
        $query = new $query_object([$id_field => $entity_id, 'number' => 1]);
        if (empty($query->items)) {
            throw new Web_Request_Exception("Entity with id $entity_id not found.");
        }
        $entity = $query->items[0];

        $args = [];
        foreach ($fields as $field => $transform) {
            if (array_key_exists($field, $source)) {
                $data = $transform($source[$field]);
                if ($data !== $entity->$field) {
                    $args[$field] = $data;
                }
            }
        }

        if (empty($args)) {
            return $entity;
        }

        global $wpdb;
        $wpdb->last_error = '';
        $result = $query->update_item($entity_id, $args);
        if ($result) {
            $query = new $query_object(['id' => $entity_id, 'number' => 1]);
            return $query->items[0];
        }

        // update_item() runs its own diff against the row's *raw* stored
        // values and bails with false when nothing actually needs writing -
        // not necessarily a real failure. That diff can disagree with ours
        // above for any field whose Row shape transforms the raw column
        // value (e.g. Usctdp_Mgmt_Purchase_Row decodes `discounts` to an
        // array, so a freshly re-encoded JSON string here can never compare
        // === to it and always looks "changed" to us, even when the
        // underlying raw value is actually identical - see
        // ajax_update_purchase()'s 'discounts' field). $wpdb->last_error
        // tells the two cases apart: empty means update_item() bailed
        // before ever running a query (a benign no-op); non-empty means a
        // query actually ran and failed.
        if (empty($wpdb->last_error)) {
            $query = new $query_object(['id' => $entity_id, 'number' => 1]);
            return $query->items[0];
        } else {
            throw new Web_Request_Exception(
                "Updating entity $entity_id failed. wpdb->last_error: {$wpdb->last_error}"
            );
        }
    }

    private function check_nonce($handler)
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action.', 403);
        }
        if (!check_ajax_referer($handler . '_nonce', 'security', false)) {
            wp_send_json_error('Security check failed. Invalid Nonce.', 400);
        }
    }

    /**
     * Converts plain Y-m-d date-filter inputs (Eastern-time calendar days,
     * the timezone the business actually operates in) into the UTC
     * datetime boundaries created_at columns are stored in. date_to is
     * converted to the start of the following Eastern day so the whole
     * selected day is included. Returns only the keys for inputs that were
     * actually provided (and parsed successfully), so callers can
     * array_merge() the result straight into a query $args array.
     */
    private function eastern_date_range_to_utc($date_from, $date_to)
    {
        $result = [];
        $eastern_tz = new DateTimeZone('America/New_York');
        $utc_tz = new DateTimeZone('UTC');
        if ($date_from) {
            $from_dt = DateTime::createFromFormat('Y-m-d', $date_from, $eastern_tz);
            if ($from_dt) {
                $from_dt->setTime(0, 0, 0);
                $from_dt->setTimezone($utc_tz);
                $result['date_from'] = $from_dt->format('Y-m-d H:i:s');
            }
        }
        if ($date_to) {
            $to_dt = DateTime::createFromFormat('Y-m-d', $date_to, $eastern_tz);
            if ($to_dt) {
                $to_dt->setTime(0, 0, 0);
                $to_dt->modify('+1 day');
                $to_dt->setTimezone($utc_tz);
                $result['date_to'] = $to_dt->format('Y-m-d H:i:s');
            }
        }
        return $result;
    }

    public function ajax_activity_preregistration()
    {
        $this->check_nonce('activity_preregistration');

        $activity_id = isset($_GET['activity_id']) ? sanitize_text_field($_GET['activity_id']) : '';
        $student_id = isset($_GET['student_id']) ? sanitize_text_field($_GET['student_id']) : '';

        $student = Usctdp_Mgmt_Model::get_student($student_id);
        if (!$student) {
            wp_send_json_error('Student with ID ' . $student_id . ' not found.', 404);
        }

        $activity = Usctdp_Mgmt_Model::get_expanded_activity($activity_id);
        if (!$activity) {
            wp_send_json_error('Activity with ID ' . $activity_id . ' not found.', 404);
        }

        $pricing_query = new Usctdp_Mgmt_Pricing_Query([
            'session_id' => $activity->session_id,
            'product_id' => $activity->product_id,
            'number' => 1
        ]);
        if (empty($pricing_query->items)) {
            wp_send_json_error('Pricing for activity ' . $activity_id . ' not found.', 404);
        }

        $pricing = $pricing_query->items[0];
        $capacity = (int) $activity->activity_capacity;
        $enrollment_counts = $this->get_activity_enrollment_counts($activity_id);
        $student_registered = $this->is_student_enrolled($student_id, $activity_id);
        $student_waitlisted = $this->is_student_waitlisted($student_id, $activity_id);

        wp_send_json_success([
            'capacity' => $capacity,
            'session_id' => $activity->session_id,
            'product_id' => $activity->product_id,
            'woocommerce_id' => $activity->product_woocommerce_id,
            'enrollment' => $enrollment_counts['total'],
            'active' => $enrollment_counts['active'],
            'waitlist' => $enrollment_counts['waitlist'],
            'shared_with' => $this->get_shared_activities($activity_id),
            'roster_title' => $this->get_roster_title_for_activity($activity_id),
            'student_registered' => $student_registered,
            'student_waitlisted' => $student_waitlisted,
            'student_level' => $student->level,
            'pricing' => $pricing->pricing
        ]);
    }

    /**
     * Generates/regenerates a roster document. Takes exactly one of
     * activity_id, session_id, or roster_group_id - each is handled as its
     * own precise, self-contained case, rather than trying to infer a
     * roster group from a session_id (that used to happen transparently
     * inside Usctdp_Mgmt_Docgen::generate_and_upload_session_roster() - a
     * plain session_id now always means just that session, full stop).
     */
    public function ajax_gen_roster()
    {
        $this->check_nonce('gen_roster');

        // Headroom, not a load-bearing fix: roster generation used to be
        // O(activities^2) (PhpWord's TemplateProcessor rescans/rewrites the
        // whole in-progress document on every macro substitution), which is
        // what actually needed the higher limit. That's fixed now -
        // Usctdp_Mgmt_Docgen::generate_roster_for_sessions() builds the
        // document with PhpWord's object-model API instead, which is O(n) -
        // but a very large multi-session roster_group could still
        // legitimately take a bit, so this stays as a safety margin above
        // the host's normal 30s execution limit.
        set_time_limit(180);

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $roster_group_id = isset($_POST['roster_group_id']) ? intval($_POST['roster_group_id']) : 0;

        $provided = array_filter(['activity_id' => $activity_id, 'session_id' => $session_id, 'roster_group_id' => $roster_group_id]);
        if (count($provided) !== 1) {
            wp_send_json_error('Exactly one of activity_id, session_id, or roster_group_id is required.', 400);
        }

        try {
            $doc_gen = new Usctdp_Mgmt_Docgen();

            if ($activity_id) {
                $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
                if (!$activity) {
                    wp_send_json_error('Activity with ID "' . $activity_id . '" not found.', 404);
                }
                if ($activity->type !== 'clinic' && $activity->type !== 'tournament') {
                    wp_send_json_error('Unsupported activity type: ' . $activity->type, 400);
                }
                // Always generated at the reservation-group level, not the
                // single activity - if this activity has been merged with
                // others (wp usctdp merge_reservation_group), the resulting
                // doc covers all of them, one block per activity. A solo
                // (unmerged) activity's own dedicated 1:1 group produces
                // exactly what generating just that activity always did.
                $drive_file = $doc_gen->generate_and_upload_reservation_group_roster($activity->reservation_group_id);
            } elseif ($session_id) {
                $session = Usctdp_Mgmt_Model::get_session($session_id);
                if (!$session) {
                    wp_send_json_error('Session with ID "' . $session_id . '" not found.', 404);
                }
                $drive_file = $doc_gen->generate_and_upload_session_roster($session_id, $session->title);
            } else {
                $drive_file = $doc_gen->generate_and_upload_roster_group($roster_group_id);
            }

            wp_send_json_success([
                'message' => 'Roster generated successfully',
                'doc_id' => $drive_file->id,
                'doc_url' => $drive_file->webViewLink,
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z')
            ]);
        } catch (Roster_Group_Exception | Reservation_Group_Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_gen_roster', $e);
            wp_send_json_error('An unexpected server error occurred during roster generation.', 500);
        }
    }

    /**
     * `activity_id` (checked first, new) resolves through the activity's
     * reservation group - roster generation now always writes there (see
     * ajax_gen_roster()/generate_and_upload_reservation_group_roster()), so
     * this has to look the link up the same way or it'd keep reporting
     * "not yet generated" for an activity that's actually been merged with
     * others and already has a combined roster. `entity_id` (legacy,
     * generic) stays as-is for session/roster_group/family-statement
     * callers, which are untouched by this feature.
     */
    public function ajax_get_roster_link()
    {
        $this->check_nonce('roster_link');

        $activity_id = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;
        $entity_id = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : 0;
        if (empty($activity_id) && empty($entity_id)) {
            wp_send_json_error('Missing required parameter entity_id', 400);
        }

        try {
            $doc_gen = new Usctdp_Mgmt_Docgen();

            if ($activity_id) {
                $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
                if (!$activity) {
                    wp_send_json_error('Activity with ID "' . $activity_id . '" not found.', 404);
                }
                $entity_id = $activity->reservation_group_id;
            }

            $roster_link = $doc_gen->get_roster_link($entity_id);
            if (!$roster_link) {
                wp_send_json_success([
                    'drive_id' => null,
                    'doc_url' => null,
                    'generated_at' => null
                ]);
            }
            wp_send_json_success([
                'drive_id' => $roster_link->drive_id,
                // Generic Drive view URL, not the Docs-editor-specific one -
                // this endpoint serves both rosters (real PDFs now, see
                // upload_document_to_drive() in class-usctdp-mgmt-docgen.php)
                // and family financial statements (still Google Docs), and
                // only has the bare drive_id to build a URL from either way.
                'doc_url' => 'https://drive.google.com/file/d/' . $roster_link->drive_id . '/view',
                'generated_at' => $roster_link->updated_at ? $roster_link->updated_at->format('Y-m-d\TH:i:s\Z') : null
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_get_roster_link', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    /**
     * Resolves an assigned staff member's display name/photo the same way
     * Usctdp_Mgmt_Select2::select2_staff_search() does - kept in sync
     * deliberately, since both read from the same usctdp_staff row shape.
     */
    private function format_instructor($staff)
    {
        return [
            'id' => $staff->id,
            'name' => trim($staff->first_name . ' ' . $staff->last_name),
            'image_url' => $staff->image_id
                ? (wp_get_attachment_image_url((int) $staff->image_id, 'thumbnail') ?: null)
                : null,
        ];
    }

    /**
     * Clinic activities share their primary key with the usctdp_clinic row
     * that holds their day/time (see Usctdp_Mgmt_Clinic_Query - clinic.id ==
     * activity.id). Tournament activities have no such row (their schedule
     * lives as JSON on usctdp_tournament instead), so this returns null for
     * anything that isn't type === 'clinic'.
     */
    private function get_clinic_schedule($activity_id)
    {
        $clinic_query = new Usctdp_Mgmt_Clinic_Query(['id' => $activity_id, 'number' => 1]);
        if (empty($clinic_query->items)) {
            return null;
        }
        $clinic = $clinic_query->items[0];
        return [
            'day_of_week' => $clinic->day_of_week->value,
            'start_time' => $clinic->start_time->format('H:i'),
            'end_time' => $clinic->end_time->format('H:i'),
        ];
    }

    /**
     * Feeds the Activities page's "Activity Details" panel (level,
     * instructor list, schedule) AND the "Manage Group" modal (capacity,
     * group_id/group_name, shared_with) on activity selection - same
     * GET-on-selector-change pattern as ajax_get_roster_link() above.
     */
    public function ajax_get_activity_details()
    {
        $this->check_nonce('get_activity_details');

        $activity_id = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;
        if (empty($activity_id)) {
            wp_send_json_error('Missing required parameter activity_id', 400);
        }

        try {
            $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
            if (!$activity) {
                wp_send_json_error('Activity with ID ' . $activity_id . ' not found.', 404);
            }

            $staff_query = new Usctdp_Mgmt_Activity_Staff_Query();
            $instructors = array_map(
                [$this, 'format_instructor'],
                $staff_query->get_staff_for_activity($activity_id)
            );

            $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
            $group = $group_query->get_group($activity->reservation_group_id);

            wp_send_json_success([
                'level' => $activity->level,
                'type' => $activity->type,
                'session_id' => $activity->session_id,
                'schedule' => $activity->type === 'clinic' ? $this->get_clinic_schedule($activity_id) : null,
                'instructors' => $instructors,
                'shared_with' => $this->get_shared_activities($activity_id),
                'capacity' => $group ? $group->capacity : null,
                'group_id' => $group ? $group->id : null,
                'group_name' => $group ? $group->name : null,
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_get_activity_details', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    /**
     * Normalizes an HTML time-input value ("HH:MM" or "HH:MM:SS") into the
     * "HH:MM:SS" form the usctdp_clinic.start_time/end_time TIME columns
     * expect. Returns null for anything that doesn't match.
     */
    private function sanitize_time_field($raw)
    {
        $raw = sanitize_text_field($raw ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $raw, $m)) {
            return null;
        }
        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[4]) ? (int) $m[4] : 0);
    }

    /**
     * usctdp_program_schedule is a fully materialized cache derived from
     * usctdp_clinic/usctdp_tournament/usctdp_session/usctdp_product (see
     * Usctdp_Build_Program_Schedule) - there's no incremental update path,
     * so any edit to a clinic's day/time has to be followed by a full
     * rebuild to keep it in sync. This class normally only loads under
     * WP-CLI (see usctdp-mgmt.php), so it's required here on demand instead.
     */
    private function rebuild_program_schedule()
    {
        if (!class_exists('Usctdp_Build_Program_Schedule')) {
            require_once plugin_dir_path(__FILE__) . '../includes/cli/class-usctdp-build-program-schedule.php';
        }
        (new Usctdp_Build_Program_Schedule())->build();
    }

    /**
     * A clinic activity's title/search_term are derived from its product
     * name plus its day/time (same "$clinic_name, $dow, $start to $end"
     * format Usctdp_Import_Session_Data::import_clinic_classes() builds on
     * import - see Usctdp_Mgmt_Clinic_Table::create_title()), so a
     * day/time edit has to recompute both alongside the schedule fields or
     * the activity's title would silently drift out of sync with its
     * actual schedule. Pure string-building only - no DB access - so the
     * caller stays in charge of when/how the result actually gets saved.
     */
    private function build_clinic_activity_title($product_title, $day_of_week, $start_time, $end_time)
    {
        $title = Usctdp_Mgmt_Clinic_Table::create_title(
            $product_title,
            Usctdp_Day_Of_Week::from($day_of_week)->name,
            DateTime::createFromFormat('H:i:s', $start_time),
            DateTime::createFromFormat('H:i:s', $end_time)
        );
        return [
            'title' => $title,
            'search_term' => Usctdp_Mgmt_Model::append_token_suffix($title),
        ];
    }

    /**
     * Saves a clinic activity's day-of-week/start-time/end-time and
     * refreshes the materialized program schedule to match. Deliberately
     * bypasses save_entity() - Usctdp_Mgmt_Clinic_Row casts day_of_week to a
     * Usctdp_Day_Of_Week enum and start_time/end_time to DateTime objects
     * (see class-usctdp-mgmt-clinic-row.php), so save_entity()'s
     * `$data !== $entity->$field` dirty-check would always see a type
     * mismatch and treat every save as "changed" - including a plain
     * resubmit of unchanged values, which Query::update_item() would then
     * correctly no-op (it diffs against the raw stored row) and report back
     * as a failure. Calling update_item() directly avoids that false
     * negative.
     */
    public function ajax_update_clinic_schedule()
    {
        $this->check_nonce('update_clinic_schedule');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        if (empty($activity_id)) {
            wp_send_json_error('Missing required parameter activity_id', 400);
        }

        try {
            $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
            if (!$activity) {
                wp_send_json_error('Activity with ID ' . $activity_id . ' not found.', 404);
            }
            if ($activity->type !== 'clinic') {
                wp_send_json_error('Only clinic activities have an editable schedule.', 400);
            }

            $day_of_week = isset($_POST['day_of_week']) ? intval($_POST['day_of_week']) : 0;
            if ($day_of_week < 1 || $day_of_week > 7) {
                wp_send_json_error('day_of_week must be between 1 (Monday) and 7 (Sunday).', 400);
            }

            $start_time = $this->sanitize_time_field($_POST['start_time'] ?? '');
            $end_time = $this->sanitize_time_field($_POST['end_time'] ?? '');
            if (!$start_time || !$end_time) {
                wp_send_json_error('start_time and end_time must be valid times.', 400);
            }
            if ($start_time >= $end_time) {
                wp_send_json_error('end_time must be after start_time.', 400);
            }

            $product = Usctdp_Mgmt_Model::get_product($activity->product_id);
            if (!$product) {
                wp_send_json_error('Product for activity ' . $activity_id . ' not found.', 500);
            }

            $clinic_query = new Usctdp_Mgmt_Clinic_Query();
            $clinic_query->update_item($activity_id, [
                'day_of_week' => $day_of_week,
                'start_time' => $start_time,
                'end_time' => $end_time,
            ]);

            $title_fields = $this->build_clinic_activity_title($product->title, $day_of_week, $start_time, $end_time);
            $activity_query = new Usctdp_Mgmt_Activity_Query();
            $activity_query->update_item($activity_id, $title_fields);

            $this->rebuild_program_schedule();

            // Echo the recomputed title back so the caller can refresh the
            // "Day" selector's label in place - it still shows whatever
            // title was current when the page/selector was populated, and
            // has no other way to learn it just went stale.
            wp_send_json_success([
                'day_of_week' => $day_of_week,
                'start_time' => substr($start_time, 0, 5),
                'end_time' => substr($end_time, 0, 5),
                'title' => $title_fields['title'],
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_clinic_schedule', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_update_activity()
    {
        $this->check_nonce('update_activity');

        $entity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter activity_id', 400);
        }

        $post_fields = [
            'level' => sanitize_text_field(...),
        ];

        try {
            $result = $this->save_entity(
                $entity_id,
                $_POST,
                'Usctdp_Mgmt_Activity_Query',
                $post_fields
            );
            wp_send_json_success($result);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_activity', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    /**
     * Updates capacity (and, for a shared group, name) on the reservation
     * group backing an activity's shared registration pool IN PLACE - not
     * a field on the activity itself, so this is separate from
     * ajax_update_activity() above, which only ever touches usctdp_activity.
     * Mirrors set_capacity()/rename() in class-usctdp-manage-reservation-groups.php
     * (the WP-CLI equivalents), combined into one call since the "Manage
     * Group" modal saves both from a single Save button.
     *
     * This never changes which group the activity belongs to - see
     * ajax_move_activity_to_group() and ajax_create_activity_group() below
     * for the two actions that do. A reservation group can be shared by
     * more than one activity - ajax_get_activity_details() already
     * surfaces that via 'shared_with', so the client can warn before this
     * silently changes capacity/name for every sibling activity too.
     */
    public function ajax_save_activity_group_details()
    {
        $this->check_nonce('save_activity_group_details');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $capacity = isset($_POST['capacity']) ? $_POST['capacity'] : null;
        // Absent (not just empty-string) means "leave the name as-is" -
        // the standalone-clinic view of the modal doesn't offer a name
        // field at all, so it must not clear a name the group might
        // already have from when it was last shared.
        $name_provided = isset($_POST['name']);
        $name = $name_provided ? sanitize_text_field($_POST['name']) : null;
        if (!$activity_id) {
            wp_send_json_error('activity_id is required.', 400);
        }
        if (!is_numeric($capacity) || intval($capacity) < 0) {
            wp_send_json_error('Capacity must be a non-negative integer.', 400);
        }

        try {
            $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
            if (!$activity) {
                wp_send_json_error('Activity with ID ' . $activity_id . ' not found.', 404);
            }

            $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
            $group = $group_query->get_group($activity->reservation_group_id);
            if (!$group) {
                wp_send_json_error('No reservation group found for this activity.', 404);
            }

            $update = [
                'capacity' => intval($capacity),
                'updated_at' => current_time('mysql', true),
            ];
            if ($name_provided) {
                $trimmed = trim($name);
                $update['name'] = $trimmed === '' ? null : $trimmed;
            }

            $result = $group_query->update_item($group->id, $update);
            if (!$result) {
                wp_send_json_error('Failed to save group details due to an unexpected server error.', 500);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_save_activity_group_details', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }

        wp_send_json_success(['capacity' => intval($capacity)]);
    }

    /**
     * Moves an activity to an already-existing reservation group - see
     * Usctdp_Mgmt_Reservation_Group_Query::move_activity_to_group() for why
     * this is a direct repoint rather than the CLI merge command's
     * always-create-a-new-group behavior.
     */
    public function ajax_move_activity_to_group()
    {
        $this->check_nonce('move_activity_to_group');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $target_group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        if (!$activity_id || !$target_group_id) {
            wp_send_json_error('activity_id and group_id are required.', 400);
        }

        try {
            $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
            $group_query->move_activity_to_group($activity_id, $target_group_id);
        } catch (Reservation_Group_Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_move_activity_to_group', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }

        wp_send_json_success(['message' => 'Activity moved to the selected group.']);
    }

    /**
     * Creates a brand-new dedicated reservation group and moves the given
     * activity into it - "splitting" it out of a shared group. See
     * Usctdp_Mgmt_Reservation_Group_Query::create_group_for_activity().
     */
    public function ajax_create_activity_group()
    {
        $this->check_nonce('create_activity_group');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $capacity = isset($_POST['capacity']) ? $_POST['capacity'] : null;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : null;
        if (!$activity_id) {
            wp_send_json_error('activity_id is required.', 400);
        }
        if (!is_numeric($capacity) || intval($capacity) < 0) {
            wp_send_json_error('Capacity must be a non-negative integer.', 400);
        }

        $new_group_id = null;
        try {
            $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
            $new_group_id = $group_query->create_group_for_activity($activity_id, $capacity, $name);
        } catch (Reservation_Group_Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_create_activity_group', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }

        wp_send_json_success(['group_id' => $new_group_id]);
    }

    public function ajax_activity_add_instructor()
    {
        $this->check_nonce('activity_add_instructor');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        if (!$activity_id || !$staff_id) {
            wp_send_json_error('Both activity_id and staff_id are required.', 400);
        }

        try {
            $staff_query = new Usctdp_Mgmt_Activity_Staff_Query();
            $staff_query->assign_staff($activity_id, $staff_id);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_activity_add_instructor', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success(['message' => 'Instructor added successfully.']);
    }

    public function ajax_activity_remove_instructor()
    {
        $this->check_nonce('activity_remove_instructor');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        if (!$activity_id || !$staff_id) {
            wp_send_json_error('Both activity_id and staff_id are required.', 400);
        }

        try {
            $staff_query = new Usctdp_Mgmt_Activity_Staff_Query();
            $staff_query->unassign_staff($activity_id, $staff_id);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_activity_remove_instructor', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success(['message' => 'Instructor removed successfully.']);
    }

    public function ajax_gen_statement()
    {
        $this->check_nonce('gen_statement');
        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $purchase_ids = isset($_POST['purchase_ids']) ? array_map('intval', $_POST['purchase_ids']) : [];
        if (empty($family_id)) {
            wp_send_json_error('Family ID is required.', 400);
        }
        if (empty($purchase_ids)) {
            wp_send_json_error('Purchase IDs are required.', 400);
        }
        try {
            $doc_gen = new Usctdp_Mgmt_Docgen();
            $document = $doc_gen->generate_financial_statement($family_id, $purchase_ids);
            $drive_file = $doc_gen->upload_to_google_drive($document, $family_id, 'statement-' . $family_id);
            wp_send_json_success([
                'message' => 'Statement generated successfully',
                'doc_id' => $drive_file->id,
                'doc_url' => $drive_file->webViewLink
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_gen_statement', $e);
            wp_send_json_error('An unexpected server error occurred during statement generation.', 500);
        }
    }

    public function ajax_get_family()
    {
        $this->check_nonce('get_family');
        try {
            $family_id = isset($_GET['family_id']) ? intval($_GET['family_id']) : null;
            if (!$family_id) {
                wp_send_json_error('Missing required parameter family_id', 400);
            }
            $family = Usctdp_Mgmt_Model::get_family($family_id);
            if (!$family) {
                wp_send_json_error("No family found with id: $family_id", 400);
            }
            wp_send_json_success($family);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_get_family', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_ledger_datatable()
    {
        $this->check_nonce('ledger_datatable');

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $account = isset($_POST['account']) ? sanitize_text_field($_POST['account']) : null;
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : null;
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : null;

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($family_id) {
            $args['family_id'] = $family_id;
        }
        if ($student_id) {
            $args['student_id'] = $student_id;
        }
        if ($account) {
            $args['account'] = $account;
        }
        if ($order_id) {
            $args['order_id'] = $order_id;
        }
        if ($purchase_id) {
            $args['purchase_id'] = $purchase_id;
        }

        $ledger_query = new Usctdp_Mgmt_Ledger_Query();
        $result = $ledger_query->get_ledger_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    /**
     * The dollar discount applied to a clinic's *second* registered day -
     * the amount subtracted from that day's own One-day base price so the
     * two combined land on the product's Two-day price. Same formula
     * bind_clinic_info() computes client-side (admin/js/usctdp-mgmt-admin-
     * register.js): two_day_price - base_price is the *increment* the
     * two-day tier adds over a single day, so the actual per-activity
     * discount is base_price minus that increment, not the increment
     * itself. Null when the product has no Two-day tier at all (e.g. a
     * tournament, or a clinic that isn't offered twice a week) - !empty()
     * here matches woocommerce-hooks.php's own "does a Two tier exist" check.
     */
    private function get_additional_day_discount($pricing, $base_price)
    {
        if (empty($pricing->pricing['Two'])) {
            return null;
        }
        $two_day_price = round(floatval($pricing->pricing['Two']), 2);
        $diff = round($two_day_price - $base_price, 2);
        return round($base_price - $diff, 2);
    }

    private function get_price_change($current_activity_id, $new_activity_id)
    {
        $current_activity = Usctdp_Mgmt_Model::get_activity($current_activity_id);
        if (!$current_activity) {
            return null;
        }
        $current_pricing = Usctdp_Mgmt_Model::get_activity_pricing($current_activity);
        if (!$current_pricing) {
            return null;
        }
        $current_base_price = round(floatval($current_pricing->pricing['One']), 2);
        $current_additional_day_discount = $this->get_additional_day_discount($current_pricing, $current_base_price);

        $new_activity = Usctdp_Mgmt_Model::get_activity($new_activity_id);
        if (!$new_activity) {
            return null;
        }
        $new_pricing = Usctdp_Mgmt_Model::get_activity_pricing($new_activity);
        if (!$new_pricing) {
            return null;
        }
        $new_base_price = round(floatval($new_pricing->pricing['One']), 2);
        $new_additional_day_discount = $this->get_additional_day_discount($new_pricing, $new_base_price);
        return [
            'delta' => round($new_base_price - $current_base_price, 2),
            'old_price' => $current_base_price,
            'new_price' => $new_base_price,
            'old_additional_day_discount' => $current_additional_day_discount,
            'new_additional_day_discount' => $new_additional_day_discount,
        ];
    }

    public function ajax_ledger_events_datatable()
    {
        $this->check_nonce('ledger_events_datatable');

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $account = isset($_POST['account']) ? sanitize_text_field($_POST['account']) : null;
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : null;

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($family_id) {
            $args['family_id'] = $family_id;
        }
        if ($account) {
            $args['account'] = $account;
        }
        if ($purchase_id) {
            $args['purchase_id'] = $purchase_id;
        }

        if (!$purchase_id) {
            wp_send_json_error('Missing required parameter purchase_id or account', 400);
        }

        $ledger_query = new Usctdp_Mgmt_Ledger_Query();
        $result = $ledger_query->get_ledger_events($args);
        foreach ($result['data'] as $row) {
            $row->order_url = null;
            if (!empty($row->order_id)) {
                $order = wc_get_order($row->order_id);
                if ($order) {
                    $row->order_url = get_edit_post_link($row->order_id);
                }
            }
        }
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }
    /**
     * Read-only counterpart to ajax_update_registration()'s price_change
     * computation - lets the admin history page preview a registration's
     * price/discount change *before* committing to it, so declining the
     * review is a true no-op rather than having to save-then-revert. Never
     * writes anything; the actual save still happens through
     * ajax_update_registration() afterward if the admin confirms.
     */
    public function ajax_preview_registration_activity_change()
    {
        $this->check_nonce('preview_registration_activity_change');

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : '';
        if (empty($registration_id)) {
            wp_send_json_error('Missing required parameter registration_id', 400);
        }
        $new_activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : '';
        if (empty($new_activity_id)) {
            wp_send_json_error('Missing required parameter activity_id', 400);
        }

        $registration = Usctdp_Mgmt_Model::get_registration($registration_id);
        if (!$registration) {
            wp_send_json_error('No registration found with id: ' . $registration_id, 400);
        }

        try {
            $price_change = $this->get_price_change($registration->activity_id, $new_activity_id);

            $purchase_query = new Usctdp_Mgmt_Purchase_Query();
            $purchase_data = $purchase_query->get_purchase_data([
                "purchase_id" => $registration->purchase_id
            ]);

            wp_send_json_success([
                'price_change' => $price_change,
                'purchase_data' => $purchase_data
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_preview_registration_activity_change', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_update_registration()
    {
        $this->check_nonce('update_registration');

        $entity_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter registration_id', 400);
        }

        $post_fields = [
            'student_level' => sanitize_text_field(...),
            'activity_id' => intval(...),
            'status' => sanitize_text_field(...),
        ];

        $registration = Usctdp_Mgmt_Model::get_registration($entity_id);
        if (!$registration) {
            wp_send_json_error('No registration found with id: ' . $entity_id, 400);
        }

        $price_change = 0;
        $purchase_data = null;
        if (isset($_POST['activity_id'])) {
            $current_activity = $registration->activity_id;
            $new_activity = intval($_POST['activity_id']);
            $price_change = $this->get_price_change($current_activity, $new_activity);

            $purchase_query = new Usctdp_Mgmt_Purchase_Query();
            $purchase_data = $purchase_query->get_purchase_data([
                "purchase_id" => $registration->purchase_id
            ]);
        }

        try {
            $result = $this->save_entity(
                $entity_id,
                $_POST,
                'Usctdp_Mgmt_Registration_Query',
                $post_fields
            );
            wp_send_json_success([
                'updated' => $result,
                'price_change' => $price_change,
                'purchase_data' => $purchase_data
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_registration', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_set_registration_status()
    {
        $this->check_nonce('set_registration_status');

        $entity_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter registration_id', 400);
        }

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        if (!in_array($status, ['active', 'void'], true)) {
            wp_send_json_error('Invalid status.', 400);
        }

        $registration = Usctdp_Mgmt_Model::get_registration($entity_id);
        if (!$registration) {
            wp_send_json_error('No registration found with id: ' . $entity_id, 400);
        }

        global $wpdb;
        $transaction_started = false;
        try {
            $wpdb->query('START TRANSACTION');
            $transaction_started = true;

            $registration_result = $this->save_entity(
                $entity_id,
                $_POST,
                'Usctdp_Mgmt_Registration_Query',
                ['status' => sanitize_text_field(...)]
            );

            $purchase_result = $this->save_entity(
                $registration->purchase_id,
                $_POST,
                'Usctdp_Mgmt_Purchase_Query',
                ['status' => sanitize_text_field(...)]
            );

            $wpdb->query('COMMIT');
            $transaction_started = false;

            wp_send_json_success([
                'registration' => $registration_result,
                'purchase' => $purchase_result,
            ]);
        } catch (Throwable $e) {
            if ($transaction_started) {
                $wpdb->query('ROLLBACK');
            }
            Usctdp_Mgmt::logger()->log_exception('ajax_set_registration_status', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_update_family()
    {
        $this->check_nonce('update_family');

        $entity_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter family_id', 400);
        }

        $post_fields = [
            'emails' => json_encode(...),
            'address' => sanitize_text_field(...),
            'city' => sanitize_text_field(...),
            'state' => sanitize_text_field(...),
            'zip' => sanitize_text_field(...),
            'notes' => function ($value) {
                return sanitize_textarea_field(stripslashes($value));
            },
            'phone_numbers' => json_encode(...)
        ];

        try {
            $result = $this->save_entity(
                $entity_id,
                $_POST,
                'Usctdp_Mgmt_Family_Query',
                $post_fields
            );
            wp_send_json_success($result);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_family', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    /**
     * Not routed through save_entity() like ajax_update_family(): the admin
     * edits the family's whole display title directly (e.g. "Smith 1234" -
     * see ajax_create_family for how that string is built), and last/
     * search_term have to be re-derived from it every time, same reasoning
     * as ajax_update_student() below.
     */
    public function ajax_update_family_name()
    {
        $this->check_nonce('update_family_name');

        $entity_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter family_id', 400);
        }

        $family = Usctdp_Mgmt_Model::get_family($entity_id);
        if (!$family) {
            wp_send_json_error('No family found with id: ' . $entity_id, 400);
        }

        try {
            $title = $this->get_sanitized_post_field_text('title') ?? '';
            $title = trim($title);
            if ($title === '') {
                wp_send_json_error('Family title is required.', 400);
            }

            // The last name is everything but the trailing numeric suffix
            // (the last four digits of the family's phone number - see
            // ajax_create_family) - falls back to the full title if there's
            // no trailing number to strip.
            $last_name = trim(preg_replace('/\s*\d+$/', '', $title));
            if ($last_name === '') {
                $last_name = $title;
            }
            $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);

            // Same "diff against current row" reasoning as
            // ajax_update_student() - BerlinDB's update_item() treats a
            // no-op save as a false return, which isn't a real failure.
            $changed = $family->last !== $last_name
                || $family->title !== $title
                || $family->search_term !== $search_term;

            if ($changed) {
                $family_query = new Usctdp_Mgmt_Family_Query();
                if (!$family_query->update_item($entity_id, [
                    'last' => $last_name,
                    'title' => $title,
                    'search_term' => $search_term,
                ])) {
                    wp_send_json_error('Failed to update family.', 500);
                }
            }

            wp_send_json_success($changed ? Usctdp_Mgmt_Model::get_family($entity_id) : $family);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_family_name', $e);
            wp_send_json_error('An unexpected server error occurred during family update.', 500);
        }
    }

    public function ajax_update_purchase()
    {
        $this->check_nonce('update_purchase');

        $entity_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter purchase_id', 400);
        }

        $post_fields = [
            'notes' => function ($value) {
                return sanitize_textarea_field(stripslashes($value));
            },
            'status' => sanitize_text_field(...),
            // Lets the admin history page keep this purchase's discount
            // snapshot in sync after a later registration-activity change
            // (see reviewPriceChange()/updateRegistration() in
            // usctdp-mgmt-admin-history.js) - without this, a discount
            // added/changed after the initial purchase never gets
            // remembered, so the "Current" column on the next edit's
            // Confirm Registration Update modal reads the stale, original
            // discount list. Same {code, value, amount, reason} shape (and
            // json_encode, matching what usctdp_purchase.discounts is
            // stored as) that parse_merchandise_data()/parse_registration_data()
            // use when a purchase is first created.
            'discounts' => function ($value) {
                // '' is the client's explicit "empty list" sentinel (see
                // updateRegistration() in usctdp-mgmt-admin-history.js) -
                // $.ajax's POST body drops an empty array entirely, so an
                // actually-empty list can't be told apart from "no discounts
                // field was sent" any other way.
                if ($value === '' || !is_array($value)) {
                    return '[]';
                }
                $sanitized = array_map(function ($discount) {
                    return [
                        'code' => isset($discount['code']) ? sanitize_text_field($discount['code']) : '',
                        'value' => isset($discount['value']) && is_numeric($discount['value']) ? floatval($discount['value']) : null,
                        'amount' => isset($discount['amount']) && is_numeric($discount['amount']) ? round(floatval($discount['amount']), 2) : 0,
                        'reason' => isset($discount['reason']) ? sanitize_text_field($discount['reason']) : '',
                    ];
                }, $value);
                return wp_json_encode($sanitized);
            },
        ];

        try {
            $result = $this->save_entity(
                $entity_id,
                $_POST,
                'Usctdp_Mgmt_Purchase_Query',
                $post_fields
            );
            wp_send_json_success($result);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_purchase', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    private function get_family_balance($family_id, $student_id = null)
    {
        $conditions = [];
        $args = [];

        $conditions[] = "ul.family_id = %d";
        $args[] = $family_id;
        if ($student_id) {
            $conditions[] = "ul.student_id = %d";
            $args[] = $student_id;
        }

        $conditions[] = "up.status = %s";
        $args[] = 'active';

        $conditions[] = "ul.account in (%s, %s)";
        $args[] = 'registration_fees';
        $args[] = 'merchandise_fees';

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT 
                    SUM(debit) - SUM(credit) as total_balance_due
                FROM {$wpdb->prefix}usctdp_ledger as ul
                JOIN {$wpdb->prefix}usctdp_purchase as up ON ul.purchase_id = up.id
                WHERE " . implode(' AND ', $conditions),
            $args
        );
        $result = $wpdb->get_row($query);
        return $result->total_balance_due;
    }

    private function get_house_credit_balance($family_id, $student_id = null)
    {
        $conditions = [];
        $args = [];

        $conditions[] = "ul.family_id = %d";
        $args[] = $family_id;
        if ($student_id) {
            $conditions[] = "ul.student_id = %d";
            $args[] = $student_id;
        }

        $conditions[] = "ul.account = %s";
        $args[] = 'payment_house_credit';

        // House credits on voided purchases should still appear
        //$conditions[] = "up.status = %s";
        //$args[] = 'active';

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT 
                    SUM(credit) - SUM(debit) as house_credit_balance
                FROM {$wpdb->prefix}usctdp_ledger as ul
                JOIN {$wpdb->prefix}usctdp_purchase as up ON ul.purchase_id = up.id
                WHERE " . implode(' AND ', $conditions),
            $args
        );
        $result = $wpdb->get_row($query);
        return $result->house_credit_balance;
    }

    public function ajax_get_family_balance()
    {
        $this->check_nonce('get_family_balance');
        try {
            $student_id = $this->get_sanitized_post_field_int('student_id');
            $family_id = $this->get_sanitized_post_field_int('family_id');
            if ($family_id === null || $family_id === 0) {
                wp_send_json_error('Family ID is required.', 400);
            }

            $balance = $this->get_family_balance($family_id, $student_id);
            $house_credit = $this->get_house_credit_balance($family_id, $student_id);
            wp_send_json_success([
                'balance' => $balance,
                'house_credit' => $house_credit
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_get_family_balance', $e);
            wp_send_json_error('An unexpected server error occurred during family balance retrieval.', 500);
        }
    }

    // Reserved placeholder product that imported/historical house credit
    // purchases attach to (see ajax_issue_house_credit) - product_id on
    // usctdp_purchase is NOT NULL, so a real row is needed even though this
    // one is never sold. Found-or-created lazily by its fixed `code` rather
    // than provisioned via activation, so it's self-healing if ever deleted.
    private function get_or_create_credit_import_product()
    {
        $code = 'house-credit-import';
        $product_query = new Usctdp_Mgmt_Product_Query([
            'code' => $code,
            'number' => 1
        ]);
        if (!empty($product_query->items)) {
            return $product_query->items[0]->id;
        }

        $product_query = new Usctdp_Mgmt_Product_Query();
        $product_id = $product_query->add_item([
            'woocommerce_id' => 0,
            'code' => $code,
            'type' => 'system',
            'title' => 'House Credit Import',
            'description' => 'Internal placeholder used to attach imported/historical house credit ledger entries. Not a real, purchasable product.',
        ]);
        if (!$product_id) {
            throw new Web_Request_Exception('Failed to create house credit import product.');
        }
        return $product_id;
    }

    // Issues house credit not tied to any real purchase or payment - e.g.
    // importing a balance a family already had in a prior system. Still
    // double-entry: debits a `credit_import_fees` account that no balance
    // query ever sums (so it doesn't affect what's owed) and credits
    // `payment_house_credit` (which get_house_credit_balance() does sum) -
    // the same pair createPayoutLedger(purchase_type, 'house_credit') would
    // produce for a real purchase, just attached to a placeholder purchase
    // instead. That placeholder purchase is typed 'credit_import' and has no
    // student, so it's excluded from Purchase History (see
    // ajax_purchase_history_datatable).
    public function ajax_issue_house_credit()
    {
        $this->check_nonce('issue_house_credit');

        $family_id = $this->get_sanitized_post_field_int('family_id');
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field(stripslashes($_POST['reason'])) : '';

        if (empty($family_id)) {
            wp_send_json_error('Family ID is required.', 400);
        }
        if ($amount <= 0) {
            wp_send_json_error('A positive amount is required.', 400);
        }
        if (empty($reason)) {
            wp_send_json_error('A reason is required.', 400);
        }

        global $wpdb;
        $transaction_started = false;
        try {
            $wpdb->query('START TRANSACTION');
            $transaction_started = true;

            $product_id = $this->get_or_create_credit_import_product();

            $purchase_query = new Usctdp_Mgmt_Purchase_Query();
            $purchase_id = $purchase_query->add_item([
                'product_id' => $product_id,
                'family_id' => $family_id,
                'student_id' => null,
                'type' => 'credit_import',
                'created_at' => current_time('mysql', true),
                'created_by' => get_current_user_id(),
                'notes' => $reason,
            ]);
            if (!$purchase_id) {
                throw new Web_Request_Exception('Failed to create purchase record for house credit.');
            }

            $amount_display = number_format($amount, 2, '.', '');
            $ledger_base = [
                'event_id' => 'credit_import_' . time() . '_' . $purchase_id,
                'event' => 'House Credit Import',
                'family_id' => $family_id,
                'purchase_id' => $purchase_id,
                'description' => 'Payout - House Credit - ' . $reason,
                'entry_type' => 'house_credit',
            ];
            $entries = [
                array_merge($ledger_base, [
                    'account' => 'credit_import_fees',
                    'debit' => $amount_display,
                    'credit' => '0.00',
                ]),
                array_merge($ledger_base, [
                    'account' => 'payment_house_credit',
                    'debit' => '0.00',
                    'credit' => $amount_display,
                ]),
            ];
            foreach ($entries as $entry) {
                if (!$this->create_ledger_entry($entry)) {
                    throw new Web_Request_Exception('Failed to create ledger entry for house credit.');
                }
            }

            $wpdb->query('COMMIT');
            $transaction_started = false;

            wp_send_json_success([
                'purchase_id' => $purchase_id,
                'balance' => $this->get_family_balance($family_id),
                'house_credit' => $this->get_house_credit_balance($family_id),
            ]);
        } catch (Web_Request_Exception $e) {
            if ($transaction_started) {
                $wpdb->query('ROLLBACK');
            }
            Usctdp_Mgmt::logger()->log_exception('ajax_issue_house_credit', $e);
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            if ($transaction_started) {
                $wpdb->query('ROLLBACK');
            }
            Usctdp_Mgmt::logger()->log_exception('ajax_issue_house_credit', $e);
            wp_send_json_error('An unexpected server error occurred while issuing house credit.', 500);
        }
    }

    public function ajax_create_family()
    {
        $this->check_nonce('create_family');

        try {
            $fields = [
                'emails' => function ($raw) {
                    return json_encode([$this->get_sanitized_post_field_text('email')]);
                },
                'last' => sanitize_text_field(...),
                'address' => sanitize_text_field(...),
                'city' => sanitize_text_field(...),
                'state' => sanitize_text_field(...),
                'zip' => sanitize_text_field(...),
                'phone_numbers' => function ($raw) {
                    return json_encode([$this->get_sanitized_post_field_text('phone')]);
                },
                'title' => function ($raw) {
                    $phone = trim($this->get_sanitized_post_field_text('phone'));
                    $last_four = substr($phone, -4);
                    $last_name = $this->get_sanitized_post_field_text('last');
                    return $last_name . ' ' . $last_four;
                },
                'search_term' => function ($raw) {
                    $phone = trim($this->get_sanitized_post_field_text('phone'));
                    $last_four = substr($phone, -4);
                    $last_name = $this->get_sanitized_post_field_text('last');
                    return Usctdp_Mgmt_Model::append_token_suffix($last_name . ' ' . $last_four);
                },
            ];
            $family_id = $this->create_entity($_POST, 'Usctdp_Mgmt_Family_Query', $fields);
            if (!$family_id) {
                wp_send_json_error('Failed to create family.', 500);
            }
            $family = Usctdp_Mgmt_Model::get_family($family_id);
            if (!$family) {
                wp_send_json_error('Failed to create family.', 500);
            }
            $last_name = $family->last;
            $phone = trim($family->phone_numbers[0]);
            $last_four = substr($phone, -4);
            $userdata = array(
                'user_login' => $last_name . $last_four,
                'user_pass' => bin2hex(random_bytes(24)),
                'user_email' => $family->emails[0] ?? '',
                'first_name' => 'Family Account',
                'last_name' => $last_name,
                'display_name' => $last_name . ' ' . $last_four,
                'role' => 'subscriber'
            );
            $user_id = wp_insert_user($userdata);
            if (is_wp_error($user_id)) {
                $family_query = new Usctdp_Mgmt_Family_Query();
                $family_query->delete_item($family_id);
                throw new Web_Request_Exception(
                    $user_id->get_error_message(),
                    500
                );
            }
            $family_query = new Usctdp_Mgmt_Family_Query(); 
            if (!$family_query->update_item($family_id, ['user_id' => $user_id])) {
                wp_delete_user($user_id);
                throw new Web_Request_Exception('Failed to link user account to family.', 500);
            }
            wp_send_json_success([
                'user_id' => $user_id,
                'family_id' => $family_id
            ], 200);
        } catch (Web_Request_Exception $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_create_family', $e);
            if ($family_id) {
                $family_query = new Usctdp_Mgmt_Family_Query([]);
                $family_query->delete_item($family_id);
            }
            wp_send_json_error($e->getMessage(), $e->getCode());
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_create_family', $e);
            if ($family_id) {
                $family_query = new Usctdp_Mgmt_Family_Query([]);
                $family_query->delete_item($family_id);
            }
            wp_send_json_error('An unexpected server error occurred during family creation.', 500);
        }
    }

    public function ajax_create_student()
    {
        $this->check_nonce('create_student');

        try {
            $fields = [
                'family_id' => intval(...),
                'first' => sanitize_text_field(...),
                'last' => sanitize_text_field(...),
                'level' => sanitize_text_field(...),
                'title' => function ($raw) {
                    $first_name = $this->get_sanitized_post_field_text('first');
                    $last_name = $this->get_sanitized_post_field_text('last');
                    return $first_name . ' ' . $last_name;
                },
                'search_term' => function () {
                    $first_name = $this->get_sanitized_post_field_text('first');
                    $last_name = $this->get_sanitized_post_field_text('last');
                    return Usctdp_Mgmt_Model::append_token_suffix($first_name . ' ' . $last_name);
                },
                'birth_date' => function ($raw) {
                    if (empty($raw)) {
                        return null;
                    }
                    $date = new DateTime($raw);
                    return $date->format('Y-m-d');
                },
            ];

            $student_id = $this->create_entity($_POST, 'Usctdp_Mgmt_Student_Query', $fields);
            if (!$student_id) {
                wp_send_json_error('Failed to create student.', 500);
            } else {
                wp_send_json_success([
                    'student_id' => $student_id
                ], 200);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_create_student', $e);
            wp_send_json_error('An unexpected server error occurred during student creation.', 500);
        }
    }

    /**
     * Not routed through save_entity() like the other single-entity
     * updaters: title/search_term have to be recomputed from first/last
     * every time (same derivation as create_student's), but save_entity()
     * only touches fields present as top-level keys in $_POST, and the
     * edit form has no title/search_term inputs of its own to send.
     */
    public function ajax_update_student()
    {
        $this->check_nonce('update_student');

        $entity_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter student_id', 400);
        }

        $student = Usctdp_Mgmt_Model::get_student($entity_id);
        if (!$student) {
            wp_send_json_error('No student found with id: ' . $entity_id, 400);
        }

        try {
            $first_name = $this->get_sanitized_post_field_text('first') ?? '';
            $last_name = $this->get_sanitized_post_field_text('last') ?? '';
            $level = $this->get_sanitized_post_field_text('level') ?? '';

            $birth_date = '';
            $birth_date_raw = $this->get_sanitized_post_field_text('birth_date');
            if (!empty($birth_date_raw)) {
                $birth_date = (new DateTime($birth_date_raw))->format('Y-m-d');
            }
            $current_birth_date = $student->birth_date ? $student->birth_date->format('Y-m-d') : '';

            $title = trim($first_name . ' ' . $last_name);
            $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);

            // BerlinDB's update_item() diffs the args against the current
            // row itself and returns false when nothing actually changed
            // (its "bail if nothing to save" case) - that's not a failure,
            // just a no-op, so check for a real change ourselves first
            // rather than reading its return value as pass/fail.
            $changed = $student->first !== $first_name
                || $student->last !== $last_name
                || $student->level !== $level
                || $current_birth_date !== $birth_date
                || $student->title !== $title
                || $student->search_term !== $search_term;

            if ($changed) {
                $student_query = new Usctdp_Mgmt_Student_Query();
                if (!$student_query->update_item($entity_id, [
                    'first' => $first_name,
                    'last' => $last_name,
                    'level' => $level,
                    'birth_date' => $birth_date,
                    'title' => $title,
                    'search_term' => $search_term,
                ])) {
                    wp_send_json_error('Failed to update student.', 500);
                }
            }

            wp_send_json_success($changed ? Usctdp_Mgmt_Model::get_student($entity_id) : $student);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_student', $e);
            wp_send_json_error('An unexpected server error occurred during student update.', 500);
        }
    }

    private function create_ledger_entry($source)
    {
        $fields = [
            'family_id' => intval(...),
            'student_id' => intval(...),
            'order_id' => intval(...),
            'event_id' => sanitize_text_field(...),
            'event' => sanitize_text_field(...),
            'description' => sanitize_text_field(...),
            'entry_type' => sanitize_text_field(...),
            'account' => sanitize_text_field(...),
            'purchase_id' => intval(...),
            'debit' => sanitize_text_field(...),
            'credit' => sanitize_text_field(...),
            'payment_method' => sanitize_text_field(...),
            'reference_id' => sanitize_text_field(...),
            'notes' => sanitize_text_field(...),
            'created_by' => function ($raw) {
                return get_current_user_id();
            },
            'created_at' => function ($raw) {
                return current_time('mysql', true);
            },
        ];
        return $this->create_entity($source, 'Usctdp_Mgmt_Ledger_Query', $fields);
    }
    public function ajax_create_ledger_entries()
    {
        $this->check_nonce('create_ledger_entries');
        $entries = isset($_POST['entries']) ? $_POST['entries'] : null;
        if (empty($entries)) {
            wp_send_json_error('No ledger entries provided.', 400);
        }

        global $wpdb;
        try {
            $wpdb->query('START TRANSACTION');
            $ids = [];
            foreach ($entries as $entry) {
                $result = $this->create_ledger_entry($entry);
                if ($result) {
                    $ids[] = $result;
                } else {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error('Failed to create ledger entry.', 500);
                }
            }
            $wpdb->query('COMMIT');
            wp_send_json_success($ids);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            Usctdp_Mgmt::logger()->log_exception('ajax_create_ledger_entries', $e);
            wp_send_json_error('An unexpected server error occurred during ledger entry creation.', 500);
        }
    }

    public function ajax_select2_search()
    {
        $this->check_nonce('select2_search');

        $results = [];
        try {
            $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $target = isset($_GET['target']) ? sanitize_text_field($_GET['target']) : '';

            if (empty($target)) {
                wp_send_json_error('No search target specified.', 400);
            }

            if (!Usctdp_Mgmt::select2()->is_valid_target($target)) {
                wp_send_json_error("Invalid target type: $target", 400);
            }

            $filters = [];
            foreach (Usctdp_Mgmt::select2()->get_filters($target) as $key => $parser) {
                $filters[$key] = isset($_GET[$key]) ? $parser($_GET[$key]) : null;
            }
            $results = Usctdp_Mgmt::select2()->search($target, $search, $filters);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_select2_search', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json(array('items' => $results));
    }

    /**
     * Feeds the main dashboard's Rosters widget - every roster group,
     * unpaginated (that widget renders its whole table in one go, no
     * DataTables server-side paging). Same data shape as
     * ajax_session_rosters_datatable(), just without the draw/paging
     * envelope - see Usctdp_Mgmt_Roster_Group_Query::search_rosters().
     */
    public function ajax_session_rosters()
    {
        $this->check_nonce('session_rosters');

        $query_results = [];
        try {
            $roster_query = new Usctdp_Mgmt_Roster_Group_Query();
            $query_results = $roster_query->search_rosters([])['data'];
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_session_rosters', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json(array('data' => $query_results));
    }

    public function ajax_session_rosters_datatable()
    {
        $this->check_nonce('session_rosters_datatable');

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $roster_query = new Usctdp_Mgmt_Roster_Group_Query();
        $result = $roster_query->search_rosters([
            "q" => $search_val,
            "number" => $length,
            "offset" => $start
        ]);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    /**
     * Feeds the Rosters page's "Regenerate All Rosters" button - one entry
     * per roster (a multi-session group counts once, not once per member).
     * Deliberately separate from ajax_session_rosters(), which the main
     * dashboard's active-sessions widget also consumes and which must stay
     * one-row-per-session for its per-session Hide/Show controls.
     */
    public function ajax_roster_regenerate_all()
    {
        $this->check_nonce('roster_regenerate_all');

        $results = [];
        try {
            $roster_query = new Usctdp_Mgmt_Roster_Group_Query();
            $rosters = $roster_query->search_rosters([])['data'];
            $results = array_map(function ($roster) {
                return [
                    'id' => $roster['id'],
                    'roster_group_id' => $roster['roster_group_id'],
                    'title' => $roster['name']
                ];
            }, $rosters);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_roster_regenerate_all', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json(array('data' => $results));
    }

    public function ajax_roster_rename()
    {
        $this->check_nonce('roster_rename');

        $roster_group_id = isset($_POST['roster_group_id']) ? intval($_POST['roster_group_id']) : 0;
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        if (!$roster_group_id && !$session_id) {
            wp_send_json_error('A roster_group_id or session_id is required.', 400);
        }
        try {
            $group_query = new Usctdp_Mgmt_Roster_Group_Query();
            // The modal always knows its own roster_group_id once the roster
            // is explicit - prefer that over re-deriving it from session_id,
            // which is ambiguous now that a session can be in more than one
            // roster. Only fall back to session_id for a still-implicit
            // roster, where no group row exists yet to have an id.
            if (!$roster_group_id) {
                $group = $group_query->get_or_create_for_session($session_id);
                $roster_group_id = $group->id;
            }
            $group_query->rename($roster_group_id, $name);
        } catch (Roster_Group_Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_roster_rename', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success(['message' => 'Roster renamed successfully.']);
    }

    public function ajax_roster_add_session()
    {
        $this->check_nonce('roster_add_session');

        $roster_group_id = isset($_POST['roster_group_id']) ? intval($_POST['roster_group_id']) : 0;
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $add_session_id = isset($_POST['add_session_id']) ? intval($_POST['add_session_id']) : 0;
        if ((!$roster_group_id && !$session_id) || !$add_session_id) {
            wp_send_json_error('A roster_group_id (or session_id) and add_session_id are required.', 400);
        }
        if ($add_session_id === $session_id) {
            wp_send_json_error('Cannot add a session to itself.', 400);
        }
        try {
            $group_query = new Usctdp_Mgmt_Roster_Group_Query();
            // See ajax_roster_rename() for why roster_group_id is preferred
            // over re-deriving the group from session_id.
            if (!$roster_group_id) {
                $group = $group_query->get_or_create_for_session($session_id);
                $roster_group_id = $group->id;
            }
            $group_query->add_session($roster_group_id, $add_session_id);
        } catch (Roster_Group_Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_roster_add_session', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success(['message' => 'Session added to roster successfully.']);
    }

    public function ajax_roster_remove_session()
    {
        $this->check_nonce('roster_remove_session');

        $roster_group_id = isset($_POST['roster_group_id']) ? intval($_POST['roster_group_id']) : 0;
        $remove_session_id = isset($_POST['remove_session_id']) ? intval($_POST['remove_session_id']) : 0;
        if (!$roster_group_id || !$remove_session_id) {
            wp_send_json_error('Both roster_group_id and remove_session_id are required.', 400);
        }
        try {
            // Unlike rename/add_session, this deliberately does NOT fall
            // back to get_or_create_for_session() for a still-implicit
            // roster - there's no real membership row to remove in that
            // case, so materializing a group here would only be to
            // immediately empty it back out again. The JS disables removal
            // for implicit rosters for the same reason; this is the
            // server-side backstop.
            $group_query = new Usctdp_Mgmt_Roster_Group_Query();
            $group_query->remove_session($roster_group_id, $remove_session_id);
        } catch (Roster_Group_Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_roster_remove_session', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success(['message' => 'Session removed from roster successfully.']);
    }

    /**
     * Starts a brand-new roster group seeded with one or more sessions, for
     * the Sessions tab's "Create Roster" button. The name is required on
     * that form - unlike get_or_create_for_session()'s implicit-roster path,
     * there's no session to fall back to naming it after here for a group
     * that never existed until this moment. Always creates a fresh group
     * (see Usctdp_Mgmt_Roster_Group_Query::create_group()) even if a chosen
     * session already belongs to another roster - group membership isn't
     * exclusive.
     */
    public function ajax_roster_create()
    {
        $this->check_nonce('roster_create');

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $session_ids = isset($_POST['session_ids']) ? (array) $_POST['session_ids'] : [];
        $session_ids = array_values(array_filter(array_map('intval', $session_ids)));
        if (trim($name) === '') {
            wp_send_json_error('Enter a name for the roster.', 400);
        }
        if (empty($session_ids)) {
            wp_send_json_error('Select at least one session to start the new roster with.', 400);
        }
        $group = null;
        try {
            $group_query = new Usctdp_Mgmt_Roster_Group_Query();
            $group = $group_query->create_group($name);
            foreach ($session_ids as $session_id) {
                $group_query->add_session($group->id, $session_id);
            }
        } catch (Roster_Group_Exception $e) {
            wp_send_json_error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_roster_create', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success(['roster_group_id' => $group->id]);
    }

    public function ajax_roster_delete_group()
    {
        $this->check_nonce('roster_delete_group');

        $roster_group_id = isset($_POST['roster_group_id']) ? intval($_POST['roster_group_id']) : 0;
        if (!$roster_group_id) {
            wp_send_json_error('roster_group_id is required.', 400);
        }
        try {
            $group_query = new Usctdp_Mgmt_Roster_Group_Query();
            $group_query->delete_group($roster_group_id);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_roster_delete_group', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success(['message' => 'Roster deleted successfully.']);
    }

    /**
     * Server-side paging for the Sessions admin page - the status each row
     * renders (and lets the admin change) is read from here and written
     * back by ajax_update_session_status() below, which also syncs
     * WooCommerce pricing/variations to match. See search_sessions_table()
     * in Usctdp_Mgmt_Session_Query.
     */
    public function ajax_sessions_datatable()
    {
        $this->check_nonce('sessions_datatable');

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        $session_query = new Usctdp_Mgmt_Session_Query();
        $result = $session_query->search_sessions_table([
            "q" => $search_val,
            "status" => $status_filter,
            "number" => $length,
            "offset" => $start
        ]);

        $data = array_map(function ($row) {
            $start_date = DateTime::createFromFormat('Y-m-d', $row->start_date);
            $end_date = DateTime::createFromFormat('Y-m-d', $row->end_date);
            return [
                'id' => (int) $row->id,
                'title' => $row->title,
                'dates' => ($start_date ? $start_date->format('M j, Y') : '') .
                    ' – ' . ($end_date ? $end_date->format('M j, Y') : ''),
                'status' => $row->status,
            ];
        }, $result['data']);

        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $data,
        );
        wp_send_json($response);
    }

    /**
     * Sets a session's lifecycle status - see the 'status' entry in
     * Usctdp_Mgmt_Session_Schema for what each state means - then:
     *   1. Rebuilds usctdp_program_schedule (see rebuild_program_schedule()
     *      and Usctdp_Build_Program_Schedule's docblock) - it includes every
     *      non-archived session, so a status change can move a session in
     *      or out of that materialized cache and it has no incremental
     *      update path, same reason ajax_update_clinic_schedule() rebuilds
     *      it after a schedule edit.
     *   2. Syncs WooCommerce (pricing/variations/attributes) for every
     *      product this session is priced under, via
     *      Usctdp_Mgmt_Woocommerce::sync_onsale_sessions_for_session().
     * The status write is the source of truth for the admin lifecycle
     * regardless of what happens in steps 1/2 - a hiccup in either
     * shouldn't silently roll it back, since 'scheduled'/'archived' are
     * still meaningful admin states on their own - but the caller does
     * need to know if either side didn't fully catch up, hence the
     * separate try/catches and the *_failed flags below.
     */
    public function ajax_update_session_status()
    {
        $this->check_nonce('update_session_status');

        $valid_statuses = ['scheduled', 'on_sale', 'archived'];
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        try {
            if (!$session_id) {
                wp_send_json_error('No session ID provided.', 400);
            }
            if (!in_array($status, $valid_statuses, true)) {
                wp_send_json_error('Invalid status provided.', 400);
            }
            $query = new Usctdp_Mgmt_Session_Query([]);
            $query_results = $query->update_item($session_id, [
                'status' => $status
            ]);
            if (!$query_results) {
                wp_send_json_error('Failed to update session status due to an unexpected server error.', 500);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_session_status', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }

        $schedule_rebuild_failed = false;
        try {
            $this->rebuild_program_schedule();
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_session_status:schedule', $e);
            $schedule_rebuild_failed = true;
        }

        $sync_failed = false;
        try {
            $woocommerce = new Usctdp_Mgmt_Woocommerce();
            $woocommerce->sync_onsale_sessions_for_session($session_id);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_session_status:sync', $e);
            $sync_failed = true;
        }

        if ($sync_failed || $schedule_rebuild_failed) {
            $problems = [];
            if ($schedule_rebuild_failed) {
                $problems[] = 'rebuilding the public program schedule';
            }
            if ($sync_failed) {
                $problems[] = 'syncing WooCommerce pricing/variations';
            }
            wp_send_json_success([
                'message' => 'Session status updated, but ' . implode(' and ', $problems) . ' failed. Please inform a developer.',
                'status' => $status,
                'sync_failed' => $sync_failed,
                'schedule_rebuild_failed' => $schedule_rebuild_failed,
            ]);
        }

        wp_send_json_success([
            'message' => 'Session status updated successfully',
            'status' => $status,
        ]);
    }

    /**
     * Feeds the Sessions page's pricing modal - every usctdp_pricing row
     * for a session, one per product it's priced under, with the product's
     * title/type joined in so the client knows which fields to render
     * (clinics: One/Two day price; tournaments: base/early_signup/
     * with_clinic). Read-only; ajax_update_pricing() below is the write
     * side.
     */
    public function ajax_get_session_pricing()
    {
        $this->check_nonce('get_session_pricing');

        $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        if (!$session_id) {
            wp_send_json_error('session_id is required.', 400);
        }

        $data = [];
        try {
            $pricing_query = new Usctdp_Mgmt_Pricing_Query([
                'session_id' => $session_id,
                'number' => 0,
            ]);
            foreach ($pricing_query->items as $row) {
                $product = Usctdp_Mgmt_Model::get_product($row->product_id);
                if (!$product) {
                    continue;
                }
                $data[] = [
                    'pricing_id' => (int) $row->id,
                    'product_id' => (int) $row->product_id,
                    'product_title' => $product->title,
                    'product_type' => $product->type,
                    'pricing' => $row->pricing ?: [],
                ];
            }
            usort($data, function ($a, $b) {
                return $a['product_id'] <=> $b['product_id'];
            });
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_get_session_pricing', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }

        wp_send_json_success(['data' => $data]);
    }

    /**
     * Saves one usctdp_pricing row's JSON blob (see the pricing_id lookup
     * below - one row is one (session, product) pair) from the modal's
     * $_POST['pricing'] fields, then resyncs WooCommerce for that product
     * via Usctdp_Mgmt_Woocommerce::sync_onsale_sessions_for_product() -
     * if the session this row belongs to is currently 'on_sale' for this
     * product, its live variation price needs to move with it; that method
     * already no-ops for a session that isn't on_sale on this product, so
     * it's always safe to call unconditionally here. Same two-phase
     * try/catch as ajax_update_session_status(): the pricing write is the
     * source of truth regardless of whether the sync afterward succeeds,
     * but the caller does need to know if it didn't, hence sync_failed.
     */
    public function ajax_update_pricing()
    {
        $this->check_nonce('update_pricing');

        $pricing_id = isset($_POST['pricing_id']) ? intval($_POST['pricing_id']) : 0;
        $raw_pricing = isset($_POST['pricing']) && is_array($_POST['pricing']) ? $_POST['pricing'] : [];
        $pricing = [];
        $product_id = null;

        try {
            if (!$pricing_id) {
                wp_send_json_error('pricing_id is required.', 400);
            }

            $pricing_query = new Usctdp_Mgmt_Pricing_Query(['id' => $pricing_id, 'number' => 1]);
            $existing = $pricing_query->items[0] ?? null;
            if (!$existing) {
                wp_send_json_error('Pricing record not found.', 404);
            }
            $product_id = $existing->product_id;

            // Only known keys, coerced to positive floats - a blank, zero,
            // or missing field is dropped rather than stored as 0. Matches
            // how the CLI importer writes this JSON
            // (import_clinic_prices()/import_tournament_pricing() in
            // class-usctdp-import-session-data.php) and how every reader of
            // it (get_session_price_lines(), sync_product_variations(),
            // etc.) already treats an absent key and an empty one the same.
            foreach (['One', 'Two', 'base', 'early_signup', 'with_clinic'] as $key) {
                if (isset($raw_pricing[$key]) && $raw_pricing[$key] !== '') {
                    $amount = max(0, floatval($raw_pricing[$key]));
                    if ($amount > 0) {
                        $pricing[$key] = $amount;
                    }
                }
            }

            $pricing_query->update_item($pricing_id, [
                'pricing' => json_encode($pricing),
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_pricing', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }

        $sync_failed = false;
        try {
            $woocommerce = new Usctdp_Mgmt_Woocommerce();
            $woocommerce->sync_onsale_sessions_for_product($product_id);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_update_pricing:sync', $e);
            $sync_failed = true;
        }

        wp_send_json_success([
            'pricing' => $pricing,
            'sync_failed' => $sync_failed,
        ]);
    }

    public function age_from_birth_date($birth_date)
    {
        $today = new DateTime('now');
        $age = $today->diff($birth_date);
        return $age->y;
    }

    public function ajax_student_datatable()
    {
        $this->check_nonce('student_datatable');

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;

        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }

        $args = [
            'family_id' => $family_id,
            'orderby' => 'id',
            'order' => 'DESC',
        ];

        $reg_query = new Usctdp_Mgmt_Student_Query($args);
        $results = [];
        foreach ($reg_query->items as $row) {
            $birth_date_str = $row->birth_date ? $row->birth_date->format('m/d/Y') : '--';
            $age_str = $row->birth_date ? strval($this->age_from_birth_date($row->birth_date)) : '--';
            $results[] = [
                "id" => $row->id,
                "first" => $row->first,
                "last" => $row->last,
                "birth_date" => $birth_date_str,
                "birth_date_raw" => $row->birth_date ? $row->birth_date->format('Y-m-d') : '',
                "age" => $age_str,
                "level" => $row->level,
            ];
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => count($results),
            "recordsFiltered" => count($results),
            "data" => $results,
        );
        wp_send_json($response);
    }

    public function ajax_purchase_history_datatable()
    {
        $this->check_nonce('purchase_history_datatable');

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
        $owes = isset($_POST['owes']) ? intval($_POST['owes']) : null;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
            // credit_import purchases are internal placeholders used to attach
            // imported house credit to a ledger entry (see ajax_issue_house_credit)
            // - they have no student and nothing to show, so never list them here.
            'exclude_type' => 'credit_import',
        ];
        if ($family_id) {
            $args['family_id'] = $family_id;
        }
        if ($student_id) {
            $args['student_id'] = $student_id;
        }
        if ($session_id) {
            $args['session_id'] = $session_id;
        }
        if ($owes == 1) {
            $args['owes'] = $owes;
        }
        if ($type) {
            $args['type'] = $type;
        }
        if ($status) {
            $args['status'] = $status;
        }

        $args = array_merge($args, $this->eastern_date_range_to_utc($date_from, $date_to));

        $purchase_query = new Usctdp_Mgmt_Purchase_Query([]);
        $results = $purchase_query->get_purchase_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $results['count'],
            "recordsFiltered" => $results['count'],
            "data" => $results['data']
        );
        wp_send_json($response);
    }

    /**
     * Formats one gross/receivable pair the way every row of the Earnings
     * dashboard (session rows, the totals tiles, the "Other / Unassigned"
     * line, and each product row in the drill-down) needs it: raw floats
     * for client-side math plus pre-formatted currency strings, matching
     * the NumberFormatter convention ajax_datatable_balances_detail() uses.
     */
    private function format_earnings_amounts($amount_fmt, $gross, $receivable)
    {
        $gross = (float) $gross;
        $receivable = (float) $receivable;
        return [
            'gross_revenue' => $gross,
            'gross_revenue_display' => $amount_fmt->format($gross),
            'receivable' => $receivable,
            'receivable_display' => $amount_fmt->format($receivable),
            'collected_display' => $amount_fmt->format($gross - $receivable),
        ];
    }

    /**
     * Feeds the Earnings dashboard's session list (usctdp-mgmt-admin-earnings.php)
     * - server-side paginated via Usctdp_Mgmt_Ledger_Query::get_session_earnings(),
     * same draw/recordsTotal/recordsFiltered/data envelope as
     * ajax_datatable_balances(), so it scales the same way as the number of
     * sessions grows. The summary tiles and the "Other / Unassigned" bucket
     * (purchases with no session, e.g. merchandise) are computed
     * separately, over the *whole* filtered range rather than just the
     * current page - see Usctdp_Mgmt_Ledger_Query::get_earnings_totals()/
     * get_unassigned_earnings(). PayPal fees are a single dashboard-wide
     * total, not per-session (see get_paypal_fees_total() for why).
     * date_from/date_to filter on purchase date, same Eastern-calendar-day
     * semantics as the Purchase History page. The DataTable's own search
     * box (search[value] in $_POST) filters the session list by name only -
     * it doesn't narrow the totals tiles.
     */
    public function ajax_earnings_rollup()
    {
        $this->check_nonce('earnings_rollup');

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;

        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;
        $range_args = $this->eastern_date_range_to_utc($date_from, $date_to);

        // DataTables' own search box (searching: true in earnings.js) posts
        // this by default - filters the session list by name only, same
        // LIKE search Usctdp_Mgmt_Roster_Group_Query::search_rosters() uses.
        // Deliberately doesn't narrow the totals/unassigned/PayPal-fee
        // figures below - those stay whole-range totals regardless of
        // search text, since the search box is for finding a row, not for
        // redefining what the dashboard's summary means.
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $search_args = $search_val !== '' ? array_merge($range_args, ['q' => $search_val]) : $range_args;

        try {
            $ledger_query = new Usctdp_Mgmt_Ledger_Query();
            $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

            $page_args = array_merge($search_args, ['number' => $length, 'offset' => $start]);
            $rows = $ledger_query->get_session_earnings($page_args);
            $total_count = $ledger_query->get_session_earnings_count($range_args);
            $filtered_count = $search_val !== ''
                ? $ledger_query->get_session_earnings_count($search_args)
                : $total_count;

            $data = [];
            foreach ($rows as $row) {
                $data[] = array_merge([
                    'session_id' => (int) $row->session_id,
                    'session_title' => $row->session_title,
                    'start_date' => $row->session_start_date,
                    'end_date' => $row->session_end_date,
                ], $this->format_earnings_amounts($amount_fmt, $row->gross_revenue, $row->receivable));
            }

            $totals = $ledger_query->get_earnings_totals($range_args);
            $unassigned = $ledger_query->get_unassigned_earnings($range_args);
            $paypal_fees = $ledger_query->get_paypal_fees_total($range_args);
            $net_revenue = (float) $totals->gross_revenue - $paypal_fees;

            wp_send_json([
                'draw' => $draw,
                'recordsTotal' => $total_count,
                'recordsFiltered' => $filtered_count,
                'data' => $data,
                'totals' => array_merge(
                    $this->format_earnings_amounts($amount_fmt, $totals->gross_revenue, $totals->receivable),
                    [
                        'paypal_fees' => $paypal_fees,
                        'paypal_fees_display' => $amount_fmt->format($paypal_fees),
                        'net_revenue' => $net_revenue,
                        'net_revenue_display' => $amount_fmt->format($net_revenue),
                    ]
                ),
                'unassigned' => $this->format_earnings_amounts($amount_fmt, $unassigned->gross_revenue, $unassigned->receivable),
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_earnings_rollup', $e);
            // Still a valid DataTables envelope (empty), not
            // wp_send_json_error()'s {success:false,...} shape - a
            // datatable's ajax.dataSrc always expects a `.data` array.
            wp_send_json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Feeds the Earnings dashboard's per-session drill-down: gross revenue
     * + accounts receivable for a single session, broken out by product
     * (e.g. its separate clinic/tournament offerings). Rendered as an
     * expandable accordion row under the session, not another paginated
     * table - the number of distinct products a single session offers is
     * small and bounded, unlike the session count itself, so
     * Usctdp_Mgmt_Ledger_Query::get_product_earnings_for_session() is
     * called with no number/offset, returning everything in one shot.
     */
    public function ajax_earnings_session_detail()
    {
        $this->check_nonce('earnings_session_detail');

        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        if (!$session_id) {
            wp_send_json_error('session_id is required.', 400);
        }

        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : null;
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : null;
        $range_args = $this->eastern_date_range_to_utc($date_from, $date_to);

        try {
            $ledger_query = new Usctdp_Mgmt_Ledger_Query();
            $rows = $ledger_query->get_product_earnings_for_session($session_id, $range_args);
            $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

            $products = [];
            foreach ($rows as $row) {
                $products[] = array_merge([
                    'product_id' => (int) $row->product_id,
                    'product_title' => $row->product_title,
                    'product_type' => $row->product_type,
                ], $this->format_earnings_amounts($amount_fmt, $row->gross_revenue, $row->receivable));
            }

            wp_send_json_success(['products' => $products]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_earnings_session_detail', $e);
            wp_send_json_error('An unexpected server error occurred while loading session earnings.', 500);
        }
    }

    public function ajax_recent_registrations()
    {
        $this->check_nonce('recent_registrations');

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 8;

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT
                    stud.first as student_first,
                    stud.last as student_last,
                    fam.title as family_name,
                    sesh.title as session_name,
                    act.title as activity_name,
                    reg.created_at as created_at
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_family AS fam ON stud.family_id = fam.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                WHERE reg.status = 'active'
                ORDER BY reg.id DESC
                LIMIT %d",
            $limit
        );

        $query_results = $wpdb->get_results($query);
        $output_data = [];
        if ($query_results) {
            foreach ($query_results as $result) {
                $output_data[] = [
                    "student_name" => $result->student_first . ' ' . $result->student_last,
                    "family_name" => $result->family_name,
                    "session_name" => $result->session_name,
                    "activity_name" => $result->activity_name,
                    "created_at" => $result->created_at,
                ];
            }
        }
        wp_send_json_success($output_data);
    }

    public function ajax_registrations_datatable()
    {
        $this->check_nonce('registrations_datatable');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($activity_id) {
            // Both callers of this endpoint (the register page's "View
            // Roster" modal and the Activities page's roster tab) want
            // everyone sharing this activity's reservation group, not just
            // this one activity - two clinics sharing a room/time slot
            // share one roster now that they share one capacity pool. This
            // degenerates to a single-activity result for a solo
            // (unmerged) activity's own dedicated 1:1 group.
            $group_query = new Usctdp_Mgmt_Reservation_Group_Query();
            $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
            $args['activity_ids'] = $activity
                ? $group_query->get_member_activity_ids($activity->reservation_group_id)
                : [$activity_id];
        }
        if ($student_id) {
            $args['student_id'] = $student_id;
        }
        if ($status) {
            $args['status'] = $status;
        }

        $reg_query = new Usctdp_Mgmt_Registration_Query([]);
        $result = $reg_query->get_registration_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    public function ajax_datatable_balances()
    {
        $this->check_nonce('datatable_balances');

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $min_balance = isset($_POST['min_balance']) ? intval($_POST['min_balance']) : 0;
        $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        global $wpdb;
        // Joined to usctdp_purchase and scoped to status='active' (mirrors
        // get_family_balance()) so a voided purchase's never-reversed
        // charge (see ajax_set_registration_status()) doesn't keep
        // inflating a family's balance here after the purchase itself was
        // voided. Also scoped to both fee accounts, not just
        // registration_fees - the original count_query missed
        // merchandise_fees entirely, undercounting a family whose only
        // outstanding balance was on a merchandise purchase.
        $query = $wpdb->prepare(
            "   SELECT
                    ledger.family_id,
                    MAX(fam.title) as family_name,
                    SUM(ledger.debit) as total_charges,
                    SUM(ledger.credit) as total_payments,
                    (SUM(ledger.debit) - SUM(ledger.credit)) as balance_due
                FROM {$wpdb->prefix}usctdp_ledger AS ledger
                JOIN {$wpdb->prefix}usctdp_family AS fam ON ledger.family_id = fam.id
                JOIN {$wpdb->prefix}usctdp_purchase AS pur ON ledger.purchase_id = pur.id
                WHERE ledger.account in ('registration_fees', 'merchandise_fees')
                AND pur.status = 'active'
                GROUP BY ledger.family_id
                HAVING balance_due > %d
                ORDER BY balance_due DESC
                LIMIT %d
                OFFSET %d",
            $min_balance,
            $length,
            $start
        );

        $count_query = $wpdb->prepare(
            "   SELECT COUNT(*) FROM (
                    SELECT ledger.family_id
                    FROM {$wpdb->prefix}usctdp_ledger AS ledger
                    JOIN {$wpdb->prefix}usctdp_purchase AS pur ON ledger.purchase_id = pur.id
                    WHERE ledger.account in ('registration_fees', 'merchandise_fees')
                    AND pur.status = 'active'
                    GROUP BY ledger.family_id
                    HAVING (SUM(ledger.debit) - SUM(ledger.credit)) > %d
                ) AS temp_table",
            $min_balance
        );

        $query_results = $wpdb->get_results($query);
        $output_data = [];

        if ($query_results) {
            foreach ($query_results as $result) {
                $output_data[] = [
                    "family_id" => $result->family_id,
                    "family_name" => $result->family_name,
                    "total_balance" => $amount_fmt->format($result->balance_due),
                ];
            }
        }

        $grand_total = $wpdb->get_var($count_query);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $grand_total,
            "recordsFiltered" => $grand_total,
            "data" => $output_data,
        );
        wp_send_json($response);
    }

    public function ajax_datatable_balances_detail()
    {
        $this->check_nonce('datatable_balances_detail');

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }

        $purchase_query = new Usctdp_Mgmt_Purchase_Query([]);
        $results = $purchase_query->get_purchase_data([
            'number' => $length,
            'offset' => $start,
            'family_id' => $family_id,
            'owes' => 1,
            'exclude_type' => 'credit_import',
        ]);

        $output_data = [];
        foreach ($results['data'] as $result) {
            $balance_due = ($result->total_fees - $result->total_adjustments)
                - ($result->total_payments - $result->total_refunds - $result->total_house_credits);
            $item_name = $result->purchase_type === 'registration'
                ? $result->session_name . ' - ' . $result->activity_name
                : $result->product_name;

            $output_data[] = [
                "student_name" => $result->student_first . ' ' . $result->student_last,
                "item" => $item_name,
                "balance" => $amount_fmt->format($balance_due),
                "purchase_id" => $result->purchase_id,
                "purchase_type" => $result->purchase_type,
                "family_id" => $result->family_id,
            ];
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => $results['count'],
            "recordsFiltered" => $results['count'],
            "data" => $output_data,
        );
        wp_send_json($response);
    }

    private function get_order_family_id($line_items)
    {
        $family_id = null;
        foreach ($line_items as $line_item) {
            if ($family_id === null) {
                $family_id = $line_item['family_id'];
            } else if ($family_id !== $line_item['family_id']) {
                return false;
            }
        }
        return $family_id;
    }

    private function raw_id_to_entity($data, $key, $getter, $entity_name)
    {
        if (!isset($data[$key])) {
            throw new Web_Request_Exception($entity_name . ' ID missing from registration data.');
        }
        $raw_id = $data[$key];
        if (!is_numeric($raw_id)) {
            throw new Web_Request_Exception($entity_name . ' ID is not a number.');
        }
        $entity = $getter($raw_id);
        if (!$entity) {
            throw new Web_Request_Exception($entity_name . ' with ID ' . $raw_id . ' not found.');
        }
        return [
            'id' => $raw_id,
            'entity' => $entity
        ];
    }

    private function parse_registration_data($data)
    {
        $activity = $this->raw_id_to_entity($data, 'activity_id', 'Usctdp_Mgmt_Model::get_activity', 'Activity');
        $family = $this->raw_id_to_entity($data, 'family_id', 'Usctdp_Mgmt_Model::get_family', 'Family');
        $student = $this->raw_id_to_entity($data, 'student_id', 'Usctdp_Mgmt_Model::get_student', 'Student');
        $product = $this->raw_id_to_entity($data, 'product_id', 'Usctdp_Mgmt_Model::get_product', 'Product');

        $student_level = '';
        if (isset($data['student_level'])) {
            $student_level = sanitize_text_field($data['student_level']);
        }
        if (empty($student_level)) {
            $student_level = $student['entity']->level;
        }

        $notes = '';
        if (isset($data['notes'])) {
            $notes = sanitize_textarea_field(stripslashes($data['notes']));
        }

        $discounts = null;
        if (isset($data['discounts'])) {
            $discounts = json_encode($data['discounts']);
        }

        $line_item_id = 0;
        if (isset($data['line_item_id'])) {
            $line_item_id = sanitize_text_field($data['line_item_id']);
        }

        return [
            "student" => $student['entity'],
            "family" => $family['entity'],
            "activity" => $activity['entity'],
            "product" => $product['entity'],
            "line_item_id" => $line_item_id,
            "type" => "registration",
            "sql_args" => [
                'activity_id' => $activity['id'],
                'product_id' => $product['id'],
                'family_id' => $family['id'],
                'student_id' => $student['id'],
                'student_level' => $student_level,
                'notes' => $notes,
                'discounts' => $discounts,
            ]
        ];
    }

    private function parse_merchandise_data($data)
    {
        $family = $this->raw_id_to_entity($data, 'family_id', 'Usctdp_Mgmt_Model::get_family', 'Family');
        $student = $this->raw_id_to_entity($data, 'student_id', 'Usctdp_Mgmt_Model::get_student', 'Student');
        $product = $this->raw_id_to_entity($data, 'product_id', 'Usctdp_Mgmt_Model::get_product', 'Product');

        $line_item_id = 0;
        if (isset($data['line_item_id'])) {
            $line_item_id = sanitize_text_field($data['line_item_id']);
        }

        $discounts = null;
        if (isset($data['discounts'])) {
            $discounts = json_encode($data['discounts']);
        }

        return [
            "student" => $student['entity'],
            "family" => $family['entity'],
            "product" => $product['entity'],
            "line_item_id" => $line_item_id,
            "type" => "merchandise",
            "sql_args" => [
                'product_id' => $product['id'],
                'family_id' => $family['id'],
                'student_id' => $student['id'],
                'discounts' => $discounts,
            ]
        ];
    }

    private function parse_order_data($data)
    {
        $type = isset($data['type']) ? sanitize_text_field($data['type']) : null;
        if ($type === 'registration') {
            return $this->parse_registration_data($data);
        } elseif ($type === 'merchandise') {
            return $this->parse_merchandise_data($data);
        } else {
            throw new Web_Request_Exception('Invalid order type: ' . $type);
        }
    }

    private function create_merchandise_order($record)
    {
        $query = new Usctdp_Mgmt_Purchase_Query();
        $purchase_id = $query->add_item([
            'product_id' => $record['product']->id,
            'family_id' => $record['family']->id,
            'student_id' => $record['student']->id,
            'type' => 'merchandise',
            'created_at' => current_time('mysql', true),
            'created_by' => get_current_user_id(),
        ]);
        if (!$purchase_id) {
            throw new Web_Request_Exception('Failed to create merchandise.');
        }
        return $purchase_id;
    }

    /**
     * Locks the reservation groups behind these registrations, not the
     * activity rows themselves - two different activities sharing a group
     * could otherwise each take their own activity-row lock and both pass
     * a stale capacity check against the same shared pool (see
     * Usctdp_Mgmt_Reservation_Group_Table, and the matching lock in
     * Usctdp_Mgmt_Woocommerce_Hooks::after_checkout_validation()).
     */
    private function lock_registrations($registration_records)
    {
        global $wpdb;
        $group_ids = array_unique(array_map(function ($record) {
            return (int) $record["activity"]->reservation_group_id;
        }, $registration_records));

        if (!empty($group_ids)) {
            $placeholders = implode(',', array_fill(0, count($group_ids), '%d'));
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}usctdp_reservation_group WHERE id IN ($placeholders) FOR UPDATE",
                    $group_ids
                )
            );
        }
    }
    /**
     * Creates the registration as 'active' immediately (the schema's own
     * default - status is deliberately absent from $registration_args
     * below), regardless of payment method - including 'card', where the
     * family is switched into their own session and sent to a real
     * order-pay page (see payment_checkout_handler in
     * class-usctdp-mgmt-admin.php) that can genuinely fail or be declined.
     *
     * This is intentional, not an oversight: unlike the storefront's own
     * checkout - where after_checkout_validation() reserves a seat as
     * 'pending' and release_registrations_for_order() (class-usctdp-mgmt-
     * woocommerce-hooks.php) voids it if that order later fails or is
     * cancelled - an admin registering someone here is a deliberate staff
     * action/commitment to enroll the student, not a self-checkout
     * attempt. release_registrations_for_order() only ever matches
     * 'pending' registrations, so it silently never touches these even
     * when the same order later fails - a declined card just leaves a
     * visible balance on the family's account for staff to resolve
     * manually (retry payment, take a different method, or void the
     * registration themselves), rather than the system silently
     * un-enrolling a student staff already committed to registering.
     */
    private function create_purchase_and_registration($args)
    {
        $created_by = get_current_user_id();
        $created_at = current_time('mysql', true);
        $purchase_query = new Usctdp_Mgmt_Purchase_Query();
        $purchase_args = [
            'product_id' => $args['product_id'],
            'family_id' => $args['family_id'],
            'student_id' => $args['student_id'],
            'type' => 'registration',
            'created_at' => $created_at,
            'created_by' => $created_by,
            'discounts' => $args['discounts'] ?? '[]',
        ];
        $purchase_id = $purchase_query->add_item($purchase_args);
        if (!$purchase_id) {
            throw new Web_Request_Exception('Failed to create purchase.');
        }

        $registration_args = [
            'purchase_id' => $purchase_id,
            'activity_id' => $args['activity_id'],
            'student_id' => $args['student_id'],
            'student_level' => $args['student_level'],
            'notes' => $args['notes'],
            'created_at' => $created_at,
            'created_by' => $created_by,
            'modified_at' => $created_at,
            'modified_by' => $created_by,
        ];

        $registration_query = new Usctdp_Mgmt_Registration_Query();
        $registration_id = $registration_query->add_item($registration_args);
        if (!$registration_id) {
            throw new Web_Request_Exception('Failed to create registration.');
        }

        return [
            'purchase_id' => $purchase_id,
            'registration_id' => $registration_id
        ];
    }

    /**
     * Creates the purchase/registration records for a set of order line items.
     * Pure business logic - no transaction handling or JSON response, so it can
     * be composed into a larger transaction (see ajax_submit_payment()).
     *
     * @return array Results keyed by line_item_id, each ['purchase_id' => ..., 'registration_id' => ...]
     */
    private function create_order_records($line_items, $ignore_full)
    {
        $results = [];

        $order_records = [];
        foreach ($line_items as $line_item) {
            $order_records[] = $this->parse_order_data($line_item);
        }

        $merchandise_records = array_filter($order_records, function ($record) {
            return $record['type'] === 'merchandise';
        });

        $registration_records = array_filter($order_records, function ($record) {
            return $record['type'] === 'registration';
        });

        // Handle merchandise orders
        foreach ($merchandise_records as $record) {
            $line_item_id = $record['line_item_id'];
            $results[$line_item_id] = [
                'purchase_id' => $this->create_merchandise_order($record),
            ];
        }

        // Handle registration orders
        $this->lock_registrations($registration_records);
        foreach ($registration_records as $record) {
            $args = $record['sql_args'];
            $line_item_id = $record['line_item_id'];
            $student_id = $args['student_id'];
            $activity_id = $args['activity_id'];
            $activity_title = $record['activity']->title;
            if ($this->is_student_enrolled($student_id, $activity_id)) {
                throw new Web_Request_Exception('Student is already enrolled in activity: ' . $activity_title);
            }

            $capacity = $this->get_activity_capacity($activity_id);
            $enrollment_counts = $this->get_activity_enrollment_counts($activity_id);
            if (!$ignore_full && $enrollment_counts['active'] >= $capacity) {
                throw new Web_Request_Exception('Class is full: ' . $activity_title);
            }

            $ids = $this->create_purchase_and_registration($args);
            if (!$ids) {
                throw new Web_Request_Exception('Failed to create registration.');
            }
            $this->remove_waitlist_entry($student_id, $activity_id);
            $results[$line_item_id] = $ids;
        }

        return $results;
    }

    public function ajax_commit_merchandise()
    {
        $this->check_nonce('commit_merchandise');

        global $wpdb;
        $transaction_started = false;
        $transaction_completed = false;
        $response_message = '';
        $purchase_ids = [];

        $merchandise_data = isset($_POST['merchandise_data']) ? $_POST['merchandise_data'] : [];
        if (empty($merchandise_data)) {
            throw new Web_Request_Exception('No merchandise data provided.');
        }

        $merchandise_records = [];
        foreach ($merchandise_data as $merchandise) {
            $merchandise_records[] = $this->parse_merchandise_data($merchandise);
        }

        try {
            $wpdb->query('START TRANSACTION');
            $transaction_started = true;
            foreach ($merchandise_records as $record) {
                $line_item_id = $record['line_item_id'];
                $query = new Usctdp_Mgmt_Purchase_Query();
                $purchase_id = $query->add_item([
                    'product_id' => $record['product']->id,
                    'family_id' => $record['family']->id,
                    'student_id' => $record['student']->id,
                    'discounts' => $record['sql_args']['discounts'],
                    'type' => 'merchandise',
                    'created_at' => current_time('mysql', true),
                    'created_by' => get_current_user_id(),
                ]);
                if (!$purchase_id) {
                    throw new Web_Request_Exception('Failed to create merchandise.');
                }
                $purchase_ids[$line_item_id] = $purchase_id;
            }
            $wpdb->query('COMMIT');
            $transaction_completed = true;
        } catch (Web_Request_Exception $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_commit_merchandise', $e);
            $response_message = $e->getMessage();
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_commit_merchandise', $e);
            $response_message = 'A system error occurred. Please try again.';
        } finally {
            if (!$transaction_completed) {
                if ($transaction_started) {
                    $wpdb->query('ROLLBACK');
                }
                if ($response_message === '') {
                    $response_message = 'A system error occurred. Please try again.';
                }
                wp_send_json_error($response_message, 500);
            } else {
                wp_send_json_success([
                    "ids" => $purchase_ids
                ]);
            }
        }
    }
    private function sanitize_payment_line_item($item)
    {
        $type = isset($item['type']) ? sanitize_text_field($item['type']) : null;
        if (!in_array($type, ['registration', 'merchandise'], true)) {
            throw new Web_Request_Exception('Invalid line item type.');
        }

        $discounts = [];
        if (!empty($item['discounts'])) {
            foreach ($item['discounts'] as $discount) {
                $discounts[] = [
                    'amount' => round(floatval($discount['amount'] ?? 0), 2),
                    'reason' => sanitize_text_field($discount['reason'] ?? ''),
                ];
            }
        }

        return [
            'line_item_id' => sanitize_text_field($item['line_item_id'] ?? ''),
            'type' => $type,
            'family_id' => intval($item['family_id'] ?? 0),
            'student_id' => intval($item['student_id'] ?? 0),
            'purchase_id' => !empty($item['purchase_id']) ? intval($item['purchase_id']) : null,
            'base_price' => round(floatval($item['base_price'] ?? 0), 2),
            'debit' => round(floatval($item['debit'] ?? 0), 2),
            'credit' => round(floatval($item['credit'] ?? 0), 2),
            'discounts' => $discounts,
        ];
    }

    private function format_snake_case($str)
    {
        if (empty($str)) {
            return '';
        }
        return ucwords(strtolower(trim(str_replace('_', ' ', $str))));
    }

    private function build_payment_event_description($payment_mode, $payment_method, $check_number)
    {
        if ($payment_mode === 'create') {
            switch ($payment_method) {
                case 'check':
                    return 'Purchase w/ Check #' . $check_number;
                case 'cash':
                    return 'Purchase w/ Cash';
                case 'card':
                    return 'Order Initiated, Card Details Pending';
                case 'house_credit_only':
                    return 'Purchase Paid w/ House Credit';
                default:
                    return 'Order Initiated, Payment Pending';
            }
        }

        switch ($payment_method) {
            case 'check':
                return 'Payment Made w/ Check #' . $check_number;
            case 'cash':
                return 'Payment Made w/ Cash';
            case 'card':
                return 'Payment Initiated, Card Details Pending';
            case 'house_credit_only':
                return 'Payment Made w/ House Credit';
            default:
                return '';
        }
    }

    /**
     * Builds the double-entry ledger rows for a single payment line item:
     * the initial charge/revenue/discount entries (only for newly created
     * purchases) plus the payment and house-credit entries for this
     * submission. Mirrors what USCTDP_Admin.buildLedgerEntries used to do
     * in JS - this is now the single place that knows the accounting rules.
     */
    private function build_ledger_entries_for_line_item(
        $line_item,
        $order_id,
        $event_id,
        $event,
        $payment_method,
        $check_number,
        $is_new
    ) {
        $entries = [];
        $zero = '0.00';
        $base = [
            'family_id' => $line_item['family_id'],
            'student_id' => $line_item['student_id'],
            'purchase_id' => $line_item['purchase_id'],
            'order_id' => $order_id,
            'event_id' => $event_id,
            'event' => $event,
        ];

        if ($is_new) {
            $base_price = number_format($line_item['base_price'], 2, '.', '');
            $entries[] = array_merge($base, [
                'account' => $line_item['type'] . '_fees',
                'debit' => $base_price,
                'credit' => $zero,
                'entry_type' => 'charge',
                'description' => 'Base Fee',
            ]);
            $entries[] = array_merge($base, [
                'account' => 'revenue',
                'debit' => $zero,
                'credit' => $base_price,
                'entry_type' => 'charge',
                'description' => 'Base Fee',
            ]);

            foreach ($line_item['discounts'] as $discount) {
                $amount = number_format($discount['amount'], 2, '.', '');
                $entries[] = array_merge($base, [
                    'account' => $line_item['type'] . '_fees',
                    'debit' => $zero,
                    'credit' => $amount,
                    'entry_type' => 'adjustment',
                    'description' => $discount['reason'],
                ]);
                $entries[] = array_merge($base, [
                    'account' => 'revenue',
                    'debit' => $amount,
                    'credit' => $zero,
                    'entry_type' => 'adjustment',
                    'description' => $discount['reason'],
                ]);
            }
        }

        $check_str = $check_number ? " #$check_number" : '';
        $event_str = 'Payment (' . $this->format_snake_case($payment_method) . $check_str . ')';
        $house_credit = round($line_item['house_credit'] ?? 0, 2);
        $amount_after_house_credit = round($line_item['credit'] - $house_credit, 2);

        if ($amount_after_house_credit > 0 && $payment_method !== 'card') {
            $amount_str = number_format($amount_after_house_credit, 2, '.', '');
            $entries[] = array_merge($base, [
                'account' => 'payment_' . $payment_method,
                'payment_method' => $payment_method,
                'reference_id' => $check_number ?: null,
                'debit' => $amount_str,
                'credit' => $zero,
                'entry_type' => 'payment',
                'description' => $event_str,
            ]);
            $entries[] = array_merge($base, [
                'account' => $line_item['type'] . '_fees',
                'payment_method' => $payment_method,
                'reference_id' => $check_number ?: null,
                'debit' => $zero,
                'credit' => $amount_str,
                'entry_type' => 'payment',
                'description' => $event_str,
            ]);
        }

        // Deferred for card, same as the payment entries above and for the
        // same reason: house credit isn't actually *spent* until the order
        // genuinely completes (record_deferred_payment(), class-usctdp-mgmt-
        // woocommerce-hooks.php, which reads back _house_credit_amount item
        // meta create_woocommerce_order() records for exactly this). Writing
        // it here unconditionally used to record it immediately, while the
        // order was still 'pending' - wrong on its own (a declined/abandoned
        // card shouldn't have already drawn down the family's house credit),
        // and it also broke record_deferred_payment()'s own dedup check:
        // that check matches on purchase_id+order_id with no entry_type
        // filter (so it also recognizes its own house_credit write on a
        // repeat hook fire), so it was finding *this* block's early write
        // and skipping the entire per-item block - including the actual
        // payment_card entries it was supposed to write once the order
        // completed.
        if ($house_credit > 0 && $payment_method !== 'card') {
            $house_credit_str = number_format($house_credit, 2, '.', '');
            $entries[] = array_merge($base, [
                'account' => 'payment_house_credit',
                'debit' => $house_credit_str,
                'credit' => $zero,
                'entry_type' => 'house_credit',
                'description' => 'House Credit Applied',
            ]);
            $entries[] = array_merge($base, [
                'account' => $line_item['type'] . '_fees',
                'debit' => $zero,
                'credit' => $house_credit_str,
                'entry_type' => 'house_credit',
                'description' => 'House Credit Applied',
            ]);
        }

        return $entries;
    }

    /**
     * Consolidated payment submission: creates purchases/registrations (when
     * payment_mode is "create"), allocates house credit across line items,
     * creates the WooCommerce order for card payments, and writes the full
     * set of ledger entries - all in a single transaction. This replaces the
     * old three-round-trip flow (commit_order -> create_woocommerce_order ->
     * create_ledger_entries) where the browser assembled the ledger entries
     * itself and the server trusted whatever amounts it sent.
     */
    public function ajax_submit_payment()
    {
        $this->check_nonce('submit_payment');

        global $wpdb;
        $transaction_started = false;
        $transaction_completed = false;
        $response_message = '';
        $order_info = null;
        $purchases = [];

        $raw_line_items = isset($_POST['line_items']) ? $_POST['line_items'] : [];
        if (empty($raw_line_items)) {
            throw new Web_Request_Exception('No line items provided.');
        }

        $payment_mode = isset($_POST['payment_mode']) ? sanitize_text_field($_POST['payment_mode']) : 'update';
        $is_new = $payment_mode === 'create';

        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        if (empty($payment_method)) {
            throw new Web_Request_Exception('No payment method provided.');
        }

        $check_number = isset($_POST['check_number']) && $_POST['check_number'] !== ''
            ? sanitize_text_field($_POST['check_number'])
            : null;
        $house_credit_applied = round(floatval($_POST['house_credit_applied'] ?? 0), 2);
        $ignore_full = isset($_POST['ignore_class_full']) && $_POST['ignore_class_full'] === '1';

        $line_items = [];
        foreach ($raw_line_items as $item) {
            $line_items[] = $this->sanitize_payment_line_item($item);
        }

        $family_id = $this->get_order_family_id($line_items);
        if (!$family_id) {
            throw new Web_Request_Exception('No unique family ID found for the line items.');
        }

        try {
            $wpdb->query('START TRANSACTION');
            $transaction_started = true;

            if ($is_new) {
                $purchases = $this->create_order_records($raw_line_items, $ignore_full);
                foreach ($line_items as &$line_item) {
                    $created = $purchases[$line_item['line_item_id']] ?? null;
                    if (!$created) {
                        $msg = 'Line item ' . $line_item['line_item_id'] . ' not found in created purchases.';
                        throw new Web_Request_Exception($msg);
                    }
                    $line_item['purchase_id'] = $created['purchase_id'];
                    if ($line_item['type'] === 'registration') {
                        $line_item['registration_id'] = $created['registration_id'];
                    }
                }
                unset($line_item);
            }

            // Allocate house credit across line items in submission order, same
            // waterfall the client used to run before handing entries to the server.
            $remaining_house_credit = $house_credit_applied;
            foreach ($line_items as &$line_item) {
                if ($remaining_house_credit <= 0) {
                    break;
                }
                $allocated = min($remaining_house_credit, $line_item['credit']);
                $line_item['house_credit'] = round($allocated, 2);
                $remaining_house_credit = round($remaining_house_credit - $allocated, 2);
            }
            unset($line_item);

            $order_id = null;
            $event_id = 'order_payment_' . $payment_method;
            if ($payment_method === 'card') {
                $wc_line_items = [];
                foreach ($raw_line_items as $idx => $item) {
                    $item['house_credit'] = $line_items[$idx]['house_credit'] ?? 0;
                    if ($is_new) {
                        $item['purchase_id'] = $line_items[$idx]['purchase_id'];
                        if ($line_items[$idx]['type'] === 'registration') {
                            $item['registration_id'] = $line_items[$idx]['registration_id'];
                        }
                    }
                    $wc_line_items[] = $item;
                }
                $wc_result = Usctdp_Mgmt::woocommerce()->create_woocommerce_order(
                    $family_id,
                    $wc_line_items,
                    $payment_method,
                    $check_number
                );
                if (empty($wc_result) || empty($wc_result['order'])) {
                    throw new Web_Request_Exception('Failed to create WooCommerce order.');
                }
                $order = $wc_result['order'];
                $order_id = $order->get_id();
                $order_info = [
                    'order_id' => $order_id,
                    'order_url' => get_edit_post_link($order_id),
                    'payment_url' => $order->get_checkout_payment_url(),
                    'user_id' => $wc_result['user_id'],
                ];
                $event_id = 'order_card_' . $order_id;
            }

            $event = $this->build_payment_event_description($payment_mode, $payment_method, $check_number);

            $ledger_ids = [];
            foreach ($line_items as $line_item) {
                $entries = $this->build_ledger_entries_for_line_item(
                    $line_item,
                    $order_id,
                    $event_id,
                    $event,
                    $payment_method,
                    $check_number,
                    $is_new
                );
                foreach ($entries as $entry) {
                    $ledger_id = $this->create_ledger_entry($entry);
                    if (!$ledger_id) {
                        throw new Web_Request_Exception('Failed to create ledger entry.');
                    }
                    $ledger_ids[] = $ledger_id;
                }
            }

            $wpdb->query('COMMIT');
            $transaction_completed = true;
        } catch (Web_Request_Exception $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_submit_payment', $e);
            $response_message = $e->getMessage();
        } catch (Usctdp_Woocommerce_Exception $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_submit_payment', $e);
            $response_message = $e->getMessage();
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_submit_payment', $e);
            $response_message = 'A system error occurred. Please try again.';
        } finally {
            if (!$transaction_completed) {
                if ($transaction_started) {
                    $wpdb->query('ROLLBACK');
                }
                if ($order_info && !empty($order_info['order_id'])) {
                    // WooCommerce order creation isn't purely wpdb writes (it can
                    // trigger its own hooks/side effects), so it isn't safely
                    // covered by our ROLLBACK - clean it up explicitly.
                    $wc_order = wc_get_order($order_info['order_id']);
                    if ($wc_order) {
                        $wc_order->delete(true);
                    }
                }
                if ($response_message === '') {
                    $response_message = 'A system error occurred. Please try again.';
                }
                wp_send_json_error($response_message, 500);
            } else {
                wp_send_json_success([
                    'order' => $order_info,
                    'purchases' => $purchases,
                    'ledger_entries' => $ledger_ids,
                ]);
            }
        }
    }

    public function ajax_waitlist_datatable()
    {
        $this->check_nonce('waitlist_datatable');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($activity_id) {
            $args['activity_id'] = $activity_id;
        }

        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query([]);
        $result = $waitlist_query->get_waitlist_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    public function ajax_waitlist_add()
    {
        $this->check_nonce('waitlist_add');
        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;

        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query([
            'activity_id' => $activity_id,
            'student_id' => $student_id,
        ]);

        if (!empty($waitlist_query->items)) {
            wp_send_json_success('Student is already on the waitlist for this activity.');
        } else {
            $result = $waitlist_query->add_item([
                'activity_id' => $activity_id,
                'student_id' => $student_id,
                'created_at' => current_time('mysql', true),
                'status' => 'pending',
            ]);
            if ($result) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error('Failed to add student to waitlist.');
            }
        }
    }

    public function ajax_waitlist_remove()
    {
        $this->check_nonce('waitlist_remove');
        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;

        $waitlist_query = new Usctdp_Mgmt_Waitlist_Query([
            'activity_id' => $activity_id,
            'student_id' => $student_id,
            'number' => 1,
        ]);
        if (empty($waitlist_query->items)) {
            wp_send_json_success('Student is not on the waitlist for this activity.');
        } else {
            $id = $waitlist_query->items[0]->id;
            $result = $waitlist_query->delete_item($id);
            if ($result) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error('Failed to remove student from waitlist.');
            }
        }
    }
}
