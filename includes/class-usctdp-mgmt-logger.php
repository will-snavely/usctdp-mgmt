<?php
class Usctdp_Mgmt_Logger
{
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getLogger()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone()
    {
    }

    public function log_critical($message)
    {
        error_log($message);
    }

    public function log_error($message)
    {
        error_log($message);
    }

    public function log_warning($message)
    {
        error_log($message);
    }

    public function log_info($message)
    {
        error_log($message);
    }

    public function log_debug($message)
    {
        error_log($message);
    }
}
