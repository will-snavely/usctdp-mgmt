<?php

class Select2_Search_Exception extends Exception
{
}

class Usctdp_Mgmt_Select2
{
    private $select2_search_targets;
    private static $limit = 20;

    public function __construct()
    {
        $this->select2_search_targets = [
            'session' => [
                'callback' => $this->select2_session_search(...),
                'filters' => [
                    'active' => intval(...),
                    'category' => intval(...),
                    // Used by the Rosters page's "add session to roster"
                    // picker so it doesn't offer a session already in the
                    // specific roster being edited - sessions can belong to
                    // more than one roster, so this deliberately doesn't
                    // exclude sessions that are only in some *other* group.
                    'exclude_roster_group_id' => intval(...)
                ]
            ],
            'activity' => [
                'callback' => $this->select2_activity_search(...),
                'filters' => [
                    'session_id' => intval(...),
                    'product_id' => intval(...),
                    // CSV of activity ids to leave out - the "add a clinic
                    // sharing court space" picker on the Activities page
                    // uses this to exclude the currently-selected activity
                    // and its existing reservation-group members.
                    'exclude_activity_ids' => sanitize_text_field(...)
                ]
            ],
            'product' => [
                'callback' => $this->select2_product_search(...),
                'filters' => [
                    'type' => sanitize_text_field(...),
                    'session_id' => intval(...)
                ]
            ],
            'family' => [
                'callback' => $this->select2_family_search(...),
                'filters' => []
            ],
            'student' => [
                'callback' => $this->select2_student_search(...),
                'filters' => [
                    'family_id' => intval(...)
                ]
            ],
            'staff' => [
                'callback' => $this->select2_staff_search(...),
                'filters' => [
                    // Excludes staff already assigned to the activity being
                    // edited - same reasoning as 'exclude_roster_group_id' on
                    // the 'session' target above.
                    'exclude_activity_id' => intval(...)
                ]
            ],
            'travel_package' => [
                'callback' => $this->select2_travel_package_search(...),
                'filters' => [
                    'session_id' => intval(...)
                ]
            ],
            'travel_camp_option' => [
                'callback' => $this->select2_travel_camp_option_search(...),
                'filters' => [
                    'session_id' => intval(...),
                    'package' => sanitize_text_field(...)
                ]
            ],
        ];
    }

    public function is_valid_target($target)
    {
        return array_key_exists($target, $this->select2_search_targets);
    }

    public function search($target, $search, $filters)
    {
        if ($this->is_valid_target($target)) {
            $search_target = $this->select2_search_targets[$target];
            return $search_target['callback']($search, $filters);
        } else {
            return [];
        }
    }

    public function get_filters($target)
    {
        if ($this->is_valid_target($target)) {
            return $this->select2_search_targets[$target]['filters'];
        } else {
            return [];
        }
    }

    public function select2_session_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Session_Query();
        $active = $filters['active'] ?? null;
        $category = $filters['category'] ?? null;
        $exclude_roster_group_id = $filters['exclude_roster_group_id'] ?? null;
        $query_results = $query->search_sessions($search, $active, $category, self::$limit, $exclude_roster_group_id);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'category' => intval($result->category)
                );
            }
        }
        return $results;
    }

    private function select2_activity_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Activity_Query();
        $session_id = $filters['session_id'] ?? null;
        $product_id = $filters['product_id'] ?? null;
        $exclude_activity_ids = !empty($filters['exclude_activity_ids'])
            ? array_map('intval', explode(',', $filters['exclude_activity_ids']))
            : null;
        $query_results = $query->search_activities($search, $session_id, $product_id, self::$limit, $exclude_activity_ids);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'type' => $result->type,
                    'session_id' => intval($result->session_id),
                    'product_id' => intval($result->product_id),
                );
            }
        }
        return $results;
    }

    private function select2_product_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Product_Query();
        $activity_type = $filters['type'] ?? null;
        $session_id = $filters['session_id'] ?? null;
        $session_category = null;

        if($session_id) {
            $session = Usctdp_Mgmt_Model::get_session($session_id);
            $session_category = $session->category->value;
        }

        $query_results = $query->search_products($search, $session_category, $activity_type, self::$limit);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'code' => $result->code,
                    'category' => intval($result->session_category),
                    'type' => intval($result->type)
                );
            }
        }
        return $results;
    }

    private function select2_family_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Family_Query();
        $query_results = $query->search_families($search, self::$limit);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'address' => $result->address,
                    'city' => $result->city,
                    'state' => $result->state,
                    'zip' => $result->zip,
                    'phone_numbers' => json_decode($result->phone_numbers),
                    'emails' => json_decode($result->emails),
                    'notes' => $result->notes,
                );
            }
        }
        return $results;
    }

    /**
     * image_url resolution mirrors StaffRepository::hydrate() on the theme
     * side (projects/usctdp-bedrock/web/app/themes/usctdp-theme/app/
     * Repositories/StaffRepository.php) - usctdp_staff only stores a raw
     * image_id (WP attachment post id), Usctdp_Mgmt_Staff_Row doesn't
     * resolve it, so every consumer that wants a display URL does the
     * wp_get_attachment_image_url() call itself.
     */
    private function select2_staff_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Staff_Query();
        $exclude_activity_id = $filters['exclude_activity_id'] ?? null;
        $query_results = $query->search_staff($search, $exclude_activity_id, self::$limit);
        if ($query_results) {
            foreach ($query_results as $result) {
                $image_url = $result->image_id
                    ? (wp_get_attachment_image_url((int) $result->image_id, 'thumbnail') ?: null)
                    : null;
                $results[] = array(
                    'id' => $result->id,
                    'text' => trim($result->first_name . ' ' . $result->last_name),
                    'image_url' => $image_url,
                );
            }
        }
        return $results;
    }

    private function select2_student_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Student_Query();
        $family_id = $filters['family_id'] ?? null;
        $query_results = $query->search_students($search, $family_id, self::$limit);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'level' => $result->level,
                    'first' => $result->first,
                    'last' => $result->last,
                );
            }
        }
        return $results;
    }

    /**
     * Travel Team packages/camp-options aren't backed by their own DB rows -
     * a "package" is just a grouping of the travel team product's WC
     * variations by their Package/Camp Option attributes (see
     * Usctdp_Import_Session_Data::import_travel_team_packages()), with each
     * variation's _camp_activity_id meta pointing at the real camp activity
     * that the days/capacity/registration actually get booked against.
     * Resolves the one product linked to $session_id (via
     * usctdp_product_session, not select2_product_search()'s category-level
     * match - see the dead-end this replaced, previously
     * Usctdp_Mgmt_Admin_Ajax::ajax_get_travel_team_options()) and walks its
     * variations into that same package => camp_options shape, shared by
     * both targets below so a package's camp option count/sole option can
     * be computed once per package regardless of which target is asking.
     */
    private function resolve_travel_team_packages($session_id)
    {
        global $wpdb;
        $product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}usctdp_product_session WHERE session_id = %d",
            $session_id
        ));
        if (count($product_ids) !== 1) {
            return [];
        }

        $product = Usctdp_Mgmt_Model::get_product($product_ids[0]);
        if (!$product) {
            return [];
        }

        $woo_product = wc_get_product($product->woocommerce_id);
        if (!$woo_product || !$woo_product->is_type('variable')) {
            return [];
        }

        $packages = [];
        foreach ($woo_product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            $attributes = $variation->get_attributes();
            $package_name = $attributes['package'] ?? null;
            $camp_name = $attributes['camp-option'] ?? null;
            $camp_activity_id = $variation->get_meta('_camp_activity_id');
            if (!$package_name || !$camp_name || !$camp_activity_id) {
                continue;
            }

            if (!isset($packages[$package_name])) {
                $packages[$package_name] = [
                    'name' => $package_name,
                    'camp_days' => $variation->get_meta('_camp_days'),
                    'matches' => $variation->get_meta('_matches'),
                    'product_id' => $product->id,
                    'product_name' => $product->title,
                    'camp_options' => [],
                ];
            }
            $packages[$package_name]['camp_options'][] = [
                'camp_activity_id' => (int) $camp_activity_id,
                'camp_name' => $camp_name,
                'price' => $variation->get_price(),
            ];
        }

        return array_values($packages);
    }

    private function format_travel_camp_option($option)
    {
        return array(
            'id' => $option['camp_activity_id'],
            'text' => $option['camp_name'] . ' - $' . number_format((float) $option['price'], 2),
            'camp_name' => $option['camp_name'],
            'price' => $option['price'],
        );
    }

    private function select2_travel_package_search($search, $filters)
    {
        $session_id = $filters['session_id'] ?? null;
        if (!$session_id) {
            return [];
        }

        $results = [];
        foreach ($this->resolve_travel_team_packages($session_id) as $pkg) {
            if ($search && stripos($pkg['name'], $search) === false) {
                continue;
            }
            $camp_options = $pkg['camp_options'];
            $matches = (int) $pkg['matches'];
            $results[] = array(
                'id' => $pkg['name'],
                'text' => $pkg['name'] . ' (' . $pkg['camp_days'] . ' days, ' . $matches . ' ' . ($matches === 1 ? 'match' : 'matches') . ')',
                'camp_days' => $pkg['camp_days'],
                'matches' => $matches,
                'product_id' => $pkg['product_id'],
                'product_name' => $pkg['product_name'],
                // Lets the package selector auto-resolve its sole camp
                // option (see 'travel-package-selector'.autoSelectChild in
                // usctdp-mgmt-admin-register.js) without a second
                // select2_search round-trip for the common one-camp case.
                'camp_option_count' => count($camp_options),
                'sole_camp_option' => count($camp_options) === 1 ? $this->format_travel_camp_option($camp_options[0]) : null,
            );
        }
        return $results;
    }

    private function select2_travel_camp_option_search($search, $filters)
    {
        $session_id = $filters['session_id'] ?? null;
        $package = $filters['package'] ?? null;
        if (!$session_id || !$package) {
            return [];
        }

        $pkg = null;
        foreach ($this->resolve_travel_team_packages($session_id) as $candidate) {
            if ($candidate['name'] === $package) {
                $pkg = $candidate;
                break;
            }
        }
        if (!$pkg) {
            return [];
        }

        $results = [];
        foreach ($pkg['camp_options'] as $option) {
            if ($search && stripos($option['camp_name'], $search) === false) {
                continue;
            }
            $results[] = $this->format_travel_camp_option($option);
        }
        return $results;
    }
}
