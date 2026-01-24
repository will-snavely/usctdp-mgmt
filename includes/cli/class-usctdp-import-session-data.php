<?php

class Usctdp_Import_Session_Data
{
    private $sessions;
    private $clinics;
    private $pricing;
    private $sessions_by_category;

    public function __construct()
    {
        $this->sessions = [];
        $this->clinics = [];
        $this->sessions_by_category = [];
    }

    private function get_clinic_by_title($title) {
        $args = array(
            'post_type'      => 'usctdp-clinic',
            'title'          => $title,
            'post_status'    => 'publish', 
            'numberposts'    => 1
        );
        $posts = get_posts($args);
        if(!empty($posts)) {
            return $posts[0];
        } else {
            return false;
        }
    }

    private function import_sessions($data)
    {
        foreach ($data["sessions"] as $session) {
            $start_date = new DateTime($session['start_date']);
            $end_date = new DateTime($session['end_date']);
            $title = Usctdp_Mgmt_Session::create_title(
                $session['name'],
                $session['length_weeks'],
                $start_date,
                $end_date
            );

            $existing_post = get_posts([
                'post_type'   => 'usctdp-session',
                'title'       => $title,
                'numberposts' => 1,
                'post_status' => 'publish'
            ]);
            if(!empty($existing_post)) {
                $session_id = $existing_post[0]->ID;
                WP_CLI::log("Session '$title' already exists (id=$session_id)");
                $this->sessions[$session['name']] = $session_id;
                continue;
            }

            WP_CLI::log("Creating session '$title'");
            $post_id = wp_insert_post([
                'post_title'    => $title,
                'post_status'   => 'publish',
                'post_type'     => 'usctdp-session',
            ]);
            update_field('field_usctdp_session_name', $session['name'], $post_id);
            update_field('field_usctdp_session_start_date', $start_date->format('Y-m-d'), $post_id);
            update_field('field_usctdp_session_end_date', $end_date->format('Y-m-d'), $post_id);
            update_field('field_usctdp_session_length_weeks', $session['length_weeks'], $post_id);
            update_field('field_usctdp_session_category', $session['category'], $post_id);
            wp_set_post_terms($post_id, ["test-data", "active"], 'post_tag', false);
            if (!isset($this->sessions_by_category[$session['category']])) {
                $this->sessions_by_category[$session['category']] = [];
            }
            $this->sessions_by_category[$session['category']][] = $post_id;
            $this->sessions[$session['name']] = $post_id;
        }
    }

    /**
     * Removes all variations from a specific variable product.
     *
     * @param int  $product_id  The ID of the parent variable product.
     * @param bool $force       True to permanently delete, false to move to trash.
     * @return bool             True on success, false on failure.
     */
    private function delete_all_product_variations($product_id, $force = true) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return false;
        }
        $variation_ids = $product->get_children();
        if (!empty($variation_ids)) {
            foreach ($variation_ids as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $variation->delete($force);
                }
            }
            $product->set_children(array());
            $product->save();
        }
        return true;
    }

    private function import_clinic_prices($data)
    {
        $clinics_by_title = [];
        $sessions_by_product = [];
        foreach ($data["pricing"] as $pricing) {
            $clinic_title = $pricing['clinic'];
            if(!isset($clinics_by_title[$clinic_title])) {
                $clinics_by_title[$clinic_title] = $this->get_clinic_by_title($clinic_title);
            }
            $clinic = $clinics_by_title[$clinic_title]; 
            $clinic_id = $clinic->ID;
            $session_id = $this->sessions[$pricing['session']];
            $query = new Usctdp_Mgmt_Product_Link_Query([
                "activity_id" => $clinic_id,
                "number" => 1,
            ]);
            if(!empty($query->items)) {
                $result = $query->items[0];
                $product_id = $result->product_id;
                if(!isset($sessions_by_product[$product_id])) {
                    $sessions_by_product[$product_id] = [];  
                }
                $sessions_by_product[$product_id][$session_id] = [
                    "One" => $pricing['1_day_price'],
                    "Two" => $pricing['2_day_price']
                ];
            } else {
                WP_CLI::log("No product found for clinic $clinic_id");
            }
        }

        foreach($sessions_by_product as $product_id => $sessions) {
            $this->delete_all_product_variations($product_id);
            $product = wc_get_product($product_id);
            WP_CLI::log(print_r($product->get_attributes(), true));
            $session_names = [];
            ksort($sessions);
            foreach($sessions as $session_id => $_) {
                $session_names[$session_id] = get_field('field_usctdp_session_name', $session_id);
            }
            $session_attribute = new WC_Product_Attribute();
            $session_attribute->set_name('Session');
            $session_attribute->set_options(array_values($session_names));
            $session_attribute->set_position(0);
            $session_attribute->set_visible(true);
            $session_attribute->set_variation(true);

            //WP_CLI::log("Populating session attribute with: " . implode(",", $session_names));
            $attributes = $product->get_attributes();
            $attributes['session'] = $session_attribute;
            $product->set_attributes($attributes);
            $product->save();

            foreach($sessions as $session_id => $pricing) {
                foreach($pricing as $day => $amt) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    $variation->set_attributes([ 
                        sanitize_title('Session') => $session_names[$session_id],
                        sanitize_title('Days') => $day
                    ]);
                    $variation->set_regular_price($amt);
                    $variation->set_manage_stock(false);
                    $variation->save();
                }
            }
        }
    }

    private function import_clinic_classes($data)
    {
        foreach ($data["clinic_classes"] as $class) {
            $clinic_id = $this->clinics[$class['clinic']];
            $clinic_name = get_field('name', $clinic_id);
            $clinic_category = get_field('session_category', $clinic_id);
            $dow = $class['day'];
            $start_time = new DateTime($class['start_time']);
            $end_time = new DateTime($class['end_time']);
            $sessions = $this->sessions_by_category[$clinic_category];

            foreach ($sessions as $session_id) {
                $session_duration = get_field('length_weeks', $session_id);
                $title = Usctdp_Mgmt_Class::create_title($clinic_name, $dow, $start_time, $session_duration);
                $post_id = wp_insert_post([
                    'post_title'    => $title,
                    'post_status'   => 'publish',
                    'post_type'     => 'usctdp-class',
                ]);

                update_field('field_usctdp_class_session', $session_id, $post_id);
                update_field('field_usctdp_class_clinic', $clinic_id, $post_id);
                update_field('field_usctdp_class_dow', $dow, $post_id);
                update_field('field_usctdp_class_level', $class['level'], $post_id);
                update_field('field_usctdp_class_start_time', $start_time->format('H:i:s'), $post_id);
                update_field('field_usctdp_class_capacity', $class['capacity'], $post_id);
                update_field('field_usctdp_class_end_time', $end_time->format('H:i:s'), $post_id);
                wp_set_post_terms($post_id, ["test-data"], 'post_tag', false);
            }
        }
    }

    public function import($file_path)
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
            WP_CLI::error(sprintf('Error decoding JSON from file %s: %s', $file_path, json_last_error_msg()));
            return;
        }

        WP_CLI::log('Importing sessions...');
        $this->import_sessions($data);
        WP_CLI::log('Importing clinic pricing...');
        $this->import_clinic_prices($data);
        //WP_CLI::log('Importing classes...');
        //$this->import_clinic_classes($data);
    }
}
