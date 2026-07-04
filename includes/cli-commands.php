<?php

// Check to ensure WordPress functions are available and prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Cli_Command
{
    public function __construct()
    {
        $this->load_dependencies();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-clean.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-product-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-session-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-family-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-staff-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-people-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-registration-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-roster-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-clean-products.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-meta.php";
    }

    public function gen_people($args, $assoc_args)
    {
        $generator = new Usctdp_Random_People_Generator();
        $generator->generate_random(10, 20, 8);
    }

    public function gen_registrations($args, $assoc_args)
    {
        $generator = new Usctdp_Random_Registration_Generator();
        $count = 50;
        $chance_unpaid = 0;
        if (isset($args[0])) {
            $count = intval($args[0]);
        }
        if (isset($args[1])) {
            $chance_unpaid = intval($args[1]);
        }
        $generator->generate_random($count, $chance_unpaid);
    }

    public function gen_rosters($args, $assoc_args)
    {
        $generator = new Usctdp_Roster_Generator();
        $generator->create_rosters();
    }

    public function import_families($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $mock = false;
        if (isset($args[1])) {
            $mock = boolval($args[1]);
        }
        $generator = new Usctdp_Import_Family_Data();
        $generator->import($file_path, $mock);
    }

    public function import_products($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }

        $skip_download = false;
        if ($args && count($args) > 1) {
            if ($args[1] === "true") {
                $skip_download = true;
            }
        }
        $generator = new Usctdp_Import_Product_Data();
        $generator->import($file_path, $skip_download);
    }

    public function import_sessions($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $generator = new Usctdp_Import_Session_Data();
        $generator->import($file_path);
    }

    public function import_staff($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $generator = new Usctdp_Import_Staff_Data();
        $generator->import($file_path);
    }
    public function meta($args, $assoc_args)
    {
        if (count($args) < 1) {
            WP_CLI::error('Provide operation: get, set, del');
            return;
        }
        $operation = $args[0];

        if (count($args) < 2) {
            WP_CLI::error('Provide table name');
            return;
        }
        $table = $args[1];
        $meta = new Usctdp_Meta($table);

        switch ($operation) {
            case 'get':
                if (count($args) < 3) {
                    WP_CLI::error('Provide object id.');
                    return;
                }
                $object_id = $args[2];
                $meta_key = null;
                if (count($args) >= 4) {
                    $meta_key = $args[3];
                }
                $meta->get_meta($object_id, $meta_key);
                break;
            case 'set':
                if (count($args) < 3) {
                    WP_CLI::error('Provide object id.');
                    return;
                }
                if (count($args) < 4) {
                    WP_CLI::error('Provide meta key.');
                    return;
                }
                if (count($args) < 5) {
                    WP_CLI::error('Provide meta value.');
                    return;
                }
                $object_id = $args[2];
                $meta_key = $args[3];
                $meta_value = $args[4];
                $meta->set_meta($object_id, $meta_key, $meta_value);
                break;
            case 'del':
                if (count($args) < 2) {
                    WP_CLI::error('Provide object id.');
                    return;
                }
                $object_id = $args[2];
                $meta_key = null;
                if (count($args) >= 3) {
                    $meta_key = $args[3];
                }
                $meta->delete_meta($object_id, $meta_key);
                break;
            default:
                WP_CLI::error('Unknown operation: ' . $operation);
        }
    }

    public function clean($args, $assoc_args)
    {
        $cleaner = new Usctdp_Clean();
        $target = "";
        if ($args && count($args) > 0) {
            $target = $args[0];
        } else {
            WP_CLI::error('Target not provided (one of all, classes, people)');
            return;
        }
        $cleaner->clean($target);
    }

    public function clean_products($args, $assoc_args)
    {
        $cleaner = new Usctdp_Clean_Products();
        $cleaner->clean_products();
    }
}

// Register the command with WP-CLI
WP_CLI::add_command('usctdp', 'Usctdp_Cli_Command');
