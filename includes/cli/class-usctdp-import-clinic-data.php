<?php

class Usctdp_Import_Clinic_Data
{
    private $image_map;
    private $category_map;

    public function __construct()
    {
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        $this->image_map = [];
        $this->category_map = [];
    }

    private function get_or_import_image($local_file, $external_id) {
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
            'name'     => basename($local_file),
            'tmp_name' => $local_file
        );
        $id = media_handle_sideload($file_array, 0);
        if (is_wp_error($id)) {
            return false;
        }
        
        if($external_id) {
            update_post_meta($id, '_external_source_id', $external_id);
        }
        return $id;
    }

    private function create_product_category(
            $category_name,
            $description,
            $slug,
            $image_id,
            $term_order) {
        $taxonomy = 'product_cat';
        $existing_id = term_exists($category_name, $taxonomy);
        if (!$existing_id) {
            $result = wp_insert_term(
                $category_name, 
                $taxonomy,     
                array(
                    'description' => $description ,
                    'slug'        => $slug
                )
            );

            if (is_wp_error($result)) {
                WP_CLI::error('Error: ' . $result->get_error_message());
                return false;
            } else {
                $term_id = $result['term_id'];
                WP_CLI::log('Category created, term ID: ' . $term_id);
                update_term_meta($term_id, 'thumbnail_id', $image_id);
                update_term_meta($term_id, 'term_order', $term_order);
                return $term_id;
            }
        } else {
            WP_CLI::log("Term already exists.");
            return $existing_id;
        }
    }

    private function create_clinic_product($clinic, $post_id, $menu_order)
    {
        $clinic_name = $clinic['name']; 
        $sku = 'clinic-' . $post_id;
        $existing_id = wc_get_product_id_by_sku($sku);
        if($existing_id) {
            WP_CLI::log('Product already exists for clinic: ' . $clinic_name);
            return $existing_id;
        }

        $product = new WC_Product_Variable();
        WP_CLI::log('Creating product for clinic: ' . $clinic_name);
        $product->set_name($clinic_name);
        $age_range = $clinic['age_range'];
        $product->set_description($clinic['description']);
        $product->set_short_description($clinic['short_description'] . ' - Ages ' . $age_range);
        $product->set_sku($sku);
        $product->set_image_id($this->image_map[$clinic['image_id']]);
        $product->update_meta_data('_clinic_id', $post_id);
        $product->set_menu_order($menu_order);
        $product->set_status('publish');

        $session_attribute = new WC_Product_Attribute();
        $session_attribute->set_name('Session');
        $session_attribute->set_options([]);
        $session_attribute->set_position(0);
        $session_attribute->set_visible(true);
        $session_attribute->set_variation(true);

        $num_days_attr = new WC_Product_Attribute();
        $num_days_attr->set_name('Days'); 
        $num_days_attr->set_options(array('One', 'Two'));
        $num_days_attr->set_visible(true);
        $num_days_attr->set_variation(true);

        $product->set_attributes(array($session_attribute, $num_days_attr));

        $term_id = $this->category_map[$clinic['category']]['term_id'];
        $product->set_category_ids([$term_id]);
        $parent_id = $product->save();
    }

    private function create_clinic($clinic)
    {
        $title = Usctdp_Mgmt_Clinic::create_title($clinic['name']);
        $existing_post = get_posts([
            'post_type'   => 'usctdp-clinic',
            'title'       => $title,
            'numberposts' => 1,  
        ]);

        if(!empty($existing_post)) {
            $found_post = $existing_post[0];
            $post_id = $found_post->ID;
            WP_CLI::log("Existing clinic named $title found with id $post_id");
            return $post_id;
        }

        WP_CLI::log("Creating clinic $title");
        $post_id = wp_insert_post([
            'post_title'    => $title,
            'post_status'   => 'publish',
            'post_type'     => 'usctdp-clinic',
        ]);

        update_field('field_usctdp_clinic_name', $clinic['name'], $post_id);
        update_field('field_usctdp_clinic_age_range', $clinic['age_range'], $post_id);
        update_field('field_usctdp_clinic_age_group', $clinic['age_group'], $post_id);
        update_field('field_usctdp_clinic_category', $clinic['category'], $post_id);
        update_field('field_usctdp_clinic_description', $clinic['description'], $post_id);
        update_field('field_usctdp_clinic_short_description', $clinic['short_description'], $post_id);
        wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
        return $post_id;
    }

    public function import($file_path, $skip_download=false)
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
                json_last_error_msg()));
            return;
        }

        $image_ids = [];
        foreach($data["categories"] as $category) {
            $image_ids[] = $category["image_id"]; 
        } 
        foreach($data["clinics"] as $clinic) {
            $image_ids[] = $clinic["image_id"]; 
        }
        
        $idx = 1;
        $url_pref = 'https://docs.google.com/uc?export=download&id=';
        $this->image_map = [];
        foreach($image_ids as $image_id) {
            $url = $url_pref . $image_id;
            $path = "/tmp/$idx.jpg";

            if(!$skip_download) {
                $curl_cmd = "curl -L '$url' -o $path";
                WP_CLI::log($curl_cmd);
                shell_exec($curl_cmd);
            }

            $attachment_id = $this->get_or_import_image($path, $image_id); 
            $this->image_map[$image_id] = $attachment_id;
            $idx += 1;
        }

        $this->category_map = [];
        $term_order = 0;
        foreach($data["categories"] as $category) {
            $term_id = $this->create_product_category(
                $category['name'],
                $category['description'],
                sanitize_title($category['name']),
                $this->image_map[$category['image_id']],
                $term_order
            );
            if($term_id) {
                $this->category_map[$category['name']] = $term_id;
            } 
            $term_order += 1;
        }
        
        $menu_order = 0;
        foreach ($data["clinics"] as $clinic) {
            $clinic_id = $this->create_clinic($clinic, $menu_order);
            $this->create_clinic_product($clinic, $clinic_id, $menu_order);
            $menu_order += 10;
        }
    }
}
