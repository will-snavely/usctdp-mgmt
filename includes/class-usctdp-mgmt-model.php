<?php

class Usctdp_Mgmt_Model
{
    private function get_coach_taxonomy()
    {
        $labels = [
            "name" => _x("Coaches", "taxonomy general name"),
            "singular_name" => _x("Coach", "taxonomy singular name"),
            "search_items" => __("Search Coaches"),
            "all_items" => __("All Coaches"),
            "edit_item" => __("Edit Coach"),
            "update_item" => __("Update Coach"),
            "add_new_item" => __("Add New Coach"),
            "new_item_name" => __("New Coach"),
            "menu_name" => __("Coaches"),
        ];
        return [
            "hierarchical" => false,
            "labels" => $labels,
            "show_ui" => true,
            "show_admin_column" => true,
            "query_var" => true,
            "rewrite" => ["slug" => "coach"],
        ];
    }

    public function get_custom_taxonomies()
    {
        return [["coach", ["post"], $this->get_coach_taxonomy()]];
    }

    public function register_taxonomies()
    {
        foreach ($this->get_custom_taxonomies() as $tax) {
            register_taxonomy($tax[0], $tax[1], $tax[2]);
        }
    }
}
