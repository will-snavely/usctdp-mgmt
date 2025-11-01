<?php

class Usctdp_Mgmt_Staff implements Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => "usctdp-staff";
    }

    public array $wp_post_settings {
        get => [
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "staff"],
            "capability_type" => "post",
            "has_archive" => true,
            "hierarchical" => false,
            "supports" => ["thumbnail", "title"],

            "labels" => [
                "name" => __("Staff", "textdomain"),
                "singular_name" => __("Staff", "textdomain"),
                "menu_name" => __("Staff", "textdomain"),
                "name_admin_bar" => __("Staff", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Staff", "textdomain"),
                "new_item" => __("New Staff", "textdomain"),
                "edit_item" => __("Edit Staff", "textdomain"),
                "view_item" => __("View Staff", "textdomain"),
                "all_items" => __("All Staff", "textdomain"),
                "search_items" => __("Search Staff", "textdomain"),
                "not_found" => __("No staff found.", "textdomain"),
            ]
        ];
    }
    
    public array $acf_settings {
        get => [
            "key" => "group_usctdp_staff",
            "title" => "Staff Fields",
            "fields" => [
                [
                    "key" => "field_usctdp_staff_bio",
                    "label" => "Bio",
                    "name" => "person_bio",
                    "type" => "textarea",
                    "required" => 1
                ]
            ],
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-staff',
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