<?php

class Usctdp_Import_Session_Data
{
    private $session_data;
    private $sessions_by_category;

    public function __construct()
    {
        $this->session_data = [];
        $this->sessions_by_category = [];
    }

    private function get_clinic_by_title($title)
    {      
	    $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        if(!empty($query->items)) {
            return $query->items[0];
        }
    	return false;
    }

    private function get_category_integer(string $cat)
    {
        $cats = [
            'junior: beginner' => 1,
            'junior: advanced' => 2,
            'adult' => 3,
            'cardio tennis' => 4,
        ];
        $normalized_cat = strtolower(trim($cat));
        return $cats[$normalized_cat] ?? false;
    }

    private function get_day_integer(string $day)
    {
        $days = [
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            'sunday'    => 7
        ];
        $normalized_day = strtolower(trim($day));
        return $days[$normalized_day] ?? false;
    }

    private function import_sessions($data)
    {
        foreach ($data["sessions"] as $session) {
            $start_date = new DateTime($session['start_date']);
            $end_date = new DateTime($session['end_date']);
            $name = $session['name'];
            $title = Usctdp_Mgmt_Session_Table::create_title(
                $session['name'],
                $session['length_weeks'],
                $start_date,
                $end_date
            );
            $session_id = 0;
            $category_int = $this->get_category_integer($session["category"]);
	        $session_category = Usctdp_Session_Category::from($category_int);
            $query = new Usctdp_Mgmt_Session_Query([
                "title" => $title,
                "start_date" => $start_date->format("Y-m-d"),
                "number" => 1
            ]);
            if (!empty($query->items)) {
                $session_id = $query->items[0]->id;
                WP_CLI::log("Session '$name' already exists (id=$session_id)");
            } else {
                $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
                $session_id = $query->add_item([
                    "title" => $title,
                    "search_term" => $search_term,
                    "is_active" => 1,
                    "start_date" => $start_date->format("Y-m-d"),
                    "end_date" => $end_date->format("Y-m-d"),
                    "num_weeks" => $session['length_weeks'],
                    "category" => $session_category->value,
                ]);
            }
            if (!isset($this->sessions_by_category[$session_category->value])) {
                $this->sessions_by_category[$session_category->value] = [];
            }
            $this->sessions_by_category[$session_category->value][] = $session_id;
            $this->session_data[$session_id] = $session;
        }
    }

    /**
     * Removes all variations from a specific variable product.
     *
     * @param int  $product_id  The ID of the parent variable product.
     * @param bool $force       True to permanently delete, false to move to trash.
     * @return bool             True on success, false on failure.
     */
    private function delete_all_product_variations($product_id, $force = true)
    {
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
            if (!isset($clinics_by_title[$clinic_title])) {
                $clinic = $this->get_clinic_by_title($clinic_title);
                if(!$clinic) {
                    WP_CLI::log("No clinic found with title $clinic_title");
                }
                $clinics_by_title[$clinic_title] = $this->get_clinic_by_title($clinic_title);
            }
            $clinic = $clinics_by_title[$clinic_title];
            $product_id = $clinic->woocommerce_id;
            if (!isset($sessions_by_product[$product_id])) {
                $sessions_by_product[$product_id] = [];
            }
            $sessions_by_product[$product_id][$pricing['session']] = [
                "One" => $pricing['1_day_price'],
                "Two" => $pricing['2_day_price']
            ];
        }

        foreach ($sessions_by_product as $product_id => $sessions) {
            $this->delete_all_product_variations($product_id);
            $product = wc_get_product($product_id);
            ksort($sessions);
            $session_attribute = new WC_Product_Attribute();
            $session_attribute->set_name('Session');
            $session_attribute->set_options(array_keys($sessions));
            $session_attribute->set_position(0);
            $session_attribute->set_visible(true);
            $session_attribute->set_variation(true);

            $attributes = $product->get_attributes();
            $attributes['session'] = $session_attribute;
            $product->set_attributes($attributes);
            $product->save();

            foreach ($sessions as $session_name => $pricing) {
                foreach ($pricing as $day => $amt) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    $variation->set_attributes([
                        sanitize_title('Session') => $session_name,
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
        foreach ($data["classes"] as $class) {
            $clinic_name = $class['clinic'];
            $clinic = $this->get_clinic_by_title($class['clinic']);
            $clinic_id = $clinic->id;
            $clinic_category = $clinic->session_category;
            $dow = $class['day'];
            $start_time = new DateTime($class['start_time']);
            $end_time = new DateTime($class['end_time']);
            $sessions = $this->sessions_by_category[$clinic_category->value];
            foreach ($sessions as $session_id) {
                $day_of_week = $this->get_day_integer($class['day']);
                $title = Usctdp_Mgmt_Clinic_Table::create_title(
                    $clinic_name,
                    $dow,
                    $start_time
                );
                $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
                $activity_query = new Usctdp_Mgmt_Activity_Query([
                    "title" => $title,
                ]);
                if (!empty($activity_query->items)) {
                    $class_id = $activity_query->items[0]->id;
                } else {
                    WP_CLI::log("Creating activity: $title");
                    $activity_id = $activity_query->add_item([
                        "session_id" => $session_id,
                        "product_id" => $clinic_id,
			            "type" => Usctdp_Activity_Type::Clinic->value,
			            "title" => $title,
			            "search_term" => $search_term,
		            ]);
                    $clinic_query = new Usctdp_Mgmt_Clinic_Query([
	                    "activity_id" => $activity_id
       		        ]);
		            if(!empty($clinic_query->items)) {
                        WP_CLI::log("Unexpected: class already exists (id=$activity_id)");
		            } else {
	                    $clinic_query->add_item([
		                    "activity_id" => $activity_id,
                            "day_of_week" => $day_of_week,
                            "start_time" => $start_time->format("H:i:s"),
                            "end_time" => $end_time->format("H:i:s"),
                            "capacity" => $class['capacity'],
                            "level" => (string) $class['level'],
                            "notes" => '',
                        ]);
                    }
                }
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
        WP_CLI::log('Importing classes...');
        $this->import_clinic_classes($data);
    }
}
