<?php

class Usctdp_Mgmt_Unit implements Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => "usctdp-unit";
    }

    public array $wp_post_settings {
        get => [
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "unit"],
            "capability_type" => "post",
            "has_archive" => true,
            "hierarchical" => false,
            "supports" => ["thumbnail", "title"],

            "labels" => [
                "name" => __("Unit", "textdomain"),
                "singular_name" => __("Unit", "textdomain"),
                "menu_name" => __("Unit", "textdomain"),
                "name_admin_bar" => __("Unit", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Unit", "textdomain"),
                "new_item" => __("New Unit", "textdomain"),
                "edit_item" => __("Edit Unit", "textdomain"),
                "view_item" => __("View Unit", "textdomain"),
                "all_items" => __("All Units", "textdomain"),
                "search_items" => __("Search Units", "textdomain"),
                "not_found" => __("No unit found.", "textdomain"),
            ]
        ];
    }
    
    public array $acf_settings {
        get => [
            "key" => "group_usctdp_unit",
            "title" => "Unit Fields",
            "fields" => [       
                [
                    "key" => "field_usctdp_unit_parent_class",
                    "label" => "Class",
                    "name" => "parent_class",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-class",
                    ),
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_unit_dow",
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
                        "sun" => "Sunday",
                        
                    ]
                ],
                [
                    "key" => "field_usctdp_unit_start_time",
                    "label" => "Start Time",
                    "name" => "start_time",
                    "type" => "time_picker",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_unit_end_time",
                    "label" => "End Time",
                    "name" => "end_time",
                    "type" => "time_picker",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_unit_parent_instructor",
                    "label" => "Instructor",
                    "name" => "instructor",
                    "type" => "post_object",
                    "post_type" => [
                        0 => "usctdp-staff",
                    ],
                    "required" => 0
                ],
                [
                    "key" => "field_usctdp_unit_parent_instructor_addtl",
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
                        'value' => 'usctdp-unit',
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