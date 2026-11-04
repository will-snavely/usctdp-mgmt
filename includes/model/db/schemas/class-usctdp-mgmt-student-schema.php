<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Student_Schema extends Schema
{
    public $columns = [
        'id' => [
            'name' => 'id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'extra' => 'auto_increment',
            'primary' => true,
            'sortable' => true,
            'default' => 0
        ],
        'family_id' => [
            'name' => 'family_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
            'default' => 0,
        ],
        'title' => [
            'name' => 'title',
            'type' => 'tinytext',
        ],
        'search_term' => [
            'name' => 'search_term',
            'type' => 'tinytext',
            'index' => true,
        ],
        'first' => [
            'name' => 'first',
            'type' => 'tinytext',
        ],
        'last' => [
            'name' => 'last',
            'type' => 'tinytext',
        ],
        'birth_date' => [
            'name' => 'birth_date',
            'type' => 'date',
        ],
        'level' => [
            'name' => 'level',
            'type' => 'tinytext',
        ],
        'is_deleted' => [
            'name' => 'is_deleted',
            'type' => 'tinyint',
            'length' => '1',
            'unsigned' => true,
            'default' => 0,
        ],
        'deleted_at' => [
            'name' => 'deleted_at',
            'type' => 'datetime',
            'allow_null' => true,
        ],
        'deleted_by' => [
            'name' => 'deleted_by',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'allow_null' => true,
        ],
    ];
}
