<?php

class Usctdp_Mgmt_Family implements Usctdp_Mgmt_Model_Type {
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
                                [
                    "key" => "field_usctdp_class_type",
                    "label" => "Class Type",
                    "name" => "class_type",
                    "type" => "select",
                    "required" => 1,
                    "choices" => [
                        "tiny-tots" => "Tiny Tots",
                        "red-pre" => "Red Pre-Rally",
                        "red" => "Red",
                        "orange-pre" => "Orange Pre-Rally",
                        "orange" => "Orange",
                        "teen-1" => "Teen 1",
                        "orange-2" => "Orange 2",
                        "green" => "Green",
                        "yellow-1" => "Yellow Ball",
                        "yellow-2" => "Yellow Ball Open",
                    ]
                ],
                [
                    "key" => "field_usctdp_class_dow",
                    "label" => "Day of Week",
                    "name" => "day_of_week",
                    "type" => "select",
                    "required" => 1,
                    "choices" => [
                        "mon" => "Monday",
                        "tues" => "Tuesday",
                        "wed" => "Wednesday",
                        "thurs" => "Thursday",
                        "fri" => "Friday",
                        "sat" => "Saturday",
                        "sun" => "Sunday"
                    ]
                ],
                [
                    "key" => "field_usctdp_class_start_time",
                    "label" => "Start Time",
                    "name" => "start_time",
                    "type" => "time_picker",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_class_end_time",
                    "label" => "End Time",
                    "name" => "end_time",
                    "type" => "time_picker",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_class_parent_instructor",
                    "label" => "Instructor",
                    "name" => "instructor",
                    "type" => "post_object",
                    "post_type" => [
                        0 => "usctdp-staff",
                    ],
                    "required" => 0
                ],
                [
                    "key" => "field_usctdp_unit_class_instructor_addtl",
                    "label" => "Additional Instructor",
                    "name" => "instructor_addtl",
                    "type" => "post_object",
                    "post_type" => [
                        0 => "usctdp-staff",
                    ],
                    "required" => 0
                ]   
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
