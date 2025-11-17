<?php

class Usctdp_Mgmt_Session implements Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => "usctdp-session";
    }

    public array $wp_post_settings {
        get => [
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "session"],
            "capability_type" => "post",
            "has_archive" => true,
            "hierarchical" => false,
            "supports" => ["thumbnail", "title"],

            "labels" => [
                "name" => __("Session", "textdomain"),
                "singular_name" => __("Session", "textdomain"),
                "menu_name" => __("Sessions", "textdomain"),
                "name_admin_bar" => __("Sessions", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Session", "textdomain"),
                "new_item" => __("New Session", "textdomain"),
                "edit_item" => __("Edit Session", "textdomain"),
                "view_item" => __("View Session", "textdomain"),
                "all_items" => __("All Sessions", "textdomain"),
                "search_items" => __("Search Sessions", "textdomain"),
                "not_found" => __("No session found.", "textdomain"),
            ]
        ];
    }
    
    public array $acf_settings {
        get => [
            "key" => "group_usctdp_session",
            "title" => "Session Fields",
            "fields" => [
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
}