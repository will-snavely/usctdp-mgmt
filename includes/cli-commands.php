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
            "includes/cli/class-usctdp-random-data-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-session-data.php";
    }

    public function gen_random($args, $assoc_args)
    {
        $generator = new Usctdp_Random_Data_Generator();
        $generator->generate_random(10, 15, 8, 50);
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
        $cleaner->clean();
    }
}

// Register the command with WP-CLI
WP_CLI::add_command('usctdp', 'Usctdp_Cli_Command');
