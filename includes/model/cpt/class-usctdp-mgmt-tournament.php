<?php

class Usctdp_Mgmt_Tournament extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => 'usctdp-tournament';
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
                'name' => __('Tournament', 'textdomain'),
                'singular_name' => __('Tournament', 'textdomain'),
                'menu_name' => __('Tournaments', 'textdomain'),
                'name_admin_bar' => __('Tournaments', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Tournament', 'textdomain'),
                'new_item' => __('New Tournament', 'textdomain'),
                'edit_item' => __('Edit Tournament', 'textdomain'),
                'view_item' => __('View Tournament', 'textdomain'),
                'all_items' => __('All Tournaments', 'textdomain'),
                'search_items' => __('Search Tournaments', 'textdomain'),
                'not_found' => __('No tournament found.', 'textdomain'),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_tournament',
            'title' => 'Tournament Fields',
            'fields' => [
                [
                    'key' => 'field_usctdp_tournament_name',
                    'label' => 'Name',
                    'name' => 'name',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_tournament_age_group',
                    'label' => 'Age Group',
                    'name' => 'age_group',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_short_tournament_description',
                    'label' => 'Short Description',
                    'name' => 'short_description',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_tournament_description',
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
