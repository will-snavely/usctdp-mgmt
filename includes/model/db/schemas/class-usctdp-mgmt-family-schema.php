<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Family_Schema extends Schema
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
        'user_id' => [
            'name'       => 'user_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
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
        'last' => [
            'name'       => 'last',
            'type'       => 'tinytext',
        ],
        'address' => [
            'name'       => 'address',
            'type'       => 'tinytext',
        ],
        'city' => [
            'name'       => 'city',
            'type'       => 'tinytext',
        ],
        'state' => [
            'name'       => 'state',
            'type'       => 'tinytext',
        ],
        'zip' => [
            'name'       => 'zip',
            'type'       => 'tinytext',
        ],
        'phone_numbers' => [
            'name'       => 'phone_numbers',
            'type'       => 'json',
        ],
        'email' => [
            'name'       => 'email',
            'type'       => 'tinytext',
        ],
        'notes' => [
            'name'       => 'notes',
            'type'       => 'text',
        ],
        'last_modified' => [
            'name'       => 'last_modified',
            'type'       => 'datetime',
        ],
        'last_modified_by' => [
            'name'       => 'last_modified_by',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
        ],
    ];
}
