<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Transaction_Schema extends Schema
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

        'family_id' => [
            'name'       => 'family_id',
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
            'default'    => 0
        ],

        'kind' => [
            'name'       => 'kind',
            'type'       => 'tinyint',
            'unsigned'   => true,
            'default'    => 0
        ],

        'amount' => [
            'name'       => 'amount',
            'type'       => 'int',
            'unsigned'   => false,
            'default'    => 0
        ],

        'method' => [
            'name'       => 'method',
            'type'       => 'tinytext',
            'default'    => 0
        ],

        'notes' => [
            'name'       => 'notes',
            'type'       => 'text',
            'default' => '',
        ],
    ];
}
