<?php

class Usctdp_Mgmt_Family extends Usctdp_Mgmt_Model_Type
{
    public string $post_type {
        get => "usctdp-family";
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
                    'key' => 'field_usctdp_family_user',
                    'label' => 'Assigned User',
                    'name' => 'assigned_user',
                    'type' => 'user',
                    'required' => 1,
                ],
                [
                    "key" => "field_usctdp_family_last_name",
                    "label" => "Family Last Name",
                    "name" => "last_name",
                    "type" => "text",
                    "required" => 1,
                ],
                [
                    "key" => "field_usctdp_family_address",
                    "label" => "Family Address",
                    "name" => "address",
                    "type" => "text",
                    "required" => 1,
                ],
                [
                    "key" => "field_usctdp_family_city",
                    "label" => "Family City",
                    "name" => "city",
                    "type" => "text",
                    "required" => 1,
                ],
                [
                    "key" => "field_usctdp_family_state",
                    "label" => "Family State",
                    "name" => "state",
                    "type" => "text",
                    "required" => 1,
                ],
                [
                    "key" => "field_usctdp_family_zip",
                    "label" => "Family Zip",
                    "name" => "zip",
                    "type" => "text",
                    "required" => 1,
                ],
                [
                    "key" => "field_usctdp_family_phone_number",
                    "label" => "Phone Number",
                    "name" => "phone_number",
                    "type" => "text",
                    "required" => 1,
                ],
                [
                    "key" => "field_usctdp_family_notes",
                    "label" => "Notes",
                    "name" => "notes",
                    "type" => "textarea",
                ],
            ],
            'location' => array(
                array(
                    array(
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

    public function get_computed_post_fields($data, $postarr)
    {
        $result = [];
        if ($data['post_type'] === 'usctdp-family' && isset($_POST['acf'])) {
            $family_last_name = $_POST['acf']['field_usctdp_family_last_name'];
            $phone_number = $_POST['acf']['field_usctdp_phone_number'];
            $result['post_title'] = self::create_title($family_last_name, $phone_number);
        }
        return $result;
    }

    public function on_post_delete($post_id, $post)
    {
        try {
            $query = new Usctdp_Mgmt_Family_Link_Query([
                "family_id" => $post_id
            ]);
            foreach ($query->items as $item) {
                $query->delete_item($item->id);
            }
        } catch (\Throwable $th) {
            Usctdp_Mgmt_Logger::getLogger()->log_error("Failed to delete family links for family $post_id");
            Usctdp_Mgmt_Logger::getLogger()->log_error($th->getMessage());
        }
    }

    public static function create_title($last_name, $phone_number)
    {
        $last_four_digits = substr($phone_number, -4);
        return sanitize_text_field($last_name . ' ' . $last_four_digits);
    }
}
