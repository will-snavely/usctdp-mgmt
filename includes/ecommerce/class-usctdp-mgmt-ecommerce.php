<?php

/**
 * The WooCommerce integration class.
 *
 * Checks for WooCommerce dependency and handles:
 * - Product synchronization (Course -> Product)
 * - Checkout customization
 * - Registration creation
 *
 * @since      1.0.0
 * @package    Usctdp_Mgmt
 * @subpackage Usctdp_Mgmt/includes/woocommerce
 * @author     Will Snavely <will.snavely@gmail.com>
 */
class Usctdp_Mgmt_Ecommerce
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Check if WooCommerce is active.
     * 
     * @return bool
     */
    public static function is_woocommerce_active()
    {
        return class_exists('WooCommerce');
    }

    /**
     * Sync a Course post to a WooCommerce Product.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function sync_course_product($post_id)
    {
        // Check if this is a revision or autosave
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Check if this is a 'usctdp-course' post
        if (get_post_type($post_id) !== 'usctdp-course') {
            return;
        }

        // Get the course object
        $course = get_post($post_id);
        if (!$course) {
            return;
        }

        // Check if a linked product already exists
        $product_id = get_post_meta($post_id, 'usctdp_linked_product_id', true);

        // Get course data
        $course_title = $course->post_title;
        $course_description = $course->post_content;
        $short_description = get_field('short_description', $post_id);

        if ($product_id && get_post_type($product_id) === 'product') {
            // Update existing product
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_name($course_title);
                $product->set_description($course_description);
                $product->set_short_description($short_description ? $short_description : '');
                $product->save();
            }
        } else {
            // Create new product
            $product = new WC_Product_Simple();
            $product->set_name($course_title);
            $product->set_status('publish'); // Or 'draft' depending on preference
            $product->set_catalog_visibility('visible');
            $product->set_description($course_description);
            $product->set_short_description($short_description ? $short_description : '');
            $product->set_price(0); // Price is determined dynamically but needs a base price? Or set to 0.
            $product->set_regular_price(0);
            $product->set_virtual(true); // Courses are virtual
            $product->set_downloadable(false);
            
            // Save product to generate ID
            $new_product_id = $product->save();

            if ($new_product_id) {
                // Link course to product
                update_post_meta($post_id, 'usctdp_linked_product_id', $new_product_id);
                // Link product to course (bi-directional for easier lookup)
                update_post_meta($new_product_id, 'usctdp_linked_course_id', $post_id);
            }
        }
    }

    /**
     * Render custom fields on the product page.
     */
    public function render_product_fields()
    {
        global $post;
        
        $course_id = get_post_meta($post->ID, 'usctdp_linked_course_id', true);
        if (!$course_id) {
            return;
        }

        $sessions = $this->get_available_sessions($course_id);

        ?>
        <div class="usctdp-product-fields">
            <input type="hidden" id="usctdp_course_id" name="usctdp_course_id" value="<?php echo esc_attr($course_id); ?>">
            
            <div class="usctdp-field-group">
                <label for="usctdp_session"><?php _e('Select Session', 'usctdp-mgmt'); ?></label>
                <select id="usctdp_session" name="usctdp_session" required>
                    <option value=""><?php _e('Choose a session...', 'usctdp-mgmt'); ?></option>
                    <?php foreach ($sessions as $session) : ?>
                        <option value="<?php echo esc_attr($session->ID); ?>"><?php echo esc_html($session->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="usctdp_classes_container" style="display:none;">
                <label><?php _e('Select Class Days', 'usctdp-mgmt'); ?></label>
                <div id="usctdp_classes_list">
                    <!-- Populated via AJAX -->
                </div>
            </div>

            <div class="usctdp-field-group">
                 <label for="usctdp_student"><?php _e('Select Student', 'usctdp-mgmt'); ?></label>
                 <?php if (is_user_logged_in()) : ?>
                    <?php $students = $this->get_user_students(get_current_user_id()); ?>
                    <select id="usctdp_student" name="usctdp_student" required>
                        <option value=""><?php _e('Choose a student...', 'usctdp-mgmt'); ?></option>
                        <?php foreach ($students as $student) : ?>
                            <option value="<?php echo esc_attr($student->ID); ?>"><?php echo esc_html($student->post_title); ?></option>
                        <?php endforeach; ?>
                        <option value="new"><?php _e('+ Add New Student', 'usctdp-mgmt'); ?></option>
                    </select>
                 <?php else : ?>
                    <p><?php _e('Please <a href="/my-account">login</a> to select a student.', 'usctdp-mgmt'); ?></p>
                 <?php endif; ?>
            </div>
            
            <div id="usctdp_new_student_fields" style="display:none;">
                <h4><?php _e('New Student Details', 'usctdp-mgmt'); ?></h4>
                <p>
                    <label for="usctdp_new_student_first_name"><?php _e('First Name', 'usctdp-mgmt'); ?></label>
                    <input type="text" name="usctdp_new_student_first_name" id="usctdp_new_student_first_name">
                </p>
                <p>
                    <label for="usctdp_new_student_last_name"><?php _e('Last Name', 'usctdp-mgmt'); ?></label>
                    <input type="text" name="usctdp_new_student_last_name" id="usctdp_new_student_last_name">
                </p>
                <p>
                    <label for="usctdp_new_student_dob"><?php _e('Date of Birth', 'usctdp-mgmt'); ?></label>
                    <input type="date" name="usctdp_new_student_dob" id="usctdp_new_student_dob">
                </p>
            </div>
        </div>
        <?php
    }

    private function get_available_sessions($course_id)
    {
        $args = [
            'post_type' => 'usctdp-pricing',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'course',
                    'value' => $course_id,
                    'compare' => '='
                ]
            ]
        ];
        $pricings = get_posts($args);
        
        $sessions = [];
        foreach ($pricings as $pricing) {
            $session_id = get_field('session', $pricing->ID);
            if ($session_id) {
                $session = get_post($session_id);
                if ($session) {
                    $sessions[$session->ID] = $session;
                }
            }
        }
        return array_values($sessions);
    }

    private function get_user_students($user_id)
    {
        $args = [
            'post_type' => 'usctdp-family',
            'meta_query' => [
                [
                    'key' => 'assigned_user',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ]
        ];
        $families = get_posts($args);
        
        if (empty($families)) {
            return [];
        }
        
        $family_id = $families[0]->ID;

        $args_students = [
            'post_type' => 'usctdp-student',
            'posts_per_page' => -1,
             'meta_query' => [
                [
                    'key' => 'family',
                    'value' => $family_id,
                    'compare' => '='
                ]
            ]
        ];
        return get_posts($args_students);
    }

    public function get_classes_ajax()
    {
        $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

        if (!$session_id || !$course_id) {
            wp_send_json_error('Missing session or course ID');
        }

        $args = [
            'post_type' => 'usctdp-class',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'session',
                    'value' => $session_id,
                    'compare' => '='
                ],
                [
                    'key' => 'course',
                    'value' => $course_id,
                    'compare' => '='
                ]
            ]
        ];
        
        $classes = get_posts($args);
        $options = [];
        foreach ($classes as $class) {
            $dow = get_field('day_of_week', $class->ID);
            $options[] = [
                'id' => $class->ID,
                'title' => $class->post_title,
                'dow' => $dow,
            ];
        }

        wp_send_json_success($options);
    }

    public function validate_add_to_cart($passed, $product_id, $quantity)
    {
        $course_id = get_post_meta($product_id, 'usctdp_linked_course_id', true);
        if (!$course_id) {
            return $passed;
        }

        if (empty($_POST['usctdp_session'])) {
            wc_add_notice(__('Please select a session.', 'usctdp-mgmt'), 'error');
            return false;
        }

        if (empty($_POST['usctdp_classes']) || !is_array($_POST['usctdp_classes'])) {
            wc_add_notice(__('Please select at least one class.', 'usctdp-mgmt'), 'error');
            return false;
        }

        $class_count = count($_POST['usctdp_classes']);
        if ($class_count > 2) {
             wc_add_notice(__('You can only select up to 2 classes.', 'usctdp-mgmt'), 'error');
             return false;
        }

        if (empty($_POST['usctdp_student'])) {
            wc_add_notice(__('Please select a student.', 'usctdp-mgmt'), 'error');
            return false;
        }

        if ($_POST['usctdp_student'] === 'new') {
            if (empty($_POST['usctdp_new_student_first_name']) || empty($_POST['usctdp_new_student_last_name'])) {
                 wc_add_notice(__('Please enter new student details.', 'usctdp-mgmt'), 'error');
                 return false;
            }
        }

        return $passed;
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        $course_id = get_post_meta($product_id, 'usctdp_linked_course_id', true);
        if (!$course_id) {
            return $cart_item_data;
        }

        if (isset($_POST['usctdp_session'])) {
            $cart_item_data['usctdp_session'] = sanitize_text_field($_POST['usctdp_session']);
        }
        if (isset($_POST['usctdp_classes'])) {
            $cart_item_data['usctdp_classes'] = array_map('intval', $_POST['usctdp_classes']);
        }
        if (isset($_POST['usctdp_student'])) {
            $cart_item_data['usctdp_student'] = sanitize_text_field($_POST['usctdp_student']);
            
            if ($_POST['usctdp_student'] === 'new') {
                $cart_item_data['usctdp_new_student'] = [
                    'first_name' => sanitize_text_field($_POST['usctdp_new_student_first_name']),
                    'last_name' => sanitize_text_field($_POST['usctdp_new_student_last_name']),
                    'dob' => sanitize_text_field($_POST['usctdp_new_student_dob']),
                ];
            }
        }

        return $cart_item_data;
    }

    public function get_cart_item_data($item_data, $cart_item)
    {
        if (isset($cart_item['usctdp_session'])) {
            $session = get_post($cart_item['usctdp_session']);
            $item_data[] = [
                'name' => __('Session', 'usctdp-mgmt'),
                'value' => $session ? $session->post_title : '',
            ];
        }

        if (isset($cart_item['usctdp_classes'])) {
             $names = [];
             foreach ($cart_item['usctdp_classes'] as $class_id) {
                 $cls = get_post($class_id);
                 if ($cls) $names[] = $cls->post_title;
             }
             $item_data[] = [
                'name' => __('Classes', 'usctdp-mgmt'),
                'value' => implode(', ', $names),
             ];
        }

        if (isset($cart_item['usctdp_student'])) {
            if ($cart_item['usctdp_student'] === 'new' && isset($cart_item['usctdp_new_student'])) {
                $value = $cart_item['usctdp_new_student']['first_name'] . ' ' . $cart_item['usctdp_new_student']['last_name'] . ' (New)';
            } else {
                $student = get_post($cart_item['usctdp_student']);
                $value = $student ? $student->post_title : '';
            }
            $item_data[] = [
                'name' => __('Student', 'usctdp-mgmt'),
                'value' => $value,
            ];
        }

        return $item_data;
    }

    public function calculate_totals($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['usctdp_session']) && isset($cart_item['usctdp_classes'])) {
                $product_id = $cart_item['product_id'];
                $course_id = get_post_meta($product_id, 'usctdp_linked_course_id', true);
                $session_id = $cart_item['usctdp_session'];
                $num_days = count($cart_item['usctdp_classes']);

                $pricing = $this->get_pricing_post($course_id, $session_id);
                if ($pricing) {
                    $price = 0;
                    if ($num_days == 1) {
                        $price = get_field('one_day_price', $pricing->ID);
                    } elseif ($num_days == 2) {
                        $price = get_field('two_day_price', $pricing->ID);
                    }
                    
                    if ($price) {
                        $cart_item['data']->set_price($price);
                    }
                }
            }
        }
    }

    private function get_pricing_post($course_id, $session_id)
    {
        $args = [
            'post_type' => 'usctdp-pricing',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'course',
                    'value' => $course_id,
                    'compare' => '='
                ],
                [
                    'key' => 'session',
                    'value' => $session_id,
                    'compare' => '='
                ]
            ]
        ];
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : null;
    }

    public function save_order_item_data($item, $cart_item_key, $values, $order)
    {
        if (isset($values['usctdp_session'])) {
            $item->add_meta_data('_usctdp_session', $values['usctdp_session']);
        }
        if (isset($values['usctdp_classes'])) {
            $item->add_meta_data('_usctdp_classes', json_encode($values['usctdp_classes']));
        }
        if (isset($values['usctdp_student'])) {
            $item->add_meta_data('_usctdp_student', $values['usctdp_student']);
        }
        if (isset($values['usctdp_new_student'])) {
            $item->add_meta_data('_usctdp_new_student', $values['usctdp_new_student']);
        }
    }

    public function create_registrations($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) return;

        if (get_post_meta($order_id, '_usctdp_registrations_created', true)) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $session_id = $item->get_meta('_usctdp_session');
            $classes_json = $item->get_meta('_usctdp_classes');
            $student_id = $item->get_meta('_usctdp_student');
            $new_student_data = $item->get_meta('_usctdp_new_student');

            if ($session_id && $classes_json) {
                if ($student_id === 'new' && $new_student_data) {
                    $student_id = $this->create_student_from_order($new_student_data, $order->get_user_id());
                }

                if (!$student_id) continue;

                $class_ids = json_decode($classes_json);
                if (is_array($class_ids)) {
                    foreach ($class_ids as $class_id) {
                        $registration_data = [
                            'post_type' => 'usctdp-registration',
                            'post_status' => 'publish',
                            'post_author' => $order->get_user_id(),
                        ];
                        $reg_id = wp_insert_post($registration_data);
                        
                        if ($reg_id && !is_wp_error($reg_id)) {
                             update_field('field_usctdp_registration_student', $student_id, $reg_id);
                             update_field('field_usctdp_registration_class', $class_id, $reg_id);
                             update_field('field_usctdp_registration_created', current_time('mysql'), $reg_id);
                             update_field('field_usctdp_registration_balance', 0, $reg_id);
                             update_post_meta($reg_id, '_usctdp_order_id', $order_id);
                        }
                    }
                }
            }
        }
        
        update_post_meta($order_id, '_usctdp_registrations_created', true);
    }

    private function create_student_from_order($data, $user_id)
    {
        $args = [
            'post_type' => 'usctdp-family',
            'meta_query' => [
                [
                    'key' => 'assigned_user',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ]
        ];
        $families = get_posts($args);
        $family_id = !empty($families) ? $families[0]->ID : 0;
        
        if (!$family_id) {
            return 0;
        }

        $student_data = [
            'post_type' => 'usctdp-student',
            'post_status' => 'publish',
            'post_title' => $data['last_name'] . ', ' . $data['first_name'],
        ];
        
        $student_id = wp_insert_post($student_data);
        if ($student_id && !is_wp_error($student_id)) {
            update_field('field_usctdp_student_first_name', $data['first_name'], $student_id);
            update_field('field_usctdp_student_last_name', $data['last_name'], $student_id);
            $dob = str_replace('-', '', $data['dob']);
            update_field('field_usctdp_student_birth_date', $dob, $student_id);
            update_field('field_usctdp_student_family', $family_id, $student_id);
        }
        
        return $student_id;
    }
}
