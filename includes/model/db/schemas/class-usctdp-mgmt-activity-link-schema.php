<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Activity_Link_Schema extends Schema
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

        'activity_id' => [
            'name'       => 'activity_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0,
        ],

        'session_id' => [
            'name'       => 'session_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0
        ],

        'clinic_id' => [
            'name'       => 'clinic_id',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0
        ]
    ];
}
