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
                "name" => __("Classes", "textdomain"),
                "singular_name" => __("Class", "textdomain"),
                "menu_name" => __("Classes", "textdomain"),
                "name_admin_bar" => __("Classes", "textdomain"),
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
                    "key" => "field_usctdp_class_level",
                    "label" => "Level",
                    "name" => "level",
                    "type" => "number",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_class_instructors",
                    "label" => "Instructors",
                    "name" => "instructors",
                    "type" => "post_object",
                    "post_type" => [
                        0 => "usctdp-staff",
                    ],
		            "required" => 0,
		            "allow_null" => 0,
		            "multiple" => 1,
		            "return_format" => "id"
                ],
                [
                    "key" => "field_usctdp_class_notes",
                    "label" => "Notes",
                    "name" => "notes",
                    "type" => "textarea",
                    "required" => 0
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
