<?php

use BerlinDB\Database\Query;

if (! defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Import_Pending_Query extends Query
{
    protected $table_name = 'usctdp_import_pending';
    protected $table_alias = 'ustip';
    protected $table_schema = 'Usctdp_Mgmt_Import_Pending_Schema';
    protected $item_name = 'import_pending';
    protected $item_name_plural = 'import_pendings';
    protected $item_shape = 'Usctdp_Mgmt_Import_Pending_Row';
}
