<?php

class Usctdp_Mgmt_Admin_Ajax
{
    public static $ajax_handlers = [
        'activity_preregistration' => 'ajax_activity_preregistration',
        'commit_merchandise' => 'ajax_commit_merchandise',
        'create_family' => 'ajax_create_family',
        'create_ledger_entries' => 'ajax_create_ledger_entries',
        'create_student' => 'ajax_create_student',
        'datatable_balances' => 'ajax_datatable_balances',
        'datatable_balances_detail' => 'ajax_datatable_balances_detail',
        'gen_roster' => 'ajax_gen_roster',
        'gen_statement' => 'ajax_gen_statement',
        'get_family' => 'ajax_get_family',
        'get_family_balance' => 'ajax_get_family_balance',
        'ledger_datatable' => 'ajax_ledger_datatable',
        'ledger_events_datatable' => 'ajax_ledger_events_datatable',
        'purchase_history_datatable' => 'ajax_purchase_history_datatable',
        'recent_registrations' => 'ajax_recent_registrations',
        'registrations_datatable' => 'ajax_registrations_datatable',
        'roster_link' => 'ajax_get_roster_link',
        'select2_search' => 'ajax_select2_search',
        'session_rosters' => 'ajax_session_rosters',
        'session_rosters_datatable' => 'ajax_session_rosters_datatable',
        'student_datatable' => 'ajax_student_datatable',
        'submit_payment' => 'ajax_submit_payment',
        'toggle_session_active' => 'ajax_toggle_session_active',
        'update_family' => 'ajax_update_family',
        'update_registration' => 'ajax_update_registration',
        'update_purchase' => 'ajax_update_purchase',
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

    private function get_activity_enrollment_counts($activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $activity_id,
            'status' => 'active',
            'count' => true
        ]);
        $active_registrations = $reg_query->found_items;

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
        return $activity ? $activity->capacity : null;
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

        $result = $query->update_item($entity_id, $args);
        if ($result) {
            $query = new $query_object(['id' => $entity_id, 'number' => 1]);
            return $query->items[0];
        } else {
            throw new Web_Request_Exception("Updating entity $entity_id failed.");
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
            'student_registered' => $student_registered,
            'student_waitlisted' => $student_waitlisted,
            'student_level' => $student->level,
            'pricing' => $pricing->pricing
        ]);
    }

    public function ajax_gen_roster()
    {
        $this->check_nonce('gen_roster');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : '';
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : '';

        $target = null;
        if (!empty($activity_id)) {
            $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
            if (!$activity) {
                wp_send_json_error('Activity with ID "' . $activity_id . '" not found.', 404);
            }
            $target = [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type,
            ];
        } else if (!empty($session_id)) {
            $session = Usctdp_Mgmt_Model::get_session($session_id);
            if (!$session) {
                wp_send_json_error('Session with ID "' . $session_id . '" not found.', 404);
            }
            $target = [
                'id' => $session->id,
                'title' => $session->title,
                'type' => "session",
            ];
        }

        if (!$target) {
            wp_send_json_error('Activity ID or Session ID is required.', 400);
        }
        try {
            $doc_gen = new Usctdp_Mgmt_Docgen();
            $document = null;
            if ($target['type'] === 'clinic') {
                $document = $doc_gen->generate_clinic_roster($target['id']);
            } elseif ($target['type'] === 'tournament') {
                $document = $doc_gen->generate_tournament_roster($target['id']);
            } elseif ($target['type'] === 'session') {
                $document = $doc_gen->generate_session_roster($target['id']);
            }
            if (!$document) {
                wp_send_json_error('Document not generated.', 400);
            }
            $drive_file = $doc_gen->upload_to_google_drive($document, $target['id'], $target['title']);
            wp_send_json_success([
                'message' => 'Roster generated successfully',
                'doc_id' => $drive_file->id,
                'doc_url' => $drive_file->webViewLink,
                'generated_at' => current_time('mysql')
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_gen_roster', $e);
            wp_send_json_error('An unexpected server error occurred during roster generation.', 500);
        }
    }

    public function ajax_get_roster_link()
    {
        $this->check_nonce('roster_link');

        $entity_id = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : 0;
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter entity_id', 400);
        }

        try {
            $doc_gen = new Usctdp_Mgmt_Docgen();
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
                'doc_url' => 'https://drive.google.com/file/d/' . $roster_link->drive_id . '/edit',
                'generated_at' => $roster_link->updated_at ? $roster_link->updated_at->format('Y-m-d H:i:s') : null
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_get_roster_link', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
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
        $current_two_day_price = round(floatval($current_pricing->pricing['Two']), 2);
        $current_additional_day_discount = $current_two_day_price - $current_base_price;

        $new_activity = Usctdp_Mgmt_Model::get_activity($new_activity_id);
        if (!$new_activity) {
            return null;
        }
        $new_pricing = Usctdp_Mgmt_Model::get_activity_pricing($new_activity);
        if (!$new_pricing) {
            return null;
        }
        $new_base_price = round(floatval($new_pricing->pricing['One']), 2);
        $new_two_day_price = round(floatval($new_pricing->pricing['Two']), 2);
        $new_additional_day_discount = $new_two_day_price - $new_base_price;
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
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
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

        $conditions[] = "up.status = %s";
        $args[] = 'active';

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
                    $last_name = sanitize_text_field($raw);
                    return $last_name . ' ' . $last_four;
                },
                'search_term' => function ($raw) {
                    $phone = trim($this->get_sanitized_post_field_text('phone'));
                    $last_four = substr($phone, -4);
                    $last_name = sanitize_text_field($raw);
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
                return current_time('mysql');
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

    public function ajax_session_rosters()
    {
        $this->check_nonce('session_rosters');

        $results = [];
        try {
            $query = new Usctdp_Mgmt_Session_Query();
            $query_results = $query->get_active_session_rosters();
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
        $active = isset($_POST['active']) ? intval($_POST['active']) : null;
        $search_val = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $session_query = new Usctdp_Mgmt_Session_Query();
        $result = $session_query->search_session_rosters([
            "q" => $search_val,
            "active" => $active,
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

    public function ajax_toggle_session_active()
    {
        $this->check_nonce('toggle_session_active');

        try {
            $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : '';
            $active = isset($_POST['active']) ? intval($_POST['active']) : '';
            if (!$session_id) {
                wp_send_json_error('No session ID provided.', 400);
            }
            if ($active && ($active != 0 && $active != 1)) {
                wp_send_json_error('Invalid active status provided.', 400);
            }
            $query = new Usctdp_Mgmt_Session_Query([]);
            $query_results = $query->update_item($session_id, [
                'is_active' => $active
            ]);
            if (!$query_results) {
                wp_send_json_error('Failed to update session active status due to an unexpected server error.', 500);
            }
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_toggle_session_active', $e);
            wp_send_json_error('A system error occurred. Please try again.', 500);
        }
        wp_send_json_success([
            'message' => 'Session active status updated successfully'
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
            $args['activity_id'] = $activity_id;
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
        $query = $wpdb->prepare(
            "   SELECT 
                    family_id,
                    MAX(fam.title) as family_name,
                    SUM(ledger.debit) as total_charges,
                    SUM(ledger.credit) as total_payments,
                    (SUM(ledger.debit) - SUM(credit)) as balance_due
                FROM {$wpdb->prefix}usctdp_ledger AS ledger
                JOIN {$wpdb->prefix}usctdp_family AS fam ON ledger.family_id = fam.id
                WHERE account in ('registration_fees', 'merchandise_fees')
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
                    SELECT family_id
                    FROM {$wpdb->prefix}usctdp_ledger
                    WHERE account = 'registration_fees'
                    GROUP BY family_id
                    HAVING (SUM(debit) - SUM(credit)) > %d
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

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT 
                    reg.id as registration_id,
                    act.title as activity_name,
                    sesh.title as session_name,
                    stud.first as student_first,
                    stud.last as student_last,
                    ledger_sums.total_debit,
                    ledger_sums.total_credit,
                    (ledger_sums.total_debit - ledger_sums.total_credit) as balance_due,
                    COUNT(*) OVER() as grand_total
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stud ON reg.student_id = stud.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                INNER JOIN (
                    SELECT 
                        purchase_id,
                        SUM(debit) as total_debit,
                        SUM(credit) as total_credit
                    FROM {$wpdb->prefix}usctdp_ledger
                    WHERE account in ('registration_fees', 'merchandise_fees')
                    GROUP BY purchase_id
                    HAVING (SUM(debit) - SUM(credit)) > 0
                ) AS ledger_sums ON ledger_sums.purchase_id = reg.id
                WHERE stud.family_id = %d
                ORDER BY reg.id ASC
                LIMIT %d OFFSET %d",
            $family_id,
            $length,
            $start
        );

        $query_results = $wpdb->get_results($query);
        $output_data = [];
        $grand_total = 0;
        if ($query_results) {
            $grand_total = $query_results[0]->grand_total;
            foreach ($query_results as $result) {
                $output_data[] = [
                    "activity_name" => $result->activity_name,
                    "student_name" => $result->student_first . ' ' . $result->student_last,
                    "session_name" => $result->session_name,
                    "credit" => $amount_fmt->format($result->total_credit),
                    "debit" => $amount_fmt->format($result->total_debit),
                    "balance" => $amount_fmt->format($result->balance_due)
                ];
            }
        }

        $response = array(
            "draw" => $draw,
            "recordsTotal" => $grand_total,
            "recordsFiltered" => $grand_total,
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
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
        ]);
        if (!$purchase_id) {
            throw new Web_Request_Exception('Failed to create merchandise.');
        }
        return $purchase_id;
    }

    private function lock_registrations($registration_records)
    {
        global $wpdb;
        $activity_ids = array_map(function ($record) {
            return (int) $record["activity"]->id;
        }, $registration_records);

        if (!empty($activity_ids)) {
            $placeholders = implode(',', array_fill(0, count($activity_ids), '%d'));
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}usctdp_activity WHERE id IN ($placeholders) FOR UPDATE",
                    $activity_ids
                )
            );
        }
    }
    private function create_purchase_and_registration($args)
    {
        $created_by = get_current_user_id();
        $created_at = current_time('mysql');
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
                    'created_at' => current_time('mysql'),
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

        if ($house_credit > 0) {
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
                'created_at' => current_time('mysql'),
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
