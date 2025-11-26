<?php

class Usctdp_Mgmt_Session extends Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => 'usctdp-session';
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
                'name' => __('Session', 'textdomain'),
                'singular_name' => __('Session', 'textdomain'),
                'menu_name' => __('Sessions', 'textdomain'),
                'name_admin_bar' => __('Sessions', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Session', 'textdomain'),
                'new_item' => __('New Session', 'textdomain'),
                'edit_item' => __('Edit Session', 'textdomain'),
                'view_item' => __('View Session', 'textdomain'),
                'all_items' => __('All Sessions', 'textdomain'),
                'search_items' => __('Search Sessions', 'textdomain'),
                'not_found' => __('No session found.', 'textdomain'),
            ]
        ];
    }
    
    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_session',
            'title' => 'Session Fields',
            'fields' => [
                [
                    'key' => 'field_usctdp_session_name',
                    'label' => 'Session Name',
                    'name' => 'session_name',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_session_start_date',
                    'label' => 'Session Start Date',
                    'name' => 'start_date',
                    'type' => 'date_picker',
                    'display_format' => 'm/d/Y',
                    'return_format' => 'Ymd', 
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_session_end_date',
                    'label' => 'Session End Date',
                    'name' => 'end_date',
                    'type' => 'date_picker',
                    'display_format' => 'm/d/Y',
                    'return_format' => 'Ymd', 
                    'required' => 1
                ]
            ],
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-session',
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
        if ( $data['post_type'] === 'usctdp-session' && isset($_POST['acf'])) {
            $session_name = $_POST['acf']['field_usctdp_session_name'];
            $session_start = $_POST['acf']['field_usctdp_session_start_date'];
            $session_end = $_POST['acf']['field_usctdp_session_end_date'];
            $start_date = DateTime::createFromFormat('Ymd', $session_start);
            $end_date = DateTime::createFromFormat('Ymd', $session_end);
            return self::create_session_title($session_name, $start_date, $end_date);
        }
        return null;
    }

    public static function create_session_title($name, $start_date, $end_date) {
        return sanitize_text_field($name . ' (' . $start_date->format('m/d/Y') . ' - ' . $end_date->format('m/d/Y') . ')');
    }
}