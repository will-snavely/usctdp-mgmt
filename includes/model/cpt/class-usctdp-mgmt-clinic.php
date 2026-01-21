<?php

class Usctdp_Mgmt_Clinic extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => 'usctdp-clinic';
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
                'name' => __('Clinic', 'textdomain'),
                'singular_name' => __('Clinic', 'textdomain'),
                'menu_name' => __('Clinics', 'textdomain'),
                'name_admin_bar' => __('Clinics', 'textdomain'),
                'add_new' => __('Add New', 'textdomain'),
                'add_new_item' => __('Add New Clinic', 'textdomain'),
                'new_item' => __('New Clinic', 'textdomain'),
                'edit_item' => __('Edit Clinic', 'textdomain'),
                'view_item' => __('View Clinic', 'textdomain'),
                'all_items' => __('All Clinics', 'textdomain'),
                'search_items' => __('Search Clinics', 'textdomain'),
                'not_found' => __('No clinic found.', 'textdomain'),
            ]
        ];
    }

    public array $acf_settings {
        get => [
            'key' => 'group_usctdp_clinic',
            'title' => 'Clinic Fields',
            'fields' => [
                [
                    'key' => 'field_usctdp_clinic_name',
                    'label' => 'Name',
                    'name' => 'name',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_clinic_age_range',
                    'label' => 'Age Range',
                    'name' => 'age_range',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_clinic_category',
                    'label' => 'Category',
                    'name' => 'category',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_clinic_age_group',
                    'label' => 'Age Group',
                    'name' => 'age_group',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_clinic_short_description',
                    'label' => 'Short Description',
                    'name' => 'short_description',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_usctdp_clinic_description',
                    'label' => 'Description',
                    'name' => 'description',
                    'type' => 'textarea',
                    'required' => 1
                ],
            ],
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp-clinic',
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
        if ($data['post_type'] === 'usctdp-clinic' && isset($_POST['acf'])) {
            $clinic_name = $_POST['acf']['field_usctdp_clinic_name'];
            $result['post_title'] = self::create_title($clinic_name);
        }
        return $result;
    }

    public static function create_title($name)
    {
        return sanitize_text_field($name);
    }
}
