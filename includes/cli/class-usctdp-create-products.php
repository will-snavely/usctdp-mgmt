<?php

class Usctdp_Create_Products
{
    public function __construct() {}

    private function create_product_category(
            $category_name,
            $description,
            $slug) {
        $taxonomy = 'product_cat';
        $term_exists = term_exists($category_name, $taxonomy);
        if (!$term_exists) {
            $result = wp_insert_term(
                $category_name, 
                $taxonomy,     
                array(
                    'description' => $description ,
                    'slug'        => $slug
                )
            );

            if (is_wp_error($result)) {
                echo 'Error: ' . $result->get_error_message();
            } else {
                echo 'Category created successfully! Term ID: ' . $result['term_id'];
            }
        } else {
            echo 'Category already exists.';
        }
    }

    private function create_clinic_products()
    {
        $clinic_prices = get_posts([
            'post_type' => 'usctdp-clinic-prices',
            'posts_per_page' => -1,
        ]);

        $clinic_data = [];
        foreach($clinic_prices as $price) {
            $clinic = get_field('field_usctdp_clinic_prices_clinic', $price->ID);
            $session = get_field('field_usctdp_clinic_prices_session', $price->ID);
            $oneday = get_field('field_usctdp_clinic_prices_one_day_price', $price->ID);
            $twoday = get_field('field_usctdp_clinic_prices_two_day_price', $price->ID);
            if (!array_key_exists($clinic->ID, $clinic_data)) {
                $clinic_data[$clinic->ID] = [];
            } 
            $clinic_data[$clinic->ID][$session->ID] = [
                'One' => $oneday,
                'Two' => $twoday
            ];
        }

        foreach($clinic_data as $clinic_id => $sessions) {
            $product = new WC_Product_Variable();
            $clinic_name = get_field('field_usctdp_clinic_name', $clinic_id);

            $sku = 'clinic-' . $clinic_id;
            $existing_id = wc_get_product_id_by_sku($sku);
            if($existing_id) {
                continue;
            }

            WP_CLI::log('Creating product for clinic: ' . $clinic_name);
            $clinic_age_range = get_field('field_usctdp_clinic_age_range', $clinic_id);
            $product->set_name($clinic_name);
            $product->set_description('Placeholder');
            $product->set_short_description('Placeholder');
            $product->set_sku('clinic-' . $clinic_id);
            $product->set_status('publish');

            $session_attribute = new WC_Product_Attribute();
            $session_attribute->set_name('Session');
            $session_names = [];
            ksort($sessions);
            
            foreach($sessions as $session_id => $_) {
                $session_names[] = get_field('field_usctdp_session_name', $session_id);
            }
            $session_attribute->set_options($session_names);
            $session_attribute->set_position(0);
            $session_attribute->set_visible(true);
            $session_attribute->set_variation(true);

            $num_days_attr = new WC_Product_Attribute();
            $num_days_attr->set_name('Days' ); 
            $num_days_attr->set_options( array( 'One', 'Two' ) ); // The terms to use
            $num_days_attr->set_visible(true);
            $num_days_attr->set_variation(true);

            $product->set_attributes(array($session_attribute, $num_days_attr));
            $parent_id = $product->save();

            foreach($sessions as $session_id => $pricing) {
                $session_name = get_field('field_usctdp_session_name', $session_id);
                foreach($pricing as $day => $amt) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($parent_id);
                    $variation->set_attributes([ 
                        sanitize_title('Session') => $session_name,
                        sanitize_title('Days') => $day
                    ]);
                    $variation->set_regular_price($amt);
                    $variation->set_manage_stock(true);
                    $variation->set_stock_quantity(10);
                    $variation->save();
                }
            }
        }
    }

    public function create()
    {
        $categories = [
            [
                "cat" => "Beginning Juniors", 
                "desc" => "Offerings for beginning junior players.",
                "slug" => "junior-beginners"
            ],
            [
                "cat" => "Advanced Juniors", 
                "desc" => "Offerings for intermediate and advanced junior players.",
                "slug" => "junior-advanced"
            ],
            [
                "cat" => "Adult Clinics", 
                "desc" => "Tennis instruction for adults of all levels.",
                "slug" => "adult-clinics"
            ],
            [
                "cat" => "Adult Cardio", 
                "desc" => "Exercise programs for adults of all levels.",
                "slug" => "adult-cardio"
            ]
        ];

        WP_CLI::log('Creating product categories...');
        foreach($categories as $cat) {
            WP_CLI::log('Creating category: ' . $cat['cat']);
            create_product_category($cat['cat'], $cat['desc'], $cat['slug']);
        }
        WP_CLI::log('Creating products...');
        $this->create_clinic_products();
    }
}
