<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Session_Schema extends Schema
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
        'title' => [
            'name'       => 'title',
            'type'       => 'tinytext',
        ],
        'search_term' => [
            'name'     => 'search_term',
            'type'     => 'tinytext',
            'index'    => true,
        ],
        'is_active' => [
            'name'       => 'is_active',
            'type'       => 'bool',
            'index'      => true
        ],
        'start_date' => [
            'name'       => 'start_date',
            'type'       => 'date',
            'index'      => true
        ],
        'end_date' => [
            'name'       => 'end_date',
            'type'       => 'date',
        ],
        'num_weeks' => [
            'name'       => 'num_weeks',
            'type'       => 'tinyint',
            'unsigned'   => true,
        ],
        'category' => [
            'name'       => 'category',
            'type'       => 'tinyint',
            'unsigned'   => true,
        ],
    ];
}
