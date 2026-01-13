<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Roster_Link_Schema extends Schema
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

        'entity_id' => [
            'name'       => 'entity_id',
            'type'       => 'int',
            'unsigned'   => true,
            'index'      => true,
            'default' => 0,
        ],

        'drive_id' => [
            'name'       => 'drive_id',
            'type'       => 'tinytext',
            'default' => ''
        ]
    ];
}
