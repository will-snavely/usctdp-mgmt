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
    public static $token_suffix = "_xxxx";

    public function __construct()
    {
        $this->load_model_dependencies();
        $this->model_types = $this->create_model_types();
    }

    public static function append_token_suffix($str)
    {
        $parts = preg_split('/\s+/', $str, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($parts as $part) {
            if (strlen($part) <= 2 && ctype_alnum($part)) {
                $result[] = $part . self::$token_suffix;
            } else {
                $result[] = $part;
            }
        }
        return implode(" ", $result);
    }

    public static function strip_token_suffix($str)
    {
        $result = str_replace(self::$token_suffix, "", $str);
        return $result;
    }

    public function load_model_dependencies()
    {
        $cpt_classes = [
            "class-usctdp-mgmt-staff.php",
            "class-usctdp-mgmt-session.php",
            "class-usctdp-mgmt-family.php",
            "class-usctdp-mgmt-class.php",
            "class-usctdp-mgmt-clinic.php",
            "class-usctdp-mgmt-tournament.php",
            "class-usctdp-mgmt-clinic-prices.php"
        ];
        $prefix = plugin_dir_path(dirname(__FILE__)) . "includes/model/cpt/";
        foreach ($cpt_classes as $class) {
            require_once $prefix . $class;
        }

        $berlindb_entities = [
            "registration",
            "session",
            "student",
            "family",
            "clinic-class",
            "activity-link",
            "family-link",
            "roster-link",
            "transaction",
            "transaction-link",
            "product-link",
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

    private function create_model_types()
    {
        $classes = [
            new Usctdp_Mgmt_Staff(),
            new Usctdp_Mgmt_Clinic(),
            new Usctdp_Mgmt_Tournament(),
        ];

        $result = [];
        foreach ($classes as $class) {
            $result[$class->post_type] = $class;
        }
        return $result;
    }

    public function get_cpt_types()
    {
        return $this->model_types;
    }

    public function get_db_tables()
    {
        return [
            new Usctdp_Mgmt_Registration_Table(),
            new Usctdp_Mgmt_Session_Table(),
            new Usctdp_Mgmt_Student_Table(),
            new Usctdp_Mgmt_Family_Table(),
            new Usctdp_Mgmt_Clinic_Class_Table(),
            new Usctdp_Mgmt_Transaction_Table(),
            new Usctdp_Mgmt_Family_Link_Table(),
            new Usctdp_Mgmt_Roster_Link_Table(),
            new Usctdp_Mgmt_Transaction_Link_Table(),
            new Usctdp_Mgmt_Product_Link_Table(),
        ];
    }

    public function register_berlindb_entities()
    {
        $tables = $this->get_db_tables();
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
