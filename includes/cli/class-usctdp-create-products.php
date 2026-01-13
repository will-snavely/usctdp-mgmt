<?php

class Usctdp_Create_Products
{
    public function __construct() {}

    private function get_courses()
    {
        return get_posts([
            'post_type' => 'usctdp-course',
            'posts_per_page' => -1,
        ]);
    }

    private function create_course_product($course)
    {
        // 1. Create the main product object
        $product = new WC_Product_Variable();

        // 2. Basic Information
        $course_name = get_field('name', $course->ID);
        $description = get_field('description', $course->ID);
        $age_range = get_field('age_range', $course->ID);
        $short_description = get_field('short_description', $course->ID);
        $product->set_name($course_name);
        $product->set_description($description);
        $product->set_short_description($short_description . ' - ' . $age_range);
        $product->set_status('publish');
        $product->set_sku('course-' . $course->ID);

        // 3. Define the Attributes (e.g., Size)
        $session_attribute = new WC_Product_Attribute();
        $session_attribute->set_name('Session');
        $session_attribute->set_options(array('Session I', 'Session II', 'Session III', 'Session IV'));
        $session_attribute->set_position(0);
        $session_attribute->set_visible(true);
        $session_attribute->set_variation(true);

        $num_days_attr = new WC_Product_Attribute();
        $num_days_attr->set_name('Days Per Week');
        $num_days_attr->set_options(array('1', '2'));
        $num_days_attr->set_position(1);
        $num_days_attr->set_visible(true);
        $num_days_attr->set_variation(true);

        $product->set_attributes(array($session_attribute, $num_days_attr));

        // 4. Save the parent product to get an ID
        return $product->save();
    }

    public function create()
    {
        WP_CLI::log('Creating products...');
        $courses = $this->get_courses();
        foreach ($courses as $course) {
            WP_CLI::log('Creating product for course: ' . $course->post_title);
            $product = $this->create_course_product($course);
            WP_CLI::log('Product created with ID: ' . $product);
        }
    }
}
