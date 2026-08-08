<?php

class Usctdp_Mgmt_Model
{
    public $model_types;
    public static $token_suffix = "_xxx";
    private static $db_version_option = 'usctdp_mgmt_db_version';

    public function __construct()
    {
        $this->load_model_dependencies();
    }

    public static function append_token_suffix($str, $threshold = 2)
    {
        $clean_string = preg_replace('/[^a-z0-9\s]/i', '', $str);
        $parts = preg_split('/\s+/', $clean_string, -1, PREG_SPLIT_NO_EMPTY);
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
        $base_prefix = plugin_dir_path(dirname(__FILE__)) . "model/";
        require_once $base_prefix . "usctdp-mgmt-model-enums.php";

        $berlindb_entities = [
            "activity",
            "activity-staff",
            "clinic",
            "family",
            "import-pending",
            "ledger",
            "merchandise",
            "registration",
            "reservation-group",
            "roster-link",
            "pricing",
            "product",
            "product-session",
            "program-schedule",
            "purchase",
            "roster-group",
            "roster-group-session",
            "session",
            "staff",
            "student",
            "tournament",
            "waitlist",
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
            // Reservation_Group must be created before Activity - Activity's
            // own upgrade migration backfills rows into it, and BerlinDB
            // installs/upgrades tables in this array's order.
            new Usctdp_Mgmt_Reservation_Group_Table(),
            new Usctdp_Mgmt_Activity_Table(),
            new Usctdp_Mgmt_Activity_Staff_Table(),
            new Usctdp_Mgmt_Clinic_Table(),
            new Usctdp_Mgmt_Family_Table(),
            new Usctdp_Mgmt_Import_Pending_Table(),
            new Usctdp_Mgmt_Ledger_Table(),
            new Usctdp_Mgmt_Merchandise_Table(),
            new Usctdp_Mgmt_Registration_Table(),
            new Usctdp_Mgmt_Roster_Link_Table(),
            new Usctdp_Mgmt_Pricing_Table(),
            new Usctdp_Mgmt_Product_Table(),
            new Usctdp_Mgmt_Product_Session_Table(),
            new Usctdp_Mgmt_Program_Schedule_Table(),
            new Usctdp_Mgmt_Purchase_Table(),
            new Usctdp_Mgmt_Roster_Group_Table(),
            new Usctdp_Mgmt_Roster_Group_Session_Table(),
            new Usctdp_Mgmt_Session_Table(),
            new Usctdp_Mgmt_Staff_Table(),
            new Usctdp_Mgmt_Student_Table(),
            new Usctdp_Mgmt_Tournament_Table(),
            new Usctdp_Mgmt_Waitlist_Table(),
        ];
    }

    public function install_berlindb_entities()
    {
        foreach ($this->get_db_tables() as $table) {
            $table->maybe_upgrade();
        }
        update_option(self::$db_version_option, USCTDP_MGMT_VERSION);
    }

    /**
     * Constructing each Table object registers its name with $wpdb, which
     * Query relies on for every query - this must run on every request.
     * The (comparatively expensive) install/upgrade check underneath it
     * is version-gated so it only touches the database when needed.
     */
    public function register_berlindb_entities()
    {
        $tables = $this->get_db_tables();

        if (get_option(self::$db_version_option) === USCTDP_MGMT_VERSION) {
            return;
        }

        foreach ($tables as $table) {
            $table->maybe_upgrade();
        }
        update_option(self::$db_version_option, USCTDP_MGMT_VERSION);
    }

    private static function get_one($obj, $id)
    {
        $query = new $obj(['id' => $id, 'number' => 1]);
        if (empty($query->items)) {
            return null;
        }
        return $query->items[0];
    }

    public static function get_activity($id)
    {
        return Usctdp_Mgmt_Model::get_one('Usctdp_Mgmt_Activity_Query', $id);
    }

    public static function get_expanded_activity($id)
    {
        $query = new Usctdp_Mgmt_Activity_Query();
        $result = $query->get_activity_data(['id' => $id]);
        if (!$result || !isset($result['data']) || empty($result['data'])) {
            return null;
        }
        return $result['data'][0];
    }

    public static function get_student($id)
    {
        return Usctdp_Mgmt_Model::get_one('Usctdp_Mgmt_Student_Query', $id);
    }

    public static function get_family($id)
    {
        return Usctdp_Mgmt_Model::get_one('Usctdp_Mgmt_Family_Query', $id);
    }

    public static function get_product($id)
    {
        return Usctdp_Mgmt_Model::get_one('Usctdp_Mgmt_Product_Query', $id);
    }

    public static function get_session($id)
    {
        return Usctdp_Mgmt_Model::get_one('Usctdp_Mgmt_Session_Query', $id);
    }
    public static function get_registration($id)
    {
        return Usctdp_Mgmt_Model::get_one('Usctdp_Mgmt_Registration_Query', $id);
    }

    public static function get_activity_pricing($activity)
    {
        $pricing_query = new Usctdp_Mgmt_Pricing_Query([
            'session_id' => $activity->session_id,
            'product_id' => $activity->product_id,
            'number' => 1
        ]);
        if (empty($pricing_query->items)) {
            return null;
        }
        return $pricing_query->items[0];
    }
}
