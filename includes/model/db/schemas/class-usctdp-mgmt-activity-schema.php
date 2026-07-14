<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Activity_Schema extends Schema
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
        ],
        'type' => [
            'name' => 'type',
            'type' => 'varchar',
            'length' => '50',
        ],
        'title' => [
            'name' => 'title',
            'type' => 'tinytext',
        ],
        'level' => [
            'name' => 'level',
            'type' => 'tinytext',
        ],
        'capacity' => [
            'name' => 'capacity',
            'type' => 'smallint',
            'unsigned' => true,
        ],
        'search_term' => [
            'name' => 'search_term',
            'type' => 'tinytext',
            'index' => true,
        ],
        'meta' => [
            'name' => 'meta',
            'type' => 'json',
            'default' => '{}',
        ],
        'session_id' => [
            'name' => 'session_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'product_id' => [
            'name' => 'product_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
        'primary_sort_order' => [
            'name' => 'primary_sort_order',
            'type' => 'smallint',
            'unsigned' => true,
        ],
        'secondary_sort_order' => [
            'name' => 'secondary_sort_order',
            'type' => 'smallint',
            'unsigned' => true,
        ],
    ];
}
