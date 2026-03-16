<?php
class USCTDP_Mgmt_Ledger
{
    public function __construct()
    {
    }

    public function get_ledger_data($args = [])
    {
        $ledger_query = new USCTDP_Mgmt_Ledger_Query();
        return $ledger_query->get_ledger_data($args);
    }

    public function get_ledger_events($args = [])
    {
        $ledger_query = new USCTDP_Mgmt_Ledger_Query();
        return $ledger_query->get_balance_data($args);
    }
}