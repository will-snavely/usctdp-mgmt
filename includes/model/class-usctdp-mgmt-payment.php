<?php

class Usctdp_Mgmt_Payment extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => "usctdp-payment";
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
                "name" => __("Payment", "textdomain"),
                "singular_name" => __("Payment", "textdomain"),
                "menu_name" => __("Payment", "textdomain"),
                "name_admin_bar" => __("Payment", "textdomain"),
                "add_new" => __("Add New", "textdomain"),
                "add_new_item" => __("Add New Payment", "textdomain"),
                "new_item" => __("New Payment", "textdomain"),
                "edit_item" => __("Edit Payment", "textdomain"),
                "view_item" => __("View Payment", "textdomain"),
                "all_items" => __("All Payments", "textdomain"),
                "search_items" => __("Search Payments", "textdomain"),
                "not_found" => __("No registration found.", "textdomain"),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            "key" => "group_usctdp_payment",
            "title" => "Payment Fields",
            "fields" => [
                [
                    "key" => "field_usctdp_payment_amount",
                    "label" => "Amount",
                    "name" => "amount",
                    "type" => "number",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_payment_date",
                    "label" => "Date",
                    "name" => "date",
                    "type" => "date_time_picker",
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_payment_method",
                    "label" => "Method",
                    "name" => "method",
                    "type" => "select",
                    "required" => 1,
                    "choices" => [
                        "cash" => "Cash",
                        "check" => "Check",
                        "credit_card" => "Credit Card",
                        "online_store" => "Online Store", 
                        "other" => "Other"
                    ]
                ],
                [
                    "key" => "field_usctdp_payment_notes",
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
