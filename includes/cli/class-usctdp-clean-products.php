<?php

class Usctdp_Clean_Products
{
    public function clean_products()
    {
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
        ));

        foreach ($products as $product_id) {
            WP_CLI::log("Removing product with id $product_id");
            wp_delete_post($product_id, true);
        }
    }
}
