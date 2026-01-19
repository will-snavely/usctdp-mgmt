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

        'created_by' => [
            'name'       => 'created_by',
            'type'       => 'bigint',
            'unsigned'   => true,
            'default'    => 0
        ],

        'created_at' => [
            'name'       => 'created_at',
            'type'       => 'datetime',
        ],

        'kind' => [
            'name'       => 'kind',
            'type'       => 'tinyint',
            'unsigned'   => true,
            'default'    => 0
        ],

        'status' => [
            'name'       => 'status',
            'type'       => 'tinyint',
            'unsigned'   => true,
            'default'    => 0
        ],

        'method' => [
            'name'       => 'method',
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

        'reference_id' => [
            'name'       => 'reference_id',
            'type'       => 'bigint',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0
        ],

        'reference_string' => [
            'name'       => 'reference_string',
            'type'       => 'tinytext',
            'index'      => true,
            'default'    => ''
        ],

        'notes' => [
            'name'       => 'notes',
            'type'       => 'text',
            'default' => '',
        ],
    ];
}
