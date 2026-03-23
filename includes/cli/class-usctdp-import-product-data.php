<?php

class Usctdp_Import_Product_Data
{
    private $image_map;

    public function __construct()
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $this->image_map = [];
    }

    private function get_category_int(string $cat)
    {
        $cats = [
            'junior: beginner' => 1,
            'junior: advanced' => 2,
            'adult' => 3,
            'cardio tennis' => 4,
            'junior tournaments' => 5,
            'adult tournaments' => 6,
        ];
        $normalized_cat = strtolower(trim($cat));
        return $cats[$normalized_cat] ?? false;
    }

    private function get_age_group_int(string $cat)
    {
        $cats = [
            'junior' => 1,
            'adult' => 2,
        ];
        $normalized_cat = strtolower(trim($cat));
        return $cats[$normalized_cat] ?? false;
    }

    private function get_or_import_image($local_file, $external_id)
    {
        global $wpdb;

        $existing_attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
            WHERE meta_key = '_external_source_id' 
            AND meta_value = %s 
            LIMIT 1",
            $external_id
        ));

        if ($existing_attachment) {
            return $existing_attachment;
        }

        $file_array = array(
            'name' => basename($local_file),
            'tmp_name' => $local_file
        );
        $id = media_handle_sideload($file_array, 0);
        if (is_wp_error($id)) {
            return false;
        }

        if ($external_id) {
            update_post_meta($id, '_external_source_id', $external_id);
        }
        return $id;
    }

    private function create_clinic_woo_product($clinic, $menu_order)
    {
        $clinic_name = $clinic['name'];
        $sku = 'clinic-' . sanitize_title($clinic_name);
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            WP_CLI::log('Product already exists for clinic: ' . $clinic_name);
            return $existing_id;
        }

        $product = new WC_Product_Variable();
        WP_CLI::log('Creating product for clinic: ' . $clinic_name);
        $product->set_name($clinic_name);
        $product->set_description($clinic['description']);
        $product->set_short_description($clinic['short_description']);
        $product->set_sku($sku);
        $product->set_image_id($this->image_map[$clinic['image_id']]);
        $product->set_menu_order($menu_order);
        $product->set_status('publish');

        $session_attribute = new WC_Product_Attribute();
        $session_attribute->set_name('Session');
        $session_attribute->set_options([]);
        $session_attribute->set_position(0);
        $session_attribute->set_visible(true);
        $session_attribute->set_variation(true);
        $num_days_attr = new WC_Product_Attribute();
        $num_days_attr->set_name('Days Per Week');
        $num_days_attr->set_options(array('One', 'Two'));
        $num_days_attr->set_visible(true);
        $num_days_attr->set_variation(true);
        $product->set_attributes([
            $session_attribute,
            $num_days_attr
        ]);
        return $product->save();
    }

    private function create_tournament_woo_product($tournament, $menu_order)
    {
        $tourney_name = $tournament['name'];
        $sku = 'tournament-' . sanitize_title($tourney_name);
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            WP_CLI::log('Product already exists for tournament: ' . $tourney_name);
            return $existing_id;
        }

        $product = new WC_Product_Variable();
        WP_CLI::log('Creating product for tournament: ' . $tourney_name);
        $product->set_name($tourney_name);
        $product->set_description($tournament['description']);
        $product->set_short_description($tournament['short_description']);
        $product->set_sku($sku);
        $product->set_image_id($this->image_map[$tournament['image_id']]);
        $product->set_menu_order($menu_order);
        $product->set_status('publish');

        $session_attribute = new WC_Product_Attribute();
        $session_attribute->set_name('Session');
        $session_attribute->set_options([]);
        $session_attribute->set_position(0);
        $session_attribute->set_visible(true);
        $session_attribute->set_variation(true);

        $num_days_attr = new WC_Product_Attribute();
        $num_days_attr->set_name('Role');
        $num_days_attr->set_options(array('Competitor', 'Substitute'));
        $num_days_attr->set_visible(true);
        $num_days_attr->set_variation(true);

        $product->set_attributes(array($session_attribute, $num_days_attr));
        return $product->save();
    }

    private function create_equipment_woo_product($equipment)
    {
        $item_name = $equipment['name'];
        $sku = 'equipment-' . sanitize_title($item_name);
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            WP_CLI::log('Product already exists for equipment: ' . $item_name);
            return $existing_id;
        }

        $product = new WC_Product_Simple();
        $product->set_name("Equipment - $item_name");
        $product->set_status('publish');
        $product->set_regular_price($equipment["price"]);
        $product->set_description($equipment["description"]);
        $product->set_short_description($equipment["description"]);
        $product->set_catalog_visibility('hidden');
        return $product->save();
    }

    private function create_tournament($tournament, $product_id)
    {
        $title = $tournament['name'];
        $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        if (!empty($query->items)) {
            $tourney_id = $query->items[0]->id;
            WP_CLI::log("Existing tournament $title found with id $tourney_id");
            $query->update_item($tourney_id, [
                "woocommerce_id" => $product_id,
            ]);
            return $tourney_id;
        }
        WP_CLI::log("Creating tournament $title");
        return $query->add_item([
            "woocommerce_id" => $product_id,
            "title" => $title,
            "search_term" => $search_term,
            "code" => $tournament['code'],
            "type" => "tournament",
            "session_category" => $this->get_category_int($tournament['session_category']),
            "age_group" => strtolower($tournament['age_group']),
        ]);
    }

    private function create_clinic($clinic, $product_id)
    {
        $title = $clinic['name'];
        $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        if (!empty($query->items)) {
            $clinic_id = $query->items[0]->id;
            WP_CLI::log("Existing clinic $title found with id $clinic_id");
            $query->update_item($clinic_id, [
                "woocommerce_id" => $product_id,
            ]);
            return $clinic_id;
        }
        WP_CLI::log("Creating clinic $title for product $product_id");
        return $query->add_item([
            "woocommerce_id" => $product_id,
            "title" => $title,
            "search_term" => $search_term,
            "code" => $clinic['code'],
            "type" => "clinic",
            "session_category" => $this->get_category_int($clinic['session_category']),
            "age_group" => strtolower($clinic['age_group']),
        ]);
    }

    private function create_merchandise($merchandise, $product_id)
    {
        $title = 'Merchandise - ' . $merchandise['name'];
        $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        if (!empty($query->items)) {
            $id = $query->items[0]->id;
            WP_CLI::log("Existing product $title found with id $id");
            $query->update_item($id, [
                "woocommerce_id" => $product_id,
            ]);
            return $id;
        }
        WP_CLI::log("Creating product $title");
        return $query->add_item([
            "woocommerce_id" => $product_id,
            "title" => $title,
            "search_term" => $search_term,
            "code" => $merchandise['code'],
            "type" => 'merch',
            "session_category" => 0,
            "age_group" => '',
        ]);
    }

    public function import($file_path, $skip_download = false)
    {
        if (!file_exists($file_path)) {
            WP_CLI::error(sprintf('File not found: %s', $file_path));
            return;
        }

        $json_content = file_get_contents($file_path);
        if ($json_content === false) {
            WP_CLI::error(sprintf('Could not read file: %s', $file_path));
            return;
        }

        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error(sprintf(
                'Error decoding JSON from file %s: %s',
                $file_path,
                json_last_error_msg()
            ));
            return;
        }

        $image_ids = [];
        foreach ($data["clinics"] as $clinic) {
            $image_ids[] = $clinic["image_id"];
        }
        foreach ($data["tournaments"] as $tournament) {
            $image_ids[] = $tournament["image_id"];
        }

        $idx = 1;
        $url_pref = 'https://docs.google.com/uc?export=download&id=';
        $this->image_map = [];
        foreach ($image_ids as $image_id) {
            $url = $url_pref . $image_id;
            $path = "/tmp/$idx.webp";

            if (!$skip_download) {
                $curl_cmd = "curl -L '$url' -o $path";
                WP_CLI::log($curl_cmd);
                shell_exec($curl_cmd);
            }

            $attachment_id = $this->get_or_import_image($path, $image_id);
            $this->image_map[$image_id] = $attachment_id;
            $idx += 1;
        }

        $menu_order = 0;
        foreach ($data["clinics"] as $clinic) {
            $product_id = $this->create_clinic_woo_product($clinic, $menu_order);
            $clinic_id = $this->create_clinic($clinic, $product_id);
            $age_group = sanitize_title($clinic["age_group"]);
            $level = sanitize_title($clinic["level"]);
            wp_set_object_terms($product_id, $age_group, 'age_group');
            if ($clinic["session_category"] == "Cardio Tennis") {
                wp_set_object_terms($product_id, 'cardio-tennis', 'event_type');
                wp_set_object_terms($product_id, ['beginner', 'intermediate', 'advanced'], 'skill_level');
            } else {
                wp_set_object_terms($product_id, 'clinic', 'event_type');
                wp_set_object_terms($product_id, $level, 'skill_level');
            }
            $menu_order += 10;
        }

        $menu_order = 0;
        foreach ($data["tournaments"] as $tournament) {
            $product_id = $this->create_tournament_woo_product($tournament, $menu_order);
            $tournament_id = $this->create_tournament($tournament, $product_id);
            $age_group = sanitize_title($tournament["age_group"]);
            wp_set_object_terms($product_id, $age_group, 'age_group');
            wp_set_object_terms($product_id, 'tournament', 'event_type');
            wp_set_object_terms($product_id, ['beginner', 'intermediate', 'advanced'], 'skill_level');
            $menu_order += 10;
        }

        $menu_order = 0;
        foreach ($data["merchandise"] as $merchandise) {
            $product_id = $this->create_equipment_woo_product($merchandise, $menu_order);
            $merchandise_id = $this->create_merchandise($merchandise, $product_id);
            $menu_order += 10;
            $pricing_query = new Usctdp_Mgmt_Pricing_Query([
                "product_id" => $merchandise_id,
                "number" => 1,
            ]);
            if (!empty($pricing_query->items)) {
                $target = $pricing_query->items[0]->id;
                $pricing_query->update_item($target, [
                    "pricing" => json_encode($merchandise["price"]),
                ]);
            } else {
                $pricing_query->add_item([
                    "product_id" => $merchandise_id,
                    "pricing" => json_encode($merchandise["price"]),
                ]);
            }
        }
    }
}
