<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Student_Schema extends Schema
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
            'default' => 0
        ],

        'family_id' => [
            'name'       => 'family_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
            'default' => 0,
        ],

        'title' => [
            'name'       => 'title',
            'type'       => 'tinytext',
            'index'      => true
        ],

        'first' => [
            'name'       => 'first',
            'type'       => 'tinytext',
        ],

        'last' => [
            'name'       => 'last',
            'type'       => 'tinytext',
        ],

        'birth_date' => [
            'name'       => 'birth_date',
            'type'       => 'date',
        ],

        'level' => [
            'name'       => 'level',
            'type'       => 'tinytext',
        ],
    ];
}
