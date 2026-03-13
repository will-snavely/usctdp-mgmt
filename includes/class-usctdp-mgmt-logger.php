<?php

enum Usctdp_Log_Level: int
{
    case Debug = 1;
    case Info = 2;
    case Warning = 3;
    case Error = 4;
    case Critical = 5;
}

class Usctdp_Mgmt_Logger
{
    private $loggers;

    function __construct()
    {
        $this->loggers = [
            Usctdp_Log_Level::Debug->value => $this->log_debug(...),
            Usctdp_Log_Level::Info->value => $this->log_info(...),
            Usctdp_Log_Level::Warning->value => $this->log_warning(...),
            Usctdp_Log_Level::Error->value => $this->log_error(...),
            Usctdp_Log_Level::Critical->value => $this->log_critical(...),
        ];
    }

    private function get_logger($level) {
        return $this->loggers[$level->value] ?? $this->log_info(...);
    }

    public function log_exception($message, $e, $level=Usctdp_Log_Level::Error)
    {
        $logger = $this->get_logger($level);
        $logger($message . ": " . $e->getMessage());
        $logger('Trace: ' . $e->getTraceAsString());
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
