<?php

class Usctdp_Mgmt_Transaction extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => 'usctdp-transaction';
    }

    public array $wp_post_settings {
        get => [
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'author'],

            'labels' => [
                'name' => __('Transaction', 'textdomain'),
                'singular_name' => __('Transaction', 'textdomain'),
                'menu_name' => __('Transaction', 'textdomain'),
                'name_admin_bar' => __('Transaction', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Transaction', 'textdomain'),
                'new_item' => __('New Transaction', 'textdomain'),
                'edit_item' => __('Edit Transaction', 'textdomain'),
                'view_item' => __('View Transaction', 'textdomain'),
                'all_items' => __('All Transactions', 'textdomain'),
                'search_items' => __('Search Transactions', 'textdomain'),
                'not_found' => __('No transactions found.', 'textdomain'),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_transaction',
            'title' => 'Transaction Fields',
            'fields' => [
                [
                    "key" => "field_usctdp_transaction_family",
                    "label" => "Family",
                    "name" => "family",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-family",
                    ),
                    "required" => 1
                ],
                [
                    "key" => "field_usctdp_registration",
                    "label" => "Registration",
                    "name" => "registration",
                    "type" => "post_object",
                    "post_type" => array(
                        0 => "usctdp-registration",
                    ),
                    "required" => 1
                ],
                [
                    'key' => 'field_usctdp_transaction_amount',
                    'label' => 'Amount',
                    'name' => 'amount',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_transaction_date',
                    'label' => 'Date',
                    'name' => 'date',
                    'type' => 'date_picker',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_transaction_type',
                    'label' => 'Method',
                    'name' => 'method',
                    'type' => 'select',
                    'required' => 1,
                    'choices' => [
                        'cash' => 'Cash',
                        'check' => 'Check',
                        'online' => 'Online',
                        'credit_card' => 'Credit Card',
                        'club_credit' =>'Club Credit',
                        'other' => 'Other'
                    ],
                ],
 
                [
                    'key' => 'field_usctdp_transaction_method',
                    'label' => 'Method',
                    'name' => 'method',
                    'type' => 'select',
                    'required' => 1,
                    'choices' => [
                        'cash' => 'Cash',
                        'check' => 'Check',
                        'online' => 'Online',
                        'credit_card' => 'Credit Card',
                        'club_credit' =>'Club Credit',
                        'other' => 'Other'
                    ],
                ],
                [
                    'key' => 'field_usctdp_transaction_check_number',
                    'label' => 'Check Number',
                    'name' => 'check_number',
                    'type' => 'text',
                    'required' => 0,
                ],
                [
                    'key' => 'field_usctdp_transaction_online_transaction_id',
                    'label' => 'Online Transaction ID',
                    'name' => 'online_transaction_id',
                    'type' => 'text',
                    'required' => 0,
                ],
                [
                    'key' => 'field_usctdp_transaction_notes',
                    'label' => 'Notes',
                    'name' => 'notes',
                    'type' => 'textarea',
                ]
            ],
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-pricing',
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
        if ($data['post_type'] === 'usctdp-pricing' && isset($_POST['acf'])) {
            $course = get_post($_POST['acf']['field_usctdp_pricing_course']);
            $session = get_post($_POST['acf']['field_usctdp_pricing_session']);
            $session_name = get_field('name', $session->ID);
            $session_duration = get_field('length_weeks', $session->ID);
            $course_name = get_field('name', $course->ID);

            $result['post_title'] = self::create_title($session_name, $session_duration, $course_name);
        }
        return $result;
    }

    public static function create_title($session_title, $session_duration, $course_title)
    {
        return sanitize_text_field("Pricing: " . $session_title . ' - ' . $session_duration . ' Weeks - ' . $course_title);
    }
}
