<?php

class Usctdp_Mgmt_Pricing extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => 'usctdp-pricing';
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
                'name' => __('Pricing', 'textdomain'),
                'singular_name' => __('Pricing', 'textdomain'),
                'menu_name' => __('Pricing', 'textdomain'),
                'name_admin_bar' => __('Pricing', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Pricing', 'textdomain'),
                'new_item' => __('New Pricing', 'textdomain'),
                'edit_item' => __('Edit Pricing', 'textdomain'),
                'view_item' => __('View Pricing', 'textdomain'),
                'all_items' => __('All Pricings', 'textdomain'),
                'search_items' => __('Search Pricings', 'textdomain'),
                'not_found' => __('No pricing found.', 'textdomain'),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_pricing',
            'title' => 'Pricing Fields',
            'fields' => [
                [
                    "key" => "field_usctdp_pricing_course",
                    "label" => "Course",
                    "name" => "course",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-course",
                    ),
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_pricing_session",
                    "label" => "Session",
                    "name" => "session",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-session",
                    ),
                    "required" => 1
                ],
                [
                    'key' => 'field_usctdp_pricing_one_day_price',
                    'label' => 'One Day Price',
                    'name' => 'one_day_price',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_pricing_two_day_price',
                    'label' => 'Two Day Price',
                    'name' => 'two_day_price',
                    'type' => 'number',
                    'required' => 1
                ]
            ],
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-pricing',
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
        if ($data['post_type'] === 'usctdp-pricing' && isset($_POST['acf'])) {
            $course = get_post($_POST['acf']['field_usctdp_pricing_course']);
            $session = get_post($_POST['acf']['field_usctdp_pricing_session']);
            $session_name = get_field('session_name', $session->ID);
            $session_duration = get_field('length_weeks', $session->ID);
            $course_name = get_field('name', $course->ID);

            $result['post_title'] = self::create_pricing_title($session_name, $session_duration, $course_name);
        }
        return $result;
    }

    public static function create_pricing_title($session_title, $session_duration, $course_title)
    {
        return sanitize_text_field("Pricing: " . $session_title . ' - ' . $session_duration . ' Weeks - ' . $course_title);
    }
}
