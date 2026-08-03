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

    /**
     * Levels at/above this get forwarded to Sentry (if configured) as well
     * as error_log(). Debug/Info stay local-only - Sentry's event quota is
     * too valuable to spend on routine noise.
     */
    const SENTRY_MIN_LEVEL = Usctdp_Log_Level::Warning;

    /**
     * Null until the first Usctdp_Mgmt_Logger is constructed, then locked to
     * whether Sentry actually has a DSN to report to. Distinct from "did we
     * attempt init" - \Sentry\init() itself only needs to run once per
     * request, but every call site needs to know whether Sentry is actually
     * live, not just whether we've already checked.
     */
    private static $sentry_active = null;

    function __construct()
    {
        $this->loggers = [
            Usctdp_Log_Level::Debug->value => $this->log_debug(...),
            Usctdp_Log_Level::Info->value => $this->log_info(...),
            Usctdp_Log_Level::Warning->value => $this->log_warning(...),
            Usctdp_Log_Level::Error->value => $this->log_error(...),
            Usctdp_Log_Level::Critical->value => $this->log_critical(...),
        ];

        $this->maybe_init_sentry();
    }

    /**
     * Sentry activates purely on SENTRY_DSN being set (same convention as
     * SMTP_HOST for WP Mail SMTP) - there's no separate on/off switch, so an
     * environment that shouldn't report simply doesn't set the DSN in its
     * .env.
     */
    private function maybe_init_sentry()
    {
        if (self::$sentry_active !== null) {
            return;
        }

        $dsn = getenv('SENTRY_DSN');
        self::$sentry_active = !empty($dsn);

        if (self::$sentry_active) {
            \Sentry\init([
                'dsn' => $dsn,
                'enable_logs' => true,
                'environment' => defined('WP_ENV') ? WP_ENV : 'production',
            ]);
        }
    }

    private function get_logger($level)
    {
        return $this->loggers[$level->value] ?? $this->log_info(...);
    }

    private function sentry_level_for($level)
    {
        return match ($level) {
            Usctdp_Log_Level::Critical => \Sentry\Severity::fatal(),
            Usctdp_Log_Level::Error => \Sentry\Severity::error(),
            Usctdp_Log_Level::Warning => \Sentry\Severity::warning(),
            default => \Sentry\Severity::info(),
        };
    }

    private function report_to_sentry($message, $level)
    {
        if (!self::$sentry_active || $level->value < self::SENTRY_MIN_LEVEL->value) {
            return;
        }

        \Sentry\captureMessage($message, $this->sentry_level_for($level));
    }

    public function log_exception($message, $e, $level = Usctdp_Log_Level::Error)
    {
        $logger = $this->get_logger($level);
        $logger($message . ": " . $e->getMessage());
        $logger('Trace: ' . $e->getTraceAsString());

        if (self::$sentry_active && $level->value >= self::SENTRY_MIN_LEVEL->value) {
            \Sentry\withScope(function ($scope) use ($message, $level, $e) {
                $scope->setLevel($this->sentry_level_for($level));
                $scope->setContext('usctdp_mgmt', ['message' => $message]);
                \Sentry\captureException($e);
            });
        }
    }

    public function log_critical($message)
    {
        error_log($message);
        $this->report_to_sentry($message, Usctdp_Log_Level::Critical);
    }

    public function log_error($message)
    {
        error_log($message);
        $this->report_to_sentry($message, Usctdp_Log_Level::Error);
    }

    public function log_warning($message)
    {
        error_log($message);
        $this->report_to_sentry($message, Usctdp_Log_Level::Warning);
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
