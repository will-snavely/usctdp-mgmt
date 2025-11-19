<?php

class Usctdp_Mgmt_Family implements Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => "usctdp-family";
    }

    public array $wp_post_settings {
        get => [
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "family"],
            "capability_type" => "post",
            "has_archive" => true,
            "hierarchical" => false,
            "supports" => ["thumbnail", "title"],

            "labels" => [
                "name" => __("Family", "textdomain"),
                "singular_name" => __("Family", "textdomain"),
                "menu_name" => __("Families", "textdomain"),
                "name_admin_bar" => __("Family", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Family", "textdomain"),
                "new_item" => __("New Family", "textdomain"),
                "edit_item" => __("Edit Family", "textdomain"),
                "view_item" => __("View Family", "textdomain"),
                "all_items" => __("All Families", "textdomain"),
                "search_items" => __("Search Families", "textdomain"),
                "not_found" => __("No family found.", "textdomain"),
            ]
        ];
    }
    
    public array $acf_settings {
        get => [
            "key" => "group_usctdp_family",
            "title" => "Family Fields",
            "fields" => [
            ],
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-family',
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
