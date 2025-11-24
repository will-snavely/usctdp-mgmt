<?php

class Usctdp_Mgmt_Staff extends Usctdp_Mgmt_Model_Type {
    public string $post_type {
        get => "usctdp-staff";
    }

    public array $wp_post_settings {
        get => [
            "public" => false,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "capability_type" => "post",
            "hierarchical" => false,
            "supports" => ["title", "author"],

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
                    'key' => 'field_usctdp_staff_first_name',
                    'label' => 'First Name',
                    'name' => 'first name',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'key' => 'field_usctdp_staff_last_name',
                    'label' => 'Last Name',
                    'name' => 'last name',
                    'type' => 'text',
                    'required' => true
                ],
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

    public function get_custom_post_title($data, $postarr) {
        if ( $data['post_type'] === 'usctdp-staff' && isset($_POST['acf'])) {
            $first_name = $_POST['acf']['field_usctdp_staff_first_name'];
            $last_name = $_POST['acf']['field_usctdp_staff_last_name'];
            return $last_name . ", " . $first_name;
        }
        return null;
    }
}