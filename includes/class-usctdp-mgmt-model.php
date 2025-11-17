<?php

interface Usctdp_Mgmt_Model_Type
{
    public string $post_type { get; }
    public array $wp_post_settings { get; }
    public array $acf_settings { get; }
}

class Usctdp_Mgmt_Model {
    public function __construct()
    {
        $this->load_model_dependencies();
    }

    public function load_model_dependencies() {
        $model_classes = [
            "class-usctdp-mgmt-staff.php",
            "class-usctdp-mgmt-session.php",
            "class-usctdp-mgmt-class.php"
        ];
        $prefix = plugin_dir_path(dirname(__FILE__)) . "includes/model/";
        foreach($model_classes as $class) {
            require_once $prefix . $class;
        }
    }

    public function get_model_types() {
        return [
            new Usctdp_Mgmt_Staff(),
            new Usctdp_Mgmt_Session(),
            new Usctdp_Mgmt_Class()
        ];
    }

    public function register_model_types() {
        foreach ($this->get_model_types() as $type) {
            register_post_type($type->post_type, $type->wp_post_settings);
            acf_add_local_field_group($type->acf_settings);
        }
    }
}