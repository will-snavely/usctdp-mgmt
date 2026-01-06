<?php

class Usctdp_Mgmt_Class extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => 'usctdp-class';
    }

    public array $wp_post_settings {
        get => [
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'author'],
            'taxonomies' => ['post_tag', 'category'],

            'labels' => [
                'name' => __('Classes', 'textdomain'),
                'singular_name' => __('Class', 'textdomain'),
                'menu_name' => __('Classes', 'textdomain'),
                'name_admin_bar' => __('Classes', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Class', 'textdomain'),
                'new_item' => __('New Class', 'textdomain'),
                'edit_item' => __('Edit Class', 'textdomain'),
                'view_item' => __('View Class', 'textdomain'),
                'all_items' => __('All Classes', 'textdomain'),
                'search_items' => __('Search Classes', 'textdomain'),
                'not_found' => __('No class found.', 'textdomain'),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_class',
            'title' => 'Class Fields',
            'fields' => [
                [
                    'key' => 'field_usctdp_class_session',
                    'label' => 'Session',
                    'name' => 'session',
                    'type' => 'post_object',
                    'post_type' => array(
                        0 => 'usctdp-session',
                    ),
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_clinic',
                    'label' => 'Clinic',
                    'name' => 'clinic',
                    'type' => 'post_object',
                    'post_type' => array(
                        0 => 'usctdp-clinic',
                    ),
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_dow',
                    'label' => 'Day of Week',
                    'name' => 'day_of_week',
                    'type' => 'select',
                    'required' => 1,
                    'choices' => [
                        'mon' => 'Monday',
                        'tues' => 'Tuesday',
                        'wed' => 'Wednesday',
                        'thurs' => 'Thursday',
                        'fri' => 'Friday',
                        'sat' => 'Saturday',
                        'sun' => 'Sunday'
                    ]
                ],
                [
                    'key' => 'field_usctdp_class_start_time',
                    'label' => 'Start Time',
                    'name' => 'start_time',
                    'type' => 'time_picker',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_end_time',
                    'label' => 'End Time',
                    'name' => 'end_time',
                    'type' => 'time_picker',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_capacity',
                    'label' => 'Capacity',
                    'name' => 'capacity',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_level',
                    'label' => 'Level',
                    'name' => 'level',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_date_list',
                    'label' => 'Date List (M/D/Y)',
                    'name' => 'date_list',
                    'type' => 'text',
                    'required' => 0
                ],
                [
                    'key' => 'field_usctdp_class_start_date',
                    'label' => 'Start Date',
                    'name' => 'start_date',
                    'type' => 'date_picker',
                    'required' => 0,
                    'display_format' => 'm/d/Y',
                    'return_format' => 'Ymd'
                ],
                [
                    'key' => 'field_usctdp_class_end_date',
                    'label' => 'End Date',
                    'name' => 'end_date',
                    'type' => 'date_picker',
                    'required' => 0,
                    'display_format' => 'm/d/Y',
                    'return_format' => 'Ymd'
                ],
                [
                    'key' => 'field_usctdp_class_instructors',
                    'label' => 'Instructors',
                    'name' => 'instructors',
                    'type' => 'post_object',
                    'post_type' => [
                        0 => 'usctdp-staff',
                    ],
                    'required' => 0,
                    'allow_null' => 0,
                    'multiple' => 1,
                    'return_format' => 'id'
                ],
                [
                    'key' => 'field_usctdp_class_notes',
                    'label' => 'Notes',
                    'name' => 'notes',
                    'type' => 'textarea',
                    'required' => 0
                ],
            ],
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-class',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
        ];
    }

    public function get_update_value_hooks()
    {
        return [
            'field_usctdp_class_date_list' => 'update_date_list_value',
            'field'
        ];
    }

    public function get_prepare_field_hooks()
    {
        return [
            'field_usctdp_class_start_date' => 'prepare_start_date_field',
            'field_usctdp_class_end_date' => 'prepare_end_date_field',
        ];
    }

    public function update_session_value($value, $post_id, $field)
    {
        try {
            $query = new Usctdp_Mgmt_Family_Link_Query([
                "student_id" => $post_id
            ]);
            foreach ($query->items as $item) {
                $query->delete_item($item->id);
            }
            $query->add_item([
                'family_id'    => $value,
                'student_id'     => $post_id
            ]);
        } catch (\Throwable $th) {
            Usctdp_Mgmt_Logger::getLogger()->log_error("Failed to update family link for student $post_id");
            Usctdp_Mgmt_Logger::getLogger()->log_error($th->getMessage());
        }
        return $value;
    }

    public function update_date_list_value($value, $post_id, $field, $original_value)
    {
        $date_list = array_map(function ($date) {
            return strtotime($date);
        }, explode(',', $value));
        update_field('field_usctdp_class_start_date', date('Ymd', min($date_list)), $post_id);
        update_field('field_usctdp_class_end_date', date('Ymd', max($date_list)), $post_id);
        return $value;
    }

    public function prepare_start_date_field($field)
    {
        return false;
    }

    public function prepare_end_date_field($field)
    {
        return false;
    }

    public function get_computed_post_fields($data, $postarr)
    {
        $result = [];
        if ($data['post_type'] === 'usctdp-class' && isset($_POST['acf'])) {
            $clinic_id = $_POST['acf']['field_usctdp_class_clinic'];
            $session_id = $_POST['acf']['field_usctdp_class_session'];
            $clinic_name = get_field('field_usctdp_clinic_name', $clinic_id);
            $session_duration = get_field('field_usctdp_session_duration', $session_id);
            $dow = self::dow_value_to_label($_POST['acf']['field_usctdp_class_dow']);
            $start_time = DateTime::createFromFormat('H:i:s', $_POST['acf']['field_usctdp_class_start_time']);
            $result['post_title'] = self::create_title($clinic_name, $dow, $start_time, $session_duration);
        }
        return $result;
    }

    public static function dow_value_to_label($dow)
    {
        $choices = acf_get_field('field_usctdp_class_dow')['choices'];
        if (array_key_exists($dow, $choices)) {
            return $choices[$dow];
        }
        return '';
    }

    public static function create_title($clinic_name, $dow, $start_time, $duration)
    {
        $time = $start_time->format('g:i A');
        return sanitize_text_field("$clinic_name, $dow at $time ($duration Weeks)");
    }
}
