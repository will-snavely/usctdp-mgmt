<?php

class Usctdp_Mgmt_Class extends Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => 'usctdp-class';
    }

    public array $wp_post_settings {
        get => [
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'author'],

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
                    'key' => 'field_usctdp_class_parent',
                    'label' => 'Parent Session',
                    'name' => 'parent_session',
                    'type' => 'post_object',
                    'post_type' => array(
                        0 => 'usctdp-session',
                    ),
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_type',
                    'label' => 'Class Type',
                    'name' => 'class_type',
                    'type' => 'select',
                    'required' => 1,
                    'choices' => [
                        'tiny-tots' => 'Tiny Tots',
                        'red-pre' => 'Red Pre-Rally',
                        'red' => 'Red',
                        'orange-pre' => 'Orange Pre-Rally',
                        'orange' => 'Orange',
                        'teen-1' => 'Teen 1',
                        'orange-2' => 'Orange 2',
                        'green' => 'Green',
                        'yellow-1' => 'Yellow Ball',
                        'yellow-2' => 'Yellow Ball Open',
                    ]
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
                    'key' => 'field_usctdp_class_duration_weeks',
                    'label' => 'Duration in Weeks',
                    'name' => 'duration_weeks',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_date_list',
                    'label' => 'Date List (M/D/Y)',
                    'name' => 'date_list',
                    'type' => 'text',
                    'required' => 1
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
                    'key' => 'field_usctdp_class_one_day_price',
                    'label' => 'One Day Price',
                    'name' => 'one_day_price',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_class_two_day_price',
                    'label' => 'Two Day Price',
                    'name' => 'two_day_price',
                    'type' => 'number',
                    'required' => 1
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
            'location' => array (
                array (
                    array (
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

    public function get_custom_post_title($data, $postarr) {
        if ( $data['post_type'] === 'usctdp-class' && isset($_POST['acf'])) {
            $class_type = $_POST['acf']['field_usctdp_class_type'];
            $class_dow = $_POST['acf']['field_usctdp_class_dow'];
            $class_time_string = $_POST['acf']['field_usctdp_class_start_time'];
            $class_start_time = DateTime::createFromFormat('H:i:s', $class_time_string);
            return self::create_class_title($class_type, $class_dow, $class_start_time);
        }
        return null;
    }

    public static function type_value_to_label($type) {
        $choices = acf_get_field('field_usctdp_class_type')['choices'];
        if(array_key_exists($type, $choices)) {
            return $choices[$type];
        } 
        return '';
    }

    public static function dow_value_to_label($dow) {
        $choices = acf_get_field('field_usctdp_class_dow')['choices'];
        if(array_key_exists($dow, $choices)) {
            return $choices[$dow];
        } 
        return '';
    }

    public static function create_class_title($type, $dow, $start_time) {
        return sanitize_text_field(self::type_value_to_label($type) . ' ' . self::dow_value_to_label($dow) . ' at ' . $start_time->format('g:i A'));
    }
}
