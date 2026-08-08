<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Reservation_Group_Schema extends Schema
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
        'capacity' => [
            'name' => 'capacity',
            'type' => 'smallint',
            'unsigned' => true,
            'default' => 0,
        ],
        'name' => [
            'name' => 'name',
            'type' => 'tinytext',
            'allow_null' => true,
            'default' => null,
        ],
        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
            'allow_null' => true,
        ],
        'updated_at' => [
            'name' => 'updated_at',
            'type' => 'datetime',
            'allow_null' => true,
        ],
    ];
}
