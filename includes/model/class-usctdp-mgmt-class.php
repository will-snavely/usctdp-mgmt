<?php

class Usctdp_Mgmt_Class implements Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => "usctdp-class";
    }

    public array $wp_post_settings {
        get => [
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "class"],
            "capability_type" => "post",
            "has_archive" => true,
            "hierarchical" => false,
            "supports" => ["thumbnail", "title"],

            "labels" => [
                "name" => __("Class", "textdomain"),
                "singular_name" => __("Class", "textdomain"),
                "menu_name" => __("Class", "textdomain"),
                "name_admin_bar" => __("Class", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Class", "textdomain"),
                "new_item" => __("New Class", "textdomain"),
                "edit_item" => __("Edit Class", "textdomain"),
                "view_item" => __("View Class", "textdomain"),
                "all_items" => __("All Classes", "textdomain"),
                "search_items" => __("Search Classes", "textdomain"),
                "not_found" => __("No class found.", "textdomain"),
            ]
        ];
    }
    
    public array $acf_settings {
        get => [
            "key" => "group_usctdp_class",
            "title" => "Class Fields",
            "fields" => [
                [
                    "key" => "field_usctdp_class_parent",
                    "label" => "Parent Session",
                    "name" => "parent_session",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-session",
                    ),
                    "required" => 1
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
}