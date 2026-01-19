<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Transaction_Link_Schema extends Schema
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

        'transaction_id' => [
            'name'       => 'transaction_id',
            'type'       => 'bigint',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0,
        ],

        'registration_id' => [
            'name'       => 'registration_id',
            'type'       => 'bigint',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0,
        ]
    ];
}
