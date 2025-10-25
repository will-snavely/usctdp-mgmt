<?php

class Usctdp_Mgmt_Model
{
    private function get_person_post_type()
    {
        $labels = [
            "name" => __("People", "textdomain"),
            "singular_name" => __("Person", "textdomain"),
            "menu_name" => __("People", "textdomain"),
            "name_admin_bar" => __("People", "textdomain"),
            "add_new" => __("Add New", "textdomain"),
            "add_new_item" => __("Add New Person", "textdomain"),
            "new_item" => __("New Person", "textdomain"),
            "edit_item" => __("Edit Person", "textdomain"),
            "view_item" => __("View Person", "textdomain"),
            "all_items" => __("All People", "textdomain"),
            "search_items" => __("Search People", "textdomain"),
            "not_found" => __("No people found.", "textdomain"),
        ];
        $args = [
            "labels" => $labels,
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "person"],
            "capability_type" => "post",
            "has_archive" => true,
            "hierarchical" => false,
            "menu_position" => 5,
            "supports" => ["title",  "author", "thumbnail"],
        ];
        return ["usctdp_person", $args];
    }

    public function get_custom_post_types()
    {
        return [$this->get_person_post_type()];
    }

    public function register_acf_person_fields()
    {
        $group_key = "group_usctdp_person";
        acf_add_local_field_group([
            "key" => $group_key,
            "title" => "Person Fields",
            "fields" => [],
            "location" => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'usctdp_person',
                    ),
                ),
            ),
        ]);

        acf_add_local_field([
            "key" => "field_bio",
            "label" => "Bio",
            "name" => "person-bio",
            "type" => "textarea",
            "required" => 1,
            "parent" => $group_key,
        ]);
    }

    public function register_custom_fields()
    {
        $this->register_acf_person_fields();
    }

    public function register_custom_posts()
    {
        foreach ($this->get_custom_post_types() as $post_type) {
            register_post_type($post_type[0], $post_type[1]);
        }
    }
}
