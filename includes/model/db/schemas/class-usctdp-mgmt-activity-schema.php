<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Activity_Schema extends Schema
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
        ],
        'type' => [
            'name'       => 'type',
            'type'       => 'tinyint',
        ],
        'title' => [
            'name'       => 'title',
            'type'       => 'tinytext',
        ],
        'search_term' => [
            'name'     => 'search_term',
            'type'     => 'tinytext',
            'index'    => true,
        ],
        'session_id' => [
            'name'       => 'session_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
        ],
        'product_id' => [
            'name'       => 'product_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
        ]
    ];
}
