<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Product_Link_Schema extends Schema
{
    public $columns = [
        'id' => [
            'name'     => 'id',
            'type'     => 'bigint',
            'length'   => '20',
            'unsigned' => true,
            'extra'    => 'auto_increment',
            'primary'  => true,
            'sortable' => true,
            'default'  => 0
        ],

        'clinic_id' => [
            'name'       => 'activity_id',
            'type'       => 'bigint',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0,
        ],

        'product_id' => [
            'name'       => 'product_id',
            'type'       => 'bigint',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0,
        ],
    ];
}
