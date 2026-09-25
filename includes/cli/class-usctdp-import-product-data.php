<?php

class Usctdp_Import_Product_Data
{
    private $image_map;
    private $flyer_map;

    public function __construct()
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $this->image_map = [];
        $this->flyer_map = [];
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
            'camps' => 7,
        ];
        $normalized_cat = strtolower(trim($cat));
        return $cats[$normalized_cat] ?? false;
    }
    /**
     * Overwrites an existing attachment's file with new content (same
     * attachment id, same registered path) and regenerates its thumbnails -
     * used by get_or_import_image()'s $force path when the file at a Drive
     * id has been replaced but the id itself hasn't changed, so there's no
     * new _external_source_id to key a fresh attachment off of. Keeping the
     * same attachment id means every wp_image_id/wp_flyer_id reference
     * (usctdp_product rows, WC product image ids) keeps working with no
     * further sync needed.
     *
     * Old thumbnail-size files from before the swap are left on disk as
     * orphans rather than cleaned up - harmless disk usage for this import
     * tool, not worth the extra complexity here.
     */
    private function replace_attachment_file($attachment_id, $new_local_file)
    {
        if (!file_exists($new_local_file)) {
            WP_CLI::warning("Can't refresh attachment $attachment_id: $new_local_file not found (was it downloaded?)");
            return false;
        }

        $existing_file = get_attached_file($attachment_id);
        if (!$existing_file || !copy($new_local_file, $existing_file)) {
            WP_CLI::warning("Failed to overwrite attachment $attachment_id's file with $new_local_file");
            return false;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $existing_file);
        wp_update_attachment_metadata($attachment_id, $metadata);
        return true;
    }

    /**
     * A failed/interrupted download (e.g. the TLS flakiness this container
     * has shown against Drive) can still leave a $local_file behind -
     * curl truncates its -o target before it has anything to write, so a
     * dropped connection produces a 0-byte or partial file rather than no
     * file at all. Treating that as real content would either sideload a
     * broken new attachment or - worse, under $force - clobber a perfectly
     * good existing one. This isn't a full "is it really an image/PDF"
     * validation, just enough to catch "the download didn't happen."
     */
    private function is_valid_download($local_file)
    {
        return file_exists($local_file) && filesize($local_file) > 0;
    }

    private function get_or_import_image($local_file, $external_id, $force = false)
    {
        global $wpdb;

        $existing_attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta
            WHERE meta_key = '_external_source_id'
            AND meta_value = %s
            LIMIT 1",
            $external_id
        ));
        $has_valid_download = $this->is_valid_download($local_file);

        if ($existing_attachment) {
            if ($force) {
                if ($has_valid_download) {
                    WP_CLI::log("Refreshing existing attachment $existing_attachment from $local_file");
                    $this->replace_attachment_file($existing_attachment, $local_file);
                } else {
                    WP_CLI::warning("Skipping refresh of attachment $existing_attachment: $local_file is missing or empty (download failed) - leaving it as-is");
                }
            }
            return $existing_attachment;
        }

        if (!$has_valid_download) {
            WP_CLI::warning("Skipping import for external id $external_id: $local_file is missing or empty (download failed)");
            return false;
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
            WP_CLI::log('Product already exists for clinic: ' . $clinic_name . ', updating');
            $product = wc_get_product($existing_id);
        } else {
            WP_CLI::log('Creating product for clinic: ' . $clinic_name);
            $product = new WC_Product_Variable();
        }

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
            WP_CLI::log('Product already exists for tournament: ' . $tourney_name . ', updating');
            $product = wc_get_product($existing_id);
        } else {
            WP_CLI::log('Creating product for tournament: ' . $tourney_name);
            $product = new WC_Product_Variable();
        }

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

        $product->set_attributes(array($session_attribute));
        return $product->save();
    }

    private function create_camp_woo_product($camp, $menu_order)
    {
        $camp_name = $camp['name'];
        $sku = 'camp-' . sanitize_title($camp_name);
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            WP_CLI::log('Product already exists for camp: ' . $camp_name . ', updating');
            $product = wc_get_product($existing_id);
        } else {
            WP_CLI::log('Creating product for camp: ' . $camp_name);
            $product = new WC_Product_Variable();
        }

        $product->set_name($camp_name);
        $product->set_description($camp['description']);
        $product->set_short_description($camp['short_description'] ?? '');
        $product->set_sku($sku);
        $product->set_image_id($this->image_map[$camp['image_id']]);
        $product->set_menu_order($menu_order);
        $product->set_status('publish');

        $session_attribute = new WC_Product_Attribute();
        $session_attribute->set_name('Session');
        $session_attribute->set_options([]);
        $session_attribute->set_position(0);
        $session_attribute->set_visible(true);
        $session_attribute->set_variation(true);

        $product->set_attributes(array($session_attribute));
        return $product->save();
    }

    private function create_equipment_woo_product($equipment)
    {
        $item_name = $equipment['name'];
        $sku = 'equipment-' . sanitize_title($item_name);
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            WP_CLI::log('Product already exists for equipment: ' . $item_name . ', updating');
            $product = wc_get_product($existing_id);
        } else {
            WP_CLI::log('Creating product for equipment: ' . $item_name);
            $product = new WC_Product_Simple();
        }

        $product->set_name("Equipment - $item_name");
        $product->set_status('publish');
        $product->set_regular_price($equipment["price"]);
        $product->set_description($equipment["description"]);
        $product->set_short_description($equipment["description"]);
        $product->set_sku($sku);
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
        $existing = !empty($query->items) ? $query->items[0] : null;

        $data = [
            "woocommerce_id" => $product_id,
            "wp_image_id" => $this->image_map[$tournament['image_id']] ?? null,
            "wp_flyer_id" => $this->flyer_map[$tournament['flyer_id']] ?? null,
            "title" => $title,
            "search_term" => $search_term,
            "code" => $tournament['code'],
            "type" => "tournament",
            "description" => $tournament['description'],
            "short_description" => $tournament['short_description'],
            "level" => strtolower($tournament['level']),
            "session_category" => $this->get_category_int($tournament['session_category']),
            "age_group" => strtolower($tournament['age_group']),
            "meta" => isset($tournament['meta']) ? json_encode($tournament['meta']) : '{}',
        ];

        if ($existing) {
            WP_CLI::log("Existing tournament $title found with id {$existing->id}, updating");
            $query->update_item($existing->id, $data);
            return $existing->id;
        }
        WP_CLI::log("Creating tournament $title");
        return $query->add_item($data);
    }

    private function create_camp($camp, $product_id)
    {
        $title = $camp['name'];
        $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        $existing = !empty($query->items) ? $query->items[0] : null;

        $data = [
            "woocommerce_id" => $product_id,
            "wp_image_id" => $this->image_map[$camp['image_id']] ?? null,
            "wp_flyer_id" => $this->flyer_map[$camp['flyer_id']] ?? null,
            "title" => $title,
            "search_term" => $search_term,
            "code" => $camp['code'],
            "type" => "camp",
            "description" => $camp['description'],
            "short_description" => $camp['short_description'] ?? '',
            "level" => strtolower($camp['level']),
            "session_category" => $this->get_category_int($camp['session_category']),
            "age_group" => strtolower($camp['age_group']),
            "meta" => isset($camp['meta']) ? json_encode($camp['meta']) : '{}',
        ];

        if ($existing) {
            WP_CLI::log("Existing camp $title found with id {$existing->id}, updating");
            $query->update_item($existing->id, $data);
            return $existing->id;
        }
        WP_CLI::log("Creating camp $title");
        return $query->add_item($data);
    }

    private function create_clinic($clinic, $product_id)
    {
        $title = $clinic['name'];
        $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        $existing = !empty($query->items) ? $query->items[0] : null;

        $data = [
            "woocommerce_id" => $product_id,
            "wp_image_id" => $this->image_map[$clinic['image_id']] ?? null,
            "wp_flyer_id" => $this->flyer_map[$clinic['flyer_id']] ?? null,
            "title" => $title,
            "search_term" => $search_term,
            "code" => $clinic['code'],
            "type" => $clinic['type'] ?? 'clinic',
            "level" => strtolower($clinic['level']),
            "age_range" => strtolower($clinic['age_range']),
            "description" => $clinic['description'],
            "short_description" => $clinic['short_description'],
            "session_category" => $this->get_category_int($clinic['session_category']),
            "age_group" => strtolower($clinic['age_group']),
            "meta" => isset($clinic['meta']) ? json_encode($clinic['meta']) : '{}',
        ];

        if ($existing) {
            WP_CLI::log("Existing clinic $title found with id {$existing->id}, updating");
            $query->update_item($existing->id, $data);
            return $existing->id;
        }
        WP_CLI::log("Creating clinic $title for product $product_id");
        return $query->add_item($data);
    }

    private function create_merchandise($merchandise, $product_id)
    {
        $title = $merchandise['name'];
        $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        $existing = !empty($query->items) ? $query->items[0] : null;

        $data = [
            "woocommerce_id" => $product_id,
            "title" => $title,
            "search_term" => $search_term,
            "code" => $merchandise['code'],
            "type" => 'merch',
            "session_category" => 0,
            "age_group" => '',
        ];

        if ($existing) {
            WP_CLI::log("Existing product $title found with id {$existing->id}, updating");
            $query->update_item($existing->id, $data);
            return $existing->id;
        }
        WP_CLI::log("Creating product $title");
        return $query->add_item($data);
    }

    public function import($file_path, $skip_download = false, $force_images = false)
    {
        if ($force_images && $skip_download) {
            WP_CLI::warning('force_images has no effect without downloading first - pass skip_download=false to actually refresh image content.');
        }

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
        foreach ($data["camps"] as $camp) {
            $image_ids[] = $camp["image_id"];
        }

        $idx = 1;
        $url_pref = 'https://docs.google.com/uc?export=download&id=';
        $this->image_map = [];
        foreach ($image_ids as $image_id) {
            $url = $url_pref . $image_id;
            $path = "/tmp/$idx.webp";

            if (!$skip_download) {
                // /tmp/N.webp is reused across runs (N is just a position
                // counter) - remove any stale file first so a failed curl
                // below is detected as "no download" rather than silently
                // reusing whatever a previous, unrelated image left there.
                @unlink($path);
                $curl_cmd = "curl -L '$url' -o $path";
                WP_CLI::log($curl_cmd);
                shell_exec($curl_cmd);
            }

            $attachment_id = $this->get_or_import_image($path, $image_id, $force_images);
            $this->image_map[$image_id] = $attachment_id;
            $idx += 1;
        }

        $flyer_ids = [];
        foreach ($data["clinics"] as $clinic) {
            if (!empty($clinic["flyer_id"])) {
                $flyer_ids[] = $clinic["flyer_id"];
            }
        }
        foreach ($data["tournaments"] as $tournament) {
            if (!empty($tournament["flyer_id"])) {
                $flyer_ids[] = $tournament["flyer_id"];
            }
        }
        foreach ($data["camps"] as $camp) {
            if (!empty($camp["flyer_id"])) {
                $flyer_ids[] = $camp["flyer_id"];
            }
        }
        $flyer_ids = array_values(array_unique($flyer_ids));

        $idx = 1;
        $this->flyer_map = [];
        foreach ($flyer_ids as $flyer_id) {
            $url = $url_pref . $flyer_id;
            $path = "/tmp/flyer-$idx.pdf";

            if (!$skip_download) {
                @unlink($path);
                $curl_cmd = "curl -L '$url' -o $path";
                WP_CLI::log($curl_cmd);
                shell_exec($curl_cmd);
            }

            $attachment_id = $this->get_or_import_image($path, $flyer_id, $force_images);
            $this->flyer_map[$flyer_id] = $attachment_id;
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
        foreach ($data["camps"] as $camp) {
            $product_id = $this->create_camp_woo_product($camp, $menu_order);
            $camp_id = $this->create_camp($camp, $product_id);
            $age_group = sanitize_title($camp["age_group"]);
            $level = sanitize_title($camp["level"]);
            wp_set_object_terms($product_id, $age_group, 'age_group');
            wp_set_object_terms($product_id, 'camp', 'event_type');
            wp_set_object_terms($product_id, $level, 'skill_level');
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
