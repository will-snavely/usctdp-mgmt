<?php

class Usctdp_Import_Page_Data
{
    private $dry_run = false;

    public function import($file_path, $dry_run = false)
    {
        $this->dry_run = $dry_run;

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

        $pages = $data['pages'] ?? [];
        $menus = $data['menus'] ?? [];
        $permalink_structure = $data['permalink_structure'] ?? null;
        $woocommerce = $data['woocommerce'] ?? null;

        if ($permalink_structure !== null) {
            $this->set_permalink_structure($permalink_structure);
        }

        if ($woocommerce !== null) {
            $this->configure_woocommerce($woocommerce);
        }

        WP_CLI::log('Importing pages...');
        $slug_to_id = $this->upsert_pages($pages);

        foreach ($menus as $location => $items) {
            WP_CLI::log("Importing menu for location '$location'...");
            $this->sync_menu($location, $items, $slug_to_id);
        }

        WP_CLI::success('Page import complete.');
    }

    /**
     * Set the site's permalink structure (e.g. '/%postname%/' for page-name
     * URLs) and flush rewrite rules, same as Settings -> Permalinks or
     * `wp rewrite structure`. Without this, pages resolve as ?page_id=N.
     */
    private function set_permalink_structure($structure)
    {
        if ($this->dry_run) {
            WP_CLI::log("[dry-run] would set permalink structure to '$structure'");
            return;
        }

        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure($structure);
        // Soft flush: updates the rewrite_rules option WP actually routes
        // with, without touching .htaccess. This project's .htaccess is
        // hand-authored for Bedrock's directory layout (see web/.htaccess);
        // WP's auto-writer doesn't know about that and would clobber it
        // with the generic single-site block.
        $wp_rewrite->flush_rules(false);

        WP_CLI::log("Permalink structure set to '$structure'");
    }

    /**
     * Apply a handful of WooCommerce store-wide settings, same as toggling
     * them under WooCommerce -> Settings -> Products. Each key is optional
     * and only applied when present/truthy.
     */
    private function configure_woocommerce($settings)
    {
        if (!empty($settings['disable_reviews'])) {
            if ($this->dry_run) {
                WP_CLI::log('[dry-run] would disable WooCommerce product reviews');
            } else {
                update_option('woocommerce_enable_reviews', 'no');
                WP_CLI::log('Disabled WooCommerce product reviews');
            }
        }

        if (!empty($settings['classic_cart'])) {
            $this->set_classic_page_content('woocommerce_cart_page_id', '[woocommerce_cart]', 'cart');
        }

        if (!empty($settings['classic_checkout'])) {
            $this->set_classic_page_content('woocommerce_checkout_page_id', '[woocommerce_checkout]', 'checkout');
        }
    }

    /**
     * Overwrite a WooCommerce-managed page's content with a legacy shortcode,
     * replacing the Cart/Checkout block markup WooCommerce scaffolds by
     * default so the page renders via the classic (non-block) templates.
     */
    private function set_classic_page_content($option_name, $shortcode, $label)
    {
        $page_id = get_option($option_name);
        if (!$page_id) {
            WP_CLI::warning("WooCommerce $label page is not set, skipping");
            return;
        }

        if ($this->dry_run) {
            WP_CLI::log("[dry-run] would set WooCommerce $label page (id=$page_id) content to '$shortcode'");
            return;
        }

        wp_update_post([
            'ID'           => $page_id,
            'post_content' => $shortcode,
        ]);

        WP_CLI::log("Set WooCommerce $label page (id=$page_id) content to classic shortcode '$shortcode'");
    }

    /**
     * Create or update each page, set its Blade page template, then wire up
     * post_parent relationships in a second pass so entries can reference a
     * parent slug regardless of array order.
     */
    private function upsert_pages($pages)
    {
        $slug_to_id = [];

        foreach ($pages as $page) {
            $slug = $page['slug'];
            $existing = get_page_by_path($slug);

            if ($this->dry_run) {
                $verb = $existing ? 'update' : 'create';
                WP_CLI::log("[dry-run] would $verb page '{$page['title']}' (slug=$slug, template={$page['template']})");
                $slug_to_id[$slug] = $existing ? $existing->ID : 0;
                continue;
            }

            $postarr = [
                'post_title'  => $page['title'],
                'post_name'   => $slug,
                'post_type'   => 'page',
                'post_status' => 'publish',
            ];

            if ($existing) {
                $postarr['ID'] = $existing->ID;
                $post_id = wp_update_post($postarr, true);
            } else {
                $post_id = wp_insert_post($postarr, true);
            }

            if (is_wp_error($post_id)) {
                WP_CLI::warning("Failed to upsert page '{$page['title']}': " . $post_id->get_error_message());
                continue;
            }

            if (!empty($page['template'])) {
                update_post_meta($post_id, '_wp_page_template', $page['template']);
            }

            $slug_to_id[$slug] = $post_id;
            WP_CLI::log(($existing ? 'Updated' : 'Created') . " page '{$page['title']}' (id=$post_id)");
        }

        if ($this->dry_run) {
            return $slug_to_id;
        }

        // Second pass: resolve post_parent now that every slug has an id.
        foreach ($pages as $page) {
            if (empty($page['parent'])) {
                continue;
            }
            if (!isset($slug_to_id[$page['slug']]) || !isset($slug_to_id[$page['parent']])) {
                WP_CLI::warning("Skipping parent link for '{$page['slug']}': unknown parent slug '{$page['parent']}'");
                continue;
            }
            wp_update_post([
                'ID'          => $slug_to_id[$page['slug']],
                'post_parent' => $slug_to_id[$page['parent']],
            ]);
        }

        // Optional: designate a static front page.
        foreach ($pages as $page) {
            if (!empty($page['front_page']) && isset($slug_to_id[$page['slug']])) {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $slug_to_id[$page['slug']]);
                WP_CLI::log("Set '{$page['slug']}' as the static front page");
            }
        }

        return $slug_to_id;
    }

    /**
     * Wipe and rebuild the menu assigned to $location from the JSON tree.
     * The JSON is treated as the source of truth, so any menu items added
     * by hand in wp-admin will be removed on the next import.
     */
    private function sync_menu($location, $items, $slug_to_id)
    {
        $menu_name = 'Primary';
        $menu = wp_get_nav_menu_object($menu_name);

        if ($this->dry_run) {
            WP_CLI::log("[dry-run] would sync menu '$menu_name' -> location '$location' with " . count($items) . ' top-level item(s)');
            return;
        }

        $menu_id = $menu ? $menu->term_id : wp_create_nav_menu($menu_name);
        if (is_wp_error($menu_id)) {
            WP_CLI::warning("Failed to create menu '$menu_name': " . $menu_id->get_error_message());
            return;
        }

        foreach (wp_get_nav_menu_items($menu_id) ?: [] as $existing_item) {
            wp_delete_post($existing_item->ID, true);
        }

        $this->create_menu_items($menu_id, $items, $slug_to_id);

        $locations = get_theme_mod('nav_menu_locations', []);
        $locations[$location] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);

        WP_CLI::log("Menu '$menu_name' assigned to location '$location' (id=$menu_id)");
    }

    private function create_menu_items($menu_id, $items, $slug_to_id, $parent_id = 0)
    {
        $position = 1;

        foreach ($items as $item) {
            $args = [
                'menu-item-title'    => $item['label'],
                'menu-item-parent-id' => $parent_id,
                'menu-item-position' => $position++,
                'menu-item-status'   => 'publish',
            ];

            if (isset($item['page'])) {
                // Prefer a page from this manifest; fall back to any existing WP
                // page by slug (e.g. WooCommerce's auto-created Shop page) so the
                // menu can reference pages this tool doesn't own or create.
                $page_id = $slug_to_id[$item['page']] ?? 0;
                if (!$page_id) {
                    $existing_page = get_page_by_path($item['page']);
                    $page_id = $existing_page ? $existing_page->ID : 0;
                }
                if (!$page_id) {
                    WP_CLI::warning("Menu item '{$item['label']}' references unknown page slug '{$item['page']}', skipping");
                    continue;
                }
                $args['menu-item-type']      = 'post_type';
                $args['menu-item-object']    = 'page';
                $args['menu-item-object-id'] = $page_id;
            } elseif (isset($item['external'])) {
                $args['menu-item-type']   = 'custom';
                $args['menu-item-object'] = 'custom';
                $args['menu-item-url']    = $item['external'];
            } elseif (isset($item['url'])) {
                $args['menu-item-type']   = 'custom';
                $args['menu-item-object'] = 'custom';
                $args['menu-item-url']    = home_url($item['url']);
            } else {
                WP_CLI::warning("Menu item '{$item['label']}' has none of 'page', 'url', or 'external', skipping");
                continue;
            }

            $item_id = wp_update_nav_menu_item($menu_id, 0, $args);
            if (is_wp_error($item_id)) {
                WP_CLI::warning("Failed to create menu item '{$item['label']}': " . $item_id->get_error_message());
                continue;
            }

            if (!empty($item['children'])) {
                $this->create_menu_items($menu_id, $item['children'], $slug_to_id, $item_id);
            }
        }
    }
}
