<?php

class Usctdp_Import_Session_Data
{
    private $session_data;
    private $sessions_by_category;
    private $sessions_by_name;
    public function __construct()
    {
        $this->session_data = [];
        $this->sessions_by_category = [];
        $this->sessions_by_name = [];
    }

    private function get_clinic_by_title($title)
    {
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        if (!empty($query->items)) {
            return $query->items[0];
        }
        return false;
    }

    private function get_product_by_title($title)
    {
        $query = new Usctdp_Mgmt_Product_Query([
            'title' => $title,
            'number' => 1,
        ]);
        if (!empty($query->items)) {
            return $query->items[0];
        }
        return false;
    }

    /**
     * Resolves the reservation_group_id to write into an activity_data
     * payload. A brand-new activity gets a fresh, dedicated 1:1 group at
     * the imported capacity. An existing activity's group capacity is only
     * updated in place if that group is still 1:1 (just this activity) -
     * if it's been merged into a shared group via
     * `wp usctdp merge_reservation_group`, re-running import must not
     * silently overwrite a capacity someone deliberately set for the whole
     * shared group.
     */
    private function resolve_reservation_group_id($existing_activity, $capacity)
    {
        $capacity = intval($capacity);
        $group_query = new Usctdp_Mgmt_Reservation_Group_Query();

        if (!$existing_activity) {
            return $group_query->add_item([
                'capacity' => $capacity,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ]);
        }

        $group_id = (int) $existing_activity->reservation_group_id;
        if (count($group_query->get_member_activity_ids($group_id)) > 1) {
            WP_CLI::warning(
                "Activity '{$existing_activity->title}' (id={$existing_activity->id}) is in a shared " .
                    "reservation group (#$group_id) - skipping its capacity update from import. " .
                    "Use `wp usctdp set_reservation_group_capacity` to change it directly."
            );
            return $group_id;
        }

        $group_query->update_item($group_id, [
            'capacity' => $capacity,
            'updated_at' => current_time('mysql', true),
        ]);
        return $group_id;
    }

    private function get_category_integer(string $cat)
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

    private function get_day_integer(string $day)
    {
        $days = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7
        ];
        $normalized_day = strtolower(trim($day));
        return $days[$normalized_day] ?? false;
    }

    private function import_sessions($data)
    {
        $roster_group_query = new Usctdp_Mgmt_Roster_Group_Query();
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
            $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
            $session_data = [
                "title" => $title,
                "search_term" => $search_term,
                "start_date" => $start_date->format("Y-m-d"),
                "end_date" => $end_date->format("Y-m-d"),
                "num_weeks" => $session['length_weeks'],
                "season" => $session['season'],
                "category" => $session_category->value,
                "meta" => isset($session['meta']) ? json_encode($session['meta']) : '{}'
            ];
            $query = new Usctdp_Mgmt_Session_Query([
                "title" => $title,
                "start_date" => $start_date->format("Y-m-d"),
                "number" => 1
            ]);
            if (!empty($query->items)) {
                $session_id = $query->items[0]->id;
                WP_CLI::log("Session '$name' already exists (id=$session_id), updating");
                // status is deliberately left out here - it's an admin-owned
                // lifecycle decision (scheduled/on_sale/archived), not import
                // data, so re-running the importer must not silently
                // un-archive or un-publish a session someone already staged.
                $query->update_item($session_id, $session_data);
            } else {
                WP_CLI::log("Creating session '$name'");
                // New sessions always start scheduled - visible to admin and
                // on the public program schedule, but not yet on sale.
                // Promoting to on_sale is a deliberate action on the
                // Sessions admin page, not something import should do.
                $session_id = $query->add_item(array_merge($session_data, ["status" => "scheduled"]));
            }
            // Every session starts with its own explicit roster group, named
            // after the session, rather than relying on the admin UI's lazy
            // creation - see
            // Usctdp_Mgmt_Roster_Group_Query::get_or_create_for_session().
            // A no-op for sessions that already have one.
            $roster_group_query->get_or_create_for_session($session_id, $title);
            if (!isset($this->sessions_by_category[$session_category->value])) {
                $this->sessions_by_category[$session_category->value] = [];
            }
            $this->sessions_by_category[$session_category->value][] = $session_id;
            $this->session_data[$session_id] = $session;
            $this->sessions_by_name[$session['name']] = $session_id;
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

    /**
     * Syncs which sessions are currently active/on-sale for a product,
     * keyed by the custom usctdp_product id (not the WooCommerce id).
     *
     * @param int   $product_id                    usctdp_product.id
     * @param array $active_session_ids_for_product Set of session_id => true currently active for this product.
     */
    private function sync_product_sessions($product_id, $active_session_ids_for_product)
    {
        $existing_query = new Usctdp_Mgmt_Product_Session_Query([
            "product_id" => $product_id,
        ]);
        foreach ($existing_query->items as $item) {
            if (!isset($active_session_ids_for_product[$item->session_id])) {
                $existing_query->delete_item($item->id);
            }
        }

        foreach (array_keys($active_session_ids_for_product) as $session_id) {
            $query = new Usctdp_Mgmt_Product_Session_Query([
                "product_id" => $product_id,
                "session_id" => $session_id,
            ]);
            if (empty($query->items)) {
                $query->add_item([
                    "product_id" => $product_id,
                    "session_id" => $session_id,
                ]);
            }
        }
    }

    private function import_clinic_prices($data)
    {
        $clinics_by_title = [];
        $product_data = [];
        $active_sessions_by_product = [];

        foreach ($data["class_pricing"] as $pricing) {
            $clinic_title = $pricing['clinic'];
            if (!isset($clinics_by_title[$clinic_title])) {
                $clinic = $this->get_clinic_by_title($clinic_title);
                if (!$clinic) {
                    WP_CLI::log("No clinic found with title $clinic_title");
                }
                $clinics_by_title[$clinic_title] = $this->get_clinic_by_title($clinic_title);
            }
            $clinic = $clinics_by_title[$clinic_title];
            $session_id = $this->sessions_by_name[$pricing['session']];
            $woo_product_id = $clinic->woocommerce_id;

            if (!isset($product_data[$woo_product_id])) {
                $product_data[$woo_product_id] = [];
            }
            if (!isset($active_sessions_by_product[$clinic->id])) {
                $active_sessions_by_product[$clinic->id] = [];
            }

            $prices = [
                "One" => $pricing['1_day_price'],
                "Two" => $pricing['2_day_price'],
            ];

            // Appearing in class_pricing at all is the signal this session
            // should be wired up for this product - no separate "active"
            // flag needed on top of that.
            $product_data[$woo_product_id][$pricing['session']] = $prices;
            $active_sessions_by_product[$clinic->id][$session_id] = true;

            $pricing_query = new Usctdp_Mgmt_Pricing_Query([
                "session_id" => $session_id,
                "product_id" => $clinic->id,
                "number" => 1,
            ]);
            if (!empty($pricing_query->items)) {
                $target = $pricing_query->items[0]->id;
                $pricing_query->update_item($target, [
                    "pricing" => json_encode($prices),
                ]);
            } else {
                $pricing_query->add_item([
                    "session_id" => $session_id,
                    "product_id" => $clinic->id,
                    "pricing" => json_encode($prices),
                ]);
            }
        }

        foreach ($active_sessions_by_product as $product_id => $active_ids) {
            $this->sync_product_sessions($product_id, $active_ids);
        }

        foreach ($product_data as $woo_product_id => $sessions) {
            $this->delete_all_product_variations($woo_product_id);
            $product = wc_get_product($woo_product_id);
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

            $product_sessions = [];
            foreach ($sessions as $session_name => $pricing) {
                $product_sessions[$session_name] = $this->sessions_by_name[$session_name];
            }
            $product->update_meta_data('_session_post_ids', $product_sessions);
            $product->save();

            foreach ($sessions as $session_name => $pricing) {
                foreach ($pricing as $day => $amt) {
                    if (empty($amt)) {
                        WP_CLI::log("No price for $session_name $day");
                        continue;
                    }
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($woo_product_id);
                    $variation->set_attributes([
                        sanitize_title('Session') => $session_name,
                        sanitize_title('Days Per Week') => $day
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
        $primary_sort_counter = 0;
        $sorting = [];
        foreach ($data["classes"] as $class) {
            $clinic_name = $class['clinic'];
            $clinic = $this->get_clinic_by_title($class['clinic']);
            if (!$clinic) {
                WP_CLI::log("No clinic found with title $clinic_name");
                continue;
            }
            $clinic_id = $clinic->id;
            $clinic_category = $clinic->session_category;
            if (!isset($sorting[$clinic_id])) {
                $primary_sort_counter += 1;
                $sorting[$clinic_id] = [$primary_sort_counter, 0];
            }
            $sorting[$clinic_id][1] += 1;
            $primary_sort_order = $sorting[$clinic_id][0];
            $secondary_sort_order = $sorting[$clinic_id][1];

            $dow = $class['day'];
            $start_time = new DateTime($class['start_time']);
            $end_time = new DateTime($class['end_time']);
            $sessions = $this->sessions_by_category[$clinic_category->value];

            $session_filter = null;
            if (!empty($class["session"])) {
                foreach ($class["session"] as $name) {
                    $session_filter[] = $this->sessions_by_name[trim($name)];
                }
            }

            foreach ($sessions as $session_id) {
                if (!empty($session_filter) && !in_array($session_id, $session_filter)) {
                    continue;
                }
                $day_of_week = $this->get_day_integer($class['day']);
                $title = Usctdp_Mgmt_Clinic_Table::create_title(
                    $clinic_name,
                    $dow,
                    $start_time,
                    $end_time
                );
                $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);
                $activity_query = new Usctdp_Mgmt_Activity_Query([
                    "session_id" => $session_id,
                    "product_id" => $clinic_id,
                    "title" => $title,
                ]);
                $existing_activity = !empty($activity_query->items) ? $activity_query->items[0] : null;

                $activity_data = [
                    "session_id" => $session_id,
                    "product_id" => $clinic_id,
                    "type" => "clinic",
                    "title" => $title,
                    "search_term" => $search_term,
                    "reservation_group_id" => $this->resolve_reservation_group_id($existing_activity, $class['capacity']),
                    "primary_sort_order" => $primary_sort_order,
                    "secondary_sort_order" => $secondary_sort_order,
                    "level" => (string) $class['level'],
                    "meta" => isset($class['meta']) ? json_encode($class['meta']) : '{}'
                ];

                if ($existing_activity) {
                    $activity_id = $existing_activity->id;
                    WP_CLI::log("Activity exists: $title, updating (id=$activity_id)");
                    $activity_query->update_item($activity_id, $activity_data);
                } else {
                    WP_CLI::log("Creating activity: $title");
                    $activity_id = $activity_query->add_item($activity_data);
                }

                $clinic_data = [
                    "day_of_week" => $day_of_week,
                    "start_time" => $start_time->format("H:i:s"),
                    "end_time" => $end_time->format("H:i:s"),
                ];
                $clinic_query = new Usctdp_Mgmt_Clinic_Query([
                    "id" => $activity_id
                ]);
                if (!empty($clinic_query->items)) {
                    $clinic_query->update_item($activity_id, $clinic_data);
                } else {
                    $clinic_query->add_item(array_merge(["id" => $activity_id], $clinic_data));
                }
            }
        }
    }

    private function import_tournament_activities($data)
    {
        if (empty($data["tournaments"])) {
            return;
        }

        $primary_sort_order = 0;
        foreach ($data["tournaments"] as $tournament) {
            $name = trim($tournament['name']);
            $product_name = trim($tournament['product']);
            $primary_sort_order += 1;

            $product = $this->get_product_by_title($product_name);
            if (!$product) {
                WP_CLI::log("No product found with title $product_name");
                continue;
            }

            if (!isset($this->sessions_by_name[$name])) {
                WP_CLI::log("No session found with name $name");
                continue;
            }
            $session_id = $this->sessions_by_name[$name];

            $start_date = new DateTime($tournament['start_date']);
            $start_date_addtl = !empty($tournament['start_date_addtl'])
                ? new DateTime($tournament['start_date_addtl'])
                : $start_date;
            $registration_deadline = new DateTime($tournament['registration_deadline']);
            $early_registration_deadline = !empty($tournament['early_registration_deadline'])
                ? new DateTime($tournament['early_registration_deadline'])
                : null;

            $title = sanitize_text_field($name);
            $search_term = Usctdp_Mgmt_Model::append_token_suffix($title);

            $activity_query = new Usctdp_Mgmt_Activity_Query([
                "session_id" => $session_id,
                "product_id" => $product->id,
                "title" => $title,
            ]);
            $existing_activity = !empty($activity_query->items) ? $activity_query->items[0] : null;

            $activity_data = [
                "session_id" => $session_id,
                "product_id" => $product->id,
                "type" => "tournament",
                "title" => $title,
                "search_term" => $search_term,
                "reservation_group_id" => $this->resolve_reservation_group_id($existing_activity, $tournament['capacity']),
                "primary_sort_order" => $primary_sort_order,
                "secondary_sort_order" => 1,
                "meta" => isset($tournament['meta']) ? json_encode($tournament['meta']) : '{}'
            ];

            if ($existing_activity) {
                $activity_id = $existing_activity->id;
                WP_CLI::log("Activity exists: $title, updating (id=$activity_id)");
                $activity_query->update_item($activity_id, $activity_data);
            } else {
                WP_CLI::log("Creating activity: $title");
                $activity_id = $activity_query->add_item($activity_data);
            }

            $tournament_data = [
                "start_date" => $start_date->format("Y-m-d"),
                "start_date_addtl" => $start_date_addtl->format("Y-m-d"),
                "registration_deadline" => $registration_deadline->format("Y-m-d"),
                "early_registration_deadline" => $early_registration_deadline
                    ? $early_registration_deadline->format("Y-m-d")
                    : null,
                "schedule" => isset($tournament['schedule']) ? json_encode($tournament['schedule']) : '[]',
            ];
            $tournament_query = new Usctdp_Mgmt_Tournament_Query([
                "id" => $activity_id
            ]);
            if (!empty($tournament_query->items)) {
                $tournament_query->update_item($activity_id, $tournament_data);
            } else {
                $tournament_query->add_item(array_merge(["id" => $activity_id], $tournament_data));
            }
        }
    }

    private function import_tournament_pricing($data)
    {
        if (empty($data["tournament_pricing"])) {
            return;
        }

        $product_data = [];
        $active_sessions_by_product = [];

        foreach ($data["tournament_pricing"] as $pricing) {
            $tournament_name = trim($pricing['tournament']);
            $session_name = trim($pricing['session']);
            $product_name = trim($pricing['product']);

            $product = $this->get_product_by_title($product_name);
            if (!$product) {
                WP_CLI::log("No product found with title $product_name");
                continue;
            }

            if (!isset($this->sessions_by_name[$session_name])) {
                WP_CLI::log("No session found with name $session_name");
                continue;
            }
            $session_id = $this->sessions_by_name[$session_name];
            $woo_product_id = $product->woocommerce_id;

            if (!isset($product_data[$woo_product_id])) {
                $product_data[$woo_product_id] = [];
            }
            if (!isset($active_sessions_by_product[$product->id])) {
                $active_sessions_by_product[$product->id] = [];
            }

            $prices = [];
            if (!empty($pricing['base'])) {
                $prices['base'] = $pricing['base'];
            }
            if (!empty($pricing['early_signup'])) {
                $prices['early_signup'] = $pricing['early_signup'];
            }
            if (!empty($pricing['with_clinic'])) {
                $prices['with_clinic'] = $pricing['with_clinic'];
            }

            if (empty($prices)) {
                WP_CLI::log("No pricing found for tournament $tournament_name");
                continue;
            }

            $pricing_query = new Usctdp_Mgmt_Pricing_Query([
                "session_id" => $session_id,
                "product_id" => $product->id,
                "number" => 1,
            ]);
            if (!empty($pricing_query->items)) {
                $target = $pricing_query->items[0]->id;
                $pricing_query->update_item($target, [
                    "pricing" => json_encode($prices),
                ]);
            } else {
                $pricing_query->add_item([
                    "session_id" => $session_id,
                    "product_id" => $product->id,
                    "pricing" => json_encode($prices),
                ]);
            }

            // Appearing in tournament_pricing at all is the signal this
            // session should be wired up for this product - no separate
            // "active" flag needed on top of that.
            if (!empty($prices['base'])) {
                $product_data[$woo_product_id][$session_name] = [
                    "session_id" => $session_id,
                    "price" => $prices['base'],
                ];
                $active_sessions_by_product[$product->id][$session_id] = true;
            }
        }

        foreach ($active_sessions_by_product as $product_id => $active_ids) {
            $this->sync_product_sessions($product_id, $active_ids);
        }

        foreach ($product_data as $woo_product_id => $sessions) {
            $product = wc_get_product($woo_product_id);
            if (!$product) {
                WP_CLI::log("No WooCommerce product found for id $woo_product_id");
                continue;
            }
            $this->delete_all_product_variations($woo_product_id);
            ksort($sessions);

            $session_attribute = new WC_Product_Attribute();
            $session_attribute->set_name('Session');
            $session_attribute->set_options(array_keys($sessions));
            $session_attribute->set_position(0);
            $session_attribute->set_visible(true);
            $session_attribute->set_variation(true);

            $product->set_attributes([$session_attribute]);

            $session_post_ids = [];
            foreach ($sessions as $session_name => $info) {
                $session_post_ids[$session_name] = $info['session_id'];
            }
            $product->update_meta_data('_session_post_ids', $session_post_ids);
            $product->save();

            foreach ($sessions as $session_name => $info) {
                if (empty($info['price'])) {
                    WP_CLI::log("No price for $session_name");
                    continue;
                }
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($woo_product_id);
                $variation->set_attributes([
                    sanitize_title('Session') => $session_name,
                ]);
                $variation->set_regular_price($info['price']);
                $variation->set_manage_stock(false);
                $variation->save();
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
        WP_CLI::log('Importing classes...');
        $this->import_clinic_classes($data);
        WP_CLI::log('Importing clinic pricing...');
        $this->import_clinic_prices($data);
        WP_CLI::log('Importing tournaments...');
        $this->import_tournament_activities($data);
        WP_CLI::log('Importing tournament pricing...');
        $this->import_tournament_pricing($data);
        WP_CLI::log('Building program schedule...');
        (new Usctdp_Build_Program_Schedule())->build();
    }
}
