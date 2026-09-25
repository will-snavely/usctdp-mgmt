<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Camp_Schema extends Schema
{
    public $columns = [
        'id' => [
            'name' => 'id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'primary' => true,
            'sortable' => true,
        ],
        'schedule' => [
            'name' => 'schedule',
            'type' => 'json',
            'default' => '{}',
        ],
    ];
}
