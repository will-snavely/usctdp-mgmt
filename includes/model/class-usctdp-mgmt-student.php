<?php

class Usctdp_Mgmt_Student extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => "usctdp-student";
    }

    public array $wp_post_settings {
        get => [
            "public" => false,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "capability_type" => "post",
            "hierarchical" => false,
            "supports" => ["author", "title"],

            "labels" => [
                "name" => __("Student", "textdomain"),
                "singular_name" => __("Student", "textdomain"),
                "menu_name" => __("Students", "textdomain"),
                "name_admin_bar" => __("Students", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Student", "textdomain"),
                "new_item" => __("New Student", "textdomain"),
                "edit_item" => __("Edit Student", "textdomain"),
                "view_item" => __("View Student", "textdomain"),
                "all_items" => __("All Students", "textdomain"),
                "search_items" => __("Search Students", "textdomain"),
                "not_found" => __("No student found.", "textdomain"),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            "key" => "group_usctdp_student",
            "title" => "Student Fields",
            "fields" => [
                [
                    'key' => 'field_usctdp_student_first_name',
                    'label' => 'First Name',
                    'name' => 'first_name',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'key' => 'field_usctdp_student_last_name',
                    'label' => 'Last Name',
                    'name' => 'last_name',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'key' => 'field_usctdp_student_birth_date',
                    'label' => 'Birth Date',
                    'name' => 'birth_date',
                    'type' => 'date_picker',
                    'display_format' => 'm/d/Y',
                    'return_format' => 'Ymd',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_student_level',
                    'label' => 'Level',
                    'name' => 'level',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    "key" => "field_usctdp_student_family",
                    "label" => "Family",
                    "name" => "family",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-family",
                    ),
                    "required" => 0
                ]
            ],
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-student',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
        ];
    }

    public function get_computed_post_fields($data, $postarr)
    {
        $result = [];
        if ($data['post_type'] === 'usctdp-student' && isset($_POST['acf'])) {
            $first_name = $_POST['acf']['field_usctdp_student_first_name'];
            $last_name  = $_POST['acf']['field_usctdp_student_last_name'];
            $result['post_title'] = $last_name . ", " . $first_name;
        }
        return $result;
    }
}
