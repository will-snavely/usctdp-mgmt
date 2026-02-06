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
    public static $token_suffix = "_xxx";

    public function __construct()
    {
        $this->load_model_dependencies();
    }

    public static function append_token_suffix($str, $threshold=2)
    {
        $parts = preg_split('/\s+/', $str, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($parts as $part) {
            if (strlen($part) <= $threshold && ctype_alnum($part)) {
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
        $base_prefix= plugin_dir_path(dirname(__FILE__)) . "includes/model/";
        require_once $base_prefix . "usctdp-mgmt-model-enums.php";

        $berlindb_entities = [
            "activity",
            "clinic",
            "family",
            "registration",
            "roster-link",
            "pricing",
            "product",
            "session",
            "student",
            "tournament",
            "transaction",
            "transaction-link",
        ];
        $db_prefix = $base_prefix . "db/";
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

    public function get_db_tables()
    {
        return [
            new Usctdp_Mgmt_Activity_Table(),
            new Usctdp_Mgmt_Clinic_Table(),
            new Usctdp_Mgmt_Family_Table(),
            new Usctdp_Mgmt_Registration_Table(),
            new Usctdp_Mgmt_Roster_Link_Table(),
            new Usctdp_Mgmt_Pricing_Table(),
            new Usctdp_Mgmt_Product_Table(),
            new Usctdp_Mgmt_Session_Table(),
            new Usctdp_Mgmt_Student_Table(),
            new Usctdp_Mgmt_Tournament_Table(),
            new Usctdp_Mgmt_Transaction_Table(),
            new Usctdp_Mgmt_Transaction_Link_Table(),
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
}
