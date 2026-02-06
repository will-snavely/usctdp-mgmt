<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Pricing_Query extends Query
{
    protected $table_name = 'usctdp_pricing';
    protected $table_alias = 'upric';
    protected $table_schema = 'Usctdp_Mgmt_Pricing_Schema';
    protected $item_name = 'pricing';
    protected $item_name_plural = 'pricingss';
    protected $item_shape = 'Usctdp_Mgmt_Pricing_Row';
}
