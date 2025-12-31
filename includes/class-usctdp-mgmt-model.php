<?php

abstract class Usctdp_Mgmt_Model_Type
{
    abstract public string $post_type { get; }
    abstract public array $wp_post_settings { get; }
    abstract public array $acf_settings { get; }

    public function get_computed_post_fields($data, $postarr)
    {
        return [];
    }

    public function get_update_value_hooks()
    {
        return [];
    }

    public function get_prepare_field_hooks()
    {
        return [];
    }
}

class Usctdp_Mgmt_Model
{
    public $model_types;

    public function __construct()
    {
        $this->load_model_dependencies();
        $this->model_types = $this->get_model_types();
    }

    public function load_model_dependencies()
    {
        $model_classes = [
            "class-usctdp-mgmt-staff.php",
            "class-usctdp-mgmt-session.php",
            "class-usctdp-mgmt-student.php",
            "class-usctdp-mgmt-family.php",
            "class-usctdp-mgmt-registration.php",
            "class-usctdp-mgmt-class.php",
            "class-usctdp-mgmt-course.php",
            "class-usctdp-mgmt-pricing.php",
            "class-usctdp-mgmt-payment.php"
        ];
        $prefix = plugin_dir_path(dirname(__FILE__)) . "includes/model/";
        foreach ($model_classes as $class) {
            require_once $prefix . $class;
        }
    }

    private function get_model_types()
    {
        $classes = [
            new Usctdp_Mgmt_Staff(),
            new Usctdp_Mgmt_Session(),
            new Usctdp_Mgmt_Course(),
            new Usctdp_Mgmt_Pricing(),
            new Usctdp_Mgmt_Class(),
            new Usctdp_Mgmt_Student(),
            new Usctdp_Mgmt_Family(),
            new Usctdp_Mgmt_Registration(),

        ];

        $result = [];
        foreach ($classes as $class) {
            $result[$class->post_type] = $class;
        }
        return $result;
    }

    public function register_model_types()
    {
        foreach ($this->model_types as $key => $type) {
            register_post_type($type->post_type, $type->wp_post_settings);
            acf_add_local_field_group($type->acf_settings);
        }
    }

    public function generate_computed_post_fields($data, $postarr)
    {
        if (isset($this->model_types[$data['post_type']])) {
            $result = $this->model_types[$data['post_type']]->get_computed_post_fields($data, $postarr);
            foreach ($result as $key => $value) {
                $data[$key] = $value;
            }
        }
        return $data;
    }
}
