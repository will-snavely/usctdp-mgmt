<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Waitlist_Query extends Query
{
    protected $table_name = 'usctdp_waitlist';
    protected $table_alias = 'uwait';
    protected $table_schema = 'Usctdp_Mgmt_Waitlist_Schema';
    protected $item_name = 'waitlist';
    protected $item_name_plural = 'waitlists';
    protected $item_shape = 'Usctdp_Mgmt_Waitlist_Row';
}
