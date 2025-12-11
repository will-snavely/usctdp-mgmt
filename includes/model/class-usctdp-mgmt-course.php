<?php

class Usctdp_Mgmt_Course extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => 'usctdp-course';
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
                'name' => __('Course', 'textdomain'),
                'singular_name' => __('Course', 'textdomain'),
                'menu_name' => __('Courses', 'textdomain'),
                'name_admin_bar' => __('Courses', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Course', 'textdomain'),
                'new_item' => __('New Course', 'textdomain'),
                'edit_item' => __('Edit Course', 'textdomain'),
                'view_item' => __('View Course', 'textdomain'),
                'all_items' => __('All Courses', 'textdomain'),
                'search_items' => __('Search Courses', 'textdomain'),
                'not_found' => __('No course found.', 'textdomain'),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_course',
            'title' => 'Course Fields',
            'fields' => [
                [
                    'key' => 'field_usctdp_course_name',
                    'label' => 'Name',
                    'name' => 'name',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_course_age_range',
                    'label' => 'Age Range',
                    'name' => 'age_range',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_course_description',
                    'label' => 'Description',
                    'name' => 'description',
                    'type' => 'textarea',
                    'required' => 1
                ],
            ],
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-course',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
        ];
    }

    public function get_computed_post_fields($data, $postarr)
    {
        $result = [];
        if ($data['post_type'] === 'usctdp-course' && isset($_POST['acf'])) {
            $course_name = $_POST['acf']['field_usctdp_course_name'];
            $result['post_title'] = self::create_course_title($course_name);
        }
        return $result;
    }

    public static function create_course_title($name)
    {
        return sanitize_text_field($name);
    }
}
