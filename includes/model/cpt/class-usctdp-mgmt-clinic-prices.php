<?php

class Usctdp_Mgmt_Clinic_Prices extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => 'usctdp-clinic-prices';
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
                'name' => __('Clinic Prices', 'textdomain'),
                'singular_name' => __('Clinic Price', 'textdomain'),
                'menu_name' => __('Clinic Prices', 'textdomain'),
                'name_admin_bar' => __('Clinic Prices', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Clinic Price', 'textdomain'),
                'new_item' => __('New Clinic Price', 'textdomain'),
                'edit_item' => __('Edit Clinic Price', 'textdomain'),
                'view_item' => __('View Clinic Price', 'textdomain'),
                'all_items' => __('All Clinic Prices', 'textdomain'),
                'search_items' => __('Search Clinic Prices', 'textdomain'),
                'not_found' => __('No clinic prices found.', 'textdomain'),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_clinic_prices',
            'title' => 'Clinic Prices Fields',
            'fields' => [
                [
                    "key" => "field_usctdp_clinic_prices_clinic",
                    "label" => "Clinic",
                    "name" => "clinic",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-clinic",
                    ),
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_clinic_prices_session",
                    "label" => "Session",
                    "name" => "session",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-session",
                    ),
                    "required" => 1
                ],
                [
                    'key' => 'field_usctdp_clinic_prices_one_day_price',
                    'label' => 'One Day Price',
                    'name' => 'one_day_price',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_clinic_prices_two_day_price',
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
                        'value' => 'usctdp-clinic-prices',
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
        if ($data['post_type'] === 'usctdp-clinic-prices' && isset($_POST['acf'])) {
            $clinic = get_post($_POST['acf']['field_usctdp_clinic_prices_clinic']);
            $session = get_post($_POST['acf']['field_usctdp_clinic_prices_session']);
            $session_name = get_field('name', $session->ID);
            $session_duration = get_field('length_weeks', $session->ID);
            $clinic_name = get_field('name', $clinic->ID);

            $result['post_title'] = self::create_title($session_name, $session_duration, $clinic_name);
        }
        return $result;
    }

    public static function create_title($session_title, $session_duration, $clinic_name)
    {
        return sanitize_text_field("Clinic Prices: " . $session_title . ' - ' . $session_duration . ' Weeks - ' . $clinic_name);
    }
}
