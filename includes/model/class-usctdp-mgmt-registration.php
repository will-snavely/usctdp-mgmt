<?php

class Usctdp_Mgmt_Registration extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => "usctdp-registration";
    }

    public array $wp_post_settings {
        get => [
            "public" => false,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "capability_type" => "post",
            "hierarchical" => false,
            "supports" => ['author', 'title'],

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
                [
                    "key" => "field_usctdp_registration_created",
                    "label" => "Created",
                    "name" => "created",
                    "type" => "date_time_picker",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_outstanding_balance",
                    "label" => "Outstanding Balance",
                    "name" => "outstanding_balance",
                    "type" => "number",
                    "required" => 0
                ],
                [
                    "key" => "field_usctdp_payment_method",
                    "label" => "Payment Method",
                    "name" => "payment_method",
                    "type" => "select",
                    "choices" => [
                        "check" => "Check",
                        "web_payment" => "Web Payment",
                    ],
                    "required" => 0
                ],
                [
                    "key" => "field_usctdp_registration_payment_date",
                    "label" => "Payment Date",
                    "name" => "payment_date",
                    "type" => "date_time_picker",
                    "required" => 0
                ],
                [
                    "key" => "field_usctdp_registration_notes",
                    "label" => "Notes",
                    "name" => "notes",
                    "type" => "textarea",
                    "required" => 0
                ]
            ],
            'location' => array(
                array(
                    array(
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

    public function get_computed_post_fields($data, $postarr)
    {
        $result = [];
        if ($data['post_type'] === 'usctdp-family' && isset($_POST['acf'])) {
            $family_last_name = $_POST['acf']['field_usctdp_family_last_name'];
            $result['post_title'] = self::create_family_title($family_last_name);
        }
        return $result;
    }

    public static function create_family_title($last_name)
    {
        return sanitize_text_field($last_name);
    }
}
