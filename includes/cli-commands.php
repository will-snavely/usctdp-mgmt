<?php

// Check to ensure WordPress functions are available and prevent direct access.
if (! defined('ABSPATH')) {
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
            "includes/cli/class-usctdp-import-clinic-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-people-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-registration-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-roster-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-create-products.php";
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
        $include = [];
        if ($args && count($args) > 0) {
            $target = $args[0];
            if ($target === 'all') {
                $include = ['sessions', 'classes', 'clinics'];
            } else {
                $include = [$target];
            }
        } else {
            WP_CLI::error('Target not provided (one of all, sessions, classes, clinics)');
            return;
        }
        $generator = new Usctdp_Roster_Generator();
        $generator->create_rosters($include);
    }


    public function create_products($args, $assoc_args)
    {
        $generator = new Usctdp_Create_Products();
        $generator->create();
    }

    public function import_clinics($args, $assoc_args)
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
            if($args[1] === "true") {
                $skip_download = true;
            }
        }
        $generator = new Usctdp_Import_Clinic_Data();
        $generator->import($file_path, $skip_download);
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
}

// Register the command with WP-CLI
WP_CLI::add_command('usctdp', 'Usctdp_Cli_Command');
