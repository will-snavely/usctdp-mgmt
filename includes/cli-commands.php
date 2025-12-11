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
            "includes/cli/class-usctdp-import-session-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-people-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-registration-generator.php";
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
        if (isset($args[0])) {
            $count = intval($args[0]);
        }
        $generator->generate_random($count);
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
