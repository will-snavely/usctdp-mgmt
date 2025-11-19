<?php

class Usctdp_Mgmt_Registration implements Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => "usctdp-registration";
    }

    public array $wp_post_settings {
        get => [
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "registration"],
            "capability_type" => "post",
            "has_archive" => true,
            "hierarchical" => false,
            "supports" => ['author'],

            "labels" => [
                "name" => __("Registration", "textdomain"),
                "singular_name" => __("Registration", "textdomain"),
                "menu_name" => __("Registrations", "textdomain"),
                "name_admin_bar" => __("Registration", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Registration", "textdomain"),
                "new_item" => __("New Registration", "textdomain"),
                "edit_item" => __("Edit Registration", "textdomain"),
                "view_item" => __("View Registration", "textdomain"),
                "all_items" => __("All Registrations", "textdomain"),
                "search_items" => __("Search Registrations", "textdomain"),
                "not_found" => __("No registration found.", "textdomain"),
            ]
        ];
    }
    
    public array $acf_settings {
        get => [
            "key" => "group_usctdp_registration",
            "title" => "Registration Fields",
            "fields" => [
                [
                    "key" => "field_usctdp_registration_student",
                    "label" => "Student",
                    "name" => "student",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-student",
                    ),
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_registration_class",
                    "label" => "Class",
                    "name" => "class",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-class",
                    ),
                    "required" => 1
                ],
            ],
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-registration',
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
