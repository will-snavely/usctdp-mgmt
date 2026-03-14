<?php

class Usctdp_Mgmt_Admin_Ajax
{
    public static $ajax_handlers = [
        'activity_preregistration' => 'ajax_activity_preregistration',
        'clinic_datatable' => 'ajax_clinic_datatable',
        'commit_registrations' => 'ajax_commit_registrations',
        'create_family' => 'ajax_create_family',
        'create_student' => 'ajax_create_student',
        'create_woocommerce_order' => 'ajax_create_woocommerce_order',
        'datatable_balances' => 'ajax_datatable_balances',
        'datatable_balances_detail' => 'ajax_datatable_balances_detail',
        'gen_roster' => 'ajax_gen_roster',
        'get_family_balance' => 'ajax_get_family_balance',
        'get_family_fields' => 'ajax_get_family_fields',
        'payment_datatable' => 'ajax_payment_datatable',
        'registration_history_datatable' => 'ajax_registration_history_datatable',
        'registrations_datatable' => 'registrations_datatable',
        'save_family_fields' => 'ajax_save_family_fields',
        'save_registration_fields' => 'ajax_save_registration_fields',
        'select2_search' => 'ajax_select2_search',
        'session_rosters' => 'ajax_session_rosters',
        'session_rosters_datatable' => 'ajax_session_rosters_datatable',
        'student_datatable' => 'ajax_student_datatable',
        'toggle_session_active' => 'ajax_toggle_session_active',
    ];

    private function is_student_enrolled($student_id, $activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'student_id' => $student_id,
            'activity_id' => $activity_id
        ]);
        return !empty($reg_query->items);
    }

    private function get_activity_registration_count($activity_id)
    {
        $reg_query = new Usctdp_Mgmt_Registration_Query([
            'activity_id' => $activity_id,
            'count' => true
        ]);
        return $reg_query->found_items;
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

    private function create_entity_from_post($query_object, $fields)
    {
        $args = [];
        foreach ($fields as $field => $transform) {
            $raw = $_POST[$field] ?? null;
            $args[$field] = $transform($raw);
        }
        $query = new $query_object([]);
        return $query->add_item($args);
    }

    private function save_entity_fields_from_post($entity_id, $query_object, $fields)
    {
        $query = new $query_object(['id' => $entity_id, 'number' => 1]);
        if (empty($query->items)) {
            throw new Web_Request_Exception("Entity with id $entity_id not found.");
        }
        $entity = $query->items[0];

        $args = [];
        foreach ($fields as $field => $transform) {
            if (array_key_exists($field, $_POST)) {
                $data = $transform($_POST[$field]);
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

    private function check_nonce($handler) {
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
        $found_posts = (int) $this->get_activity_registration_count($activity_id);
        $student_registered = $this->is_student_enrolled($student_id, $activity_id);

        wp_send_json_success([
            'capacity' => $capacity,
            'session_id' => $activity->session_id,
            'product_id' => $activity->product_id,
            'woocommerce_id' => $activity->product_woocommerce_id,
            'registered' => $found_posts,
            'student_registered' => $student_registered,
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
            if ($target['type'] === Usctdp_Activity_Type::Clinic) {
                $document = $doc_gen->generate_clinic_roster($target['id']);
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
                'doc_url' => $drive_file->webViewLink
            ]);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_gen_roster', $e);
            wp_send_json_error('An unexpected server error occurred during roster generation.', 500);
        }
    }

    public function ajax_get_family_fields()
    {
        $this->check_nonce('get_family_fields');

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
            Usctdp_Mgmt::logger()->log_exception('ajax_get_family_fields', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_save_registration_fields()
    {
        $this->check_nonce('save_registration_fields');

        $entity_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter registration_id', 400);
        }

        $post_fields = [
            'student_level' => sanitize_text_field(...),
            'activity_id' => intval(...),
            'credit' => sanitize_text_field(...),
            'debit' => sanitize_text_field(...),
            'notes' => function ($value) {
                return sanitize_textarea_field(stripslashes($value));
            },
        ];

        try {
            $result = $this->save_entity_fields_from_post(
                $entity_id,
                'Usctdp_Mgmt_Registration_Query',
                $post_fields
            );
            wp_send_json_success($result);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_save_registration_fields', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_save_family_fields()
    {
        $this->check_nonce('save_family_fields');

        $entity_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        if (empty($entity_id)) {
            wp_send_json_error('Missing required parameter family_id', 400);
        }

        $post_fields = [
            'email' => sanitize_text_field(...),
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
            $result = $this->save_entity_fields_from_post(
                $entity_id,
                'Usctdp_Mgmt_Family_Query',
                $post_fields
            );
            wp_send_json_success($result);
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_save_family_fields', $e);
            wp_send_json_error('An unexpected server error occurred.', 500);
        }
    }

    public function ajax_get_family_balance()
    {
        $this->check_nonce('get_family_balance');

        try {
            $family_id = $this->get_sanitized_post_field_int('family_id');
            $conditions = [];
            $args = [];
            if ($family_id === null || $family_id === 0) {
                wp_send_json_error('Family ID is required.', 400);
            }
            $conditions[] = "stu.family_id = %d";
            $args[] = $family_id;

            $student_id = $this->get_sanitized_post_field_int('student_id');
            if ($student_id !== null && $student_id !== 0) {
                $conditions[] = "reg.student_id = %d";
                $args[] = $student_id;
            }

            global $wpdb;
            $query = $wpdb->prepare(
                "   SELECT
                    SUM(credit) as total_credits,
                    SUM(debit) as total_debits
                FROM {$wpdb->prefix}usctdp_registration AS reg
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id
                WHERE " . implode(' AND ', $conditions),
                $args
            );
            $results = $wpdb->get_row($query);
            wp_send_json_success([
                'total_credits' => $results->total_credits,
                'total_debits' => $results->total_debits,
                'balance' => $results->total_debits - $results->total_credits
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
                'email' => sanitize_text_field(...), 
                'last' => sanitize_text_field(...),
                'address' => sanitize_text_field(...),
                'city' => sanitize_text_field(...),
                'state' => sanitize_text_field(...),
                'zip' => sanitize_text_field(...), 
                'phone_numbers' => json_encode(...),
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
            $family_id = $this->create_entity_from_post('Usctdp_Mgmt_Family_Query', $fields);
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
                'user_email' => $family->email,
                'first_name' => 'Family Account',
                'last_name' => $last_name,
                'display_name' => $last_name . ' ' . $last_four,
                'role' => 'subscriber'
            );
            $user_id = wp_insert_user($userdata);
            if (is_wp_error($user_id)) {
                $family_query->delete_item($family_id);
                throw new Web_Request_Exception(
                    $user_id->get_error_message(),
                    500
                );
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

            $student_id = $this->create_entity_from_post('Usctdp_Mgmt_Student_Query', $fields);
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
        
            if(!Usctdp_Mgmt::select2()->is_valid_target($target)) {
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

    public function ajax_clinic_datatable()
    {
        $this->check_nonce('clinic_datatable');

        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($session_id) {
            $args['session_id'] = $session_id;
        }
        if ($product_id) {
            $args['product_id'] = $product_id;
        }

        $clinic_query = new Usctdp_Mgmt_Clinic_Query([]);
        $result = $clinic_query->get_clinic_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $result['count'],
            "recordsFiltered" => $result['count'],
            "data" => $result['data'],
        );
        wp_send_json($response);
    }

    public function ajax_payment_datatable()
    {
        $this->check_nonce('payment_datatable');

        $registration_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : null;
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

        $args = [
            'number' => $length,
            'offset' => $start,
        ];
        if ($registration_id) {
            $args['registration_id'] = $registration_id;
        } else {
            wp_send_json_error('No registration ID provided.', 400);
        }

        $query = new Usctdp_Mgmt_Payment_Query([]);
        $results = $query->get_payment_data($args);
        foreach($results["data"] as &$result) {
            if(!empty($result->order_id)) {
                $result->order_url = get_edit_post_link($result->order_id);
            }
        }
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $results['count'],
            "recordsFiltered" => $results['count'],
            "data" => $results['data']
        );
        wp_send_json($response);
    }

    public function ajax_registration_history_datatable()
    {
        $this->check_nonce('registration_history_datatable');

        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
        $owes = isset($_POST['owes']) ? intval($_POST['owes']) : null;

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

        $reg_query = new Usctdp_Mgmt_Registration_Query([]);
        $results = $reg_query->get_registration_data($args);
        $response = array(
            "draw" => $draw,
            "recordsTotal" => $results['count'],
            "recordsFiltered" => $results['count'],
            "data" => $results['data']
        );
        wp_send_json($response);
    }

    public function ajax_registrations_datatable()
    {
        $this->check_nonce('registrations_datatable');

        $activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : null;
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
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
                    fam.title as family_name,
                    fam.id as family_id,
                    SUM(reg.debit - reg.credit) AS total_family_balance,
                    COUNT(*) OVER() AS grand_total
                FROM {$wpdb->prefix}usctdp_registration AS reg 
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id 
                JOIN {$wpdb->prefix}usctdp_family AS fam ON fam.id = stu.family_id
                WHERE (reg.debit > reg.credit) AND (reg.debit - reg.credit) > %d
                GROUP BY fam.id, fam.title
                ORDER BY total_family_balance DESC
                LIMIT %d OFFSET %d",
            $min_balance,
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
                    "family_id" => $result->family_id,
                    "family_name" => $result->family_name,
                    "total_balance" => $amount_fmt->format($result->total_family_balance),
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

    public function ajax_datatable_balances_detail()
    {
        $this->check_nonce('datatable_balances_detail');

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $family_id = isset($_POST['family_id']) ? intval($_POST['family_id']) : '';
        $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $token_suffix = Usctdp_Mgmt_Model::$token_suffix;
        $amount_fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        if (!$family_id) {
            wp_send_json_error('No family ID provided.', 400);
        }

        global $wpdb;
        $query = $wpdb->prepare(
            "   SELECT 
                    act.title as activity_name,
                    stu.title as student_name,
                    sesh.title as session_name,
                    reg.credit as credit,
                    reg.debit as debit,
                    (reg.debit - reg.credit) as balance,
                    COUNT(*) OVER() as grand_total
                FROM {$wpdb->prefix}usctdp_registration AS reg 
                JOIN {$wpdb->prefix}usctdp_student AS stu ON reg.student_id = stu.id
                JOIN {$wpdb->prefix}usctdp_activity AS act ON reg.activity_id = act.id
                JOIN {$wpdb->prefix}usctdp_session AS sesh ON act.session_id = sesh.id
                WHERE stu.family_id = %d AND reg.debit > reg.credit
                ORDER BY balance DESC
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
                    "student_name" => $result->student_name,
                    "session_name" => $result->session_name,
                    "credit" => $amount_fmt->format($result->credit),
                    "debit" => $amount_fmt->format($result->debit),
                    "balance" => $amount_fmt->format($result->debit - $result->credit)
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

    public function ajax_create_woocommerce_order()
    {
        $this->check_nonce('create_woocommerce_order');

        $order_data = isset($_POST['order_data']) ? $_POST['order_data'] : null;
        if (empty($order_data)) {
            wp_send_json_error('No order data provided.', 400);
        }

        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : null;
        if (empty($payment_method)) {
            wp_send_json_error('No payment method provided.', 400);
        }
        $check_number = isset($_POST['check_number']) ? sanitize_text_field($_POST['check_number']) : 'None';

        $family_id = null;
        foreach ($order_data as $order_item) {
            if ($family_id == null) {
                $family_id = $order_item["family_id"];
            } else {
                if ($family_id != $order_item["family_id"]) {
                    wp_send_json_error('All items must belong to the same family.', 400);
                }
            }
        }

    }

    private function parse_registration_data($data)
    {
        if (!isset($data['activity_id'])) {
            throw new Web_Request_Exception('Activity ID missing from registration data.');
        }
        if (!isset($data['student_id'])) {
            throw new Web_Request_Exception('Student ID missing from registration data.');
        }
        if (!is_numeric($data['activity_id'])) {
            throw new Web_Request_Exception('Activity ID is not a number.');
        }
        if (!is_numeric($data['student_id'])) {
            throw new Web_Request_Exception('Student ID is not a number.');
        }

        $activity_id = $data['activity_id'];
        $activity = Usctdp_Mgmt_Model::get_activity($activity_id);
        if (!$activity) {
            throw new Web_Request_Exception('Activity with ID ' . $activity_id . ' not found.');
        }

        $student_id = $data['student_id'];
        $student = Usctdp_Mgmt_Model::get_student($student_id);
        if (!$student) {
            throw new Web_Request_Exception('Student with ID ' . $student_id . ' not found.');
        }

        $student_level = '';
        if (isset($data['student_level'])) {
            $student_level = sanitize_text_field($data['student_level']);
        }
        if (empty($student_level)) {
            $student_level = $student->level;
        }

        $notes = '';
        if (isset($data['notes'])) {
            $notes = sanitize_textarea_field(stripslashes($data['notes']));
        }

        $credit = 0;
        if (isset($data['credit'])) {
            $credit = sanitize_text_field($data['credit']);
        }

        $debit = 0;
        if (isset($data['debit'])) {
            $debit = sanitize_text_field($data['debit']);
        }

        $line_item_id = 0;
        if (isset($data['line_item_id'])) {
            $line_item_id = sanitize_text_field($data['line_item_id']);
        }

        return [
            "student" => $student,
            "activity" => $activity,
            "line_item_id" => $line_item_id,
            "sql_args" => [
                'activity_id' => $activity_id,
                'student_id' => $student_id,
                'student_level' => $student_level,
                'credit' => $credit,
                'debit' => $debit,
                'notes' => $notes
            ]
        ];
    }

    public function ajax_commit_registrations()
    {
        $this->check_nonce('commit_registrations');

        $transaction_started = false;
        $transaction_completed = false;
        $response_message = '';
        $registration_ids = [];
        global $wpdb;

        try {
            if (!current_user_can('manage_options')) {
                throw new Web_Request_Exception('You do not have permission to perform this action.');
            }

            $ignore_full = isset($_POST['ignore-class-full']) && $_POST['ignore-class-full'] === 'true';
            $registration_data = isset($_POST['registration_data']) ? $_POST['registration_data'] : [];
            if (empty($registration_data)) {
                throw new Web_Request_Exception('No registrations provided.');
            }

            $registration_records = [];
            foreach ($registration_data as $registration) {
                $registration_records[] = $this->parse_registration_data($registration);
            }

            $wpdb->query('START TRANSACTION');
            $transaction_started = true;
            $registration_query = new Usctdp_Mgmt_Registration_Query([]);
            foreach ($registration_records as $record) {
                $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}usctdp_activity WHERE id = %d FOR UPDATE",
                        $record["activity"]->id,
                    )
                );
            }

            $current_user = get_current_user_id();
            foreach ($registration_records as &$record) {
                $args = $record['sql_args'];
                $line_item_id = $record['line_item_id'];
                if ($this->is_student_enrolled($args['student_id'], $args['activity_id'])) {
                    throw new Web_Request_Exception('Student is already enrolled in activity: ' . $record['activity']->title);
                }

                $capacity = $this->get_activity_capacity($args['activity_id']);
                $registrations = $this->get_activity_registration_count($args['activity_id']);
                if (!$ignore_full && $registrations >= $capacity) {
                    throw new Web_Request_Exception('Class is full: ' . $record['activity']->title);
                }

                $current_time = current_time('mysql');
                $args['created_at'] = $current_time;
                $args['created_by'] = $current_user;
                $args['last_modified_at'] = $current_time;
                $args['last_modified_by'] = $current_user;
                $registration_id = $registration_query->add_item($args);
                if (!$registration_id) {
                    throw new Web_Request_Exception('Failed to create registration.');
                }
                $registration_ids[$line_item_id] = $registration_id;
            }
            $wpdb->query('COMMIT');
            $transaction_completed = true;
        } catch (Web_Request_Exception $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_gen_roster', $e);
            $response_message = $e->getMessage();
        } catch (Throwable $e) {
            Usctdp_Mgmt::logger()->log_exception('ajax_gen_roster', $e);
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
                    "ids" => $registration_ids
                ]);
            }
        }
    }
}
