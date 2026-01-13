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

    public function on_post_delete($post_id, $post)
    {
        return;
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
        $cpt_classes = [
            "class-usctdp-mgmt-staff.php",
            "class-usctdp-mgmt-session.php",
            "class-usctdp-mgmt-student.php",
            "class-usctdp-mgmt-family.php",
            "class-usctdp-mgmt-class.php",
            "class-usctdp-mgmt-clinic.php",
            "class-usctdp-mgmt-clinic-prices.php",
            "class-usctdp-mgmt-payment.php"
        ];
        $prefix = plugin_dir_path(dirname(__FILE__)) . "includes/model/cpt/";
        foreach ($cpt_classes as $class) {
            require_once $prefix . $class;
        }

        $berlindb_entities = [
            "registration",
            "activity-link",
            "family-link",
            "roster-link"
        ];
        $db_prefix = plugin_dir_path(dirname(__FILE__)) . "includes/model/db/";
        $kinds = [
            ["schema", "schemas"],
            ["table", "tables"],
            ["row", "rows"],
            ["query", "queries"],
        ];
        foreach ($berlindb_entities as $entity) {
            foreach ($kinds as $kind) {
                $file = "class-usctdp-mgmt-{$entity}-{$kind[0]}.php";
                require_once $db_prefix . $kind[1] . "/" . $file;
            }
        }
    }

    private function get_model_types()
    {
        $classes = [
            new Usctdp_Mgmt_Staff(),
            new Usctdp_Mgmt_Session(),
            new Usctdp_Mgmt_Clinic(),
            new Usctdp_Mgmt_Clinic_Prices(),
            new Usctdp_Mgmt_Class(),
            new Usctdp_Mgmt_Student(),
            new Usctdp_Mgmt_Family()
        ];

        $result = [];
        foreach ($classes as $class) {
            $result[$class->post_type] = $class;
        }
        return $result;
    }

    public function register_berlindb_entities()
    {
        $tables = [
            new Usctdp_Mgmt_Registration_Table(),
            new Usctdp_Mgmt_Activity_Link_Table(),
            new Usctdp_Mgmt_Family_Link_Table(),
            new Usctdp_Mgmt_Roster_Link_Table()
        ];
        foreach ($tables as $table) {
            if (! $table->exists()) {
                $table->install();
            }
        }
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

    public function on_post_delete($post_id, $post)
    {
        if (isset($this->model_types[$post->post_type])) {
            $this->model_types[$post->post_type]->on_post_delete($post_id, $post);
        }
    }
}
