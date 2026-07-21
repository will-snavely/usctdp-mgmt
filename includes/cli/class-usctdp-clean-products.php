<?php

class Usctdp_Clean_Products
{
    public function clean_products()
    {
        $this->clean_usctdp_products();
        $this->clean_woocommerce_products();
    }

    private function clean_usctdp_products()
    {
        $query = new Usctdp_Mgmt_Product_Query([
            'number' => false,
            'fields' => 'ids',
        ]);

        foreach ($query->items as $product_id) {
            WP_CLI::log("Removing usctdp_product with id $product_id");
            $query->delete_item($product_id);
        }
    }

    private function clean_woocommerce_products()
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
