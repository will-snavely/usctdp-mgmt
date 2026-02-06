<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Tournament_Schema extends Schema
{
    public $columns = [
        'activity_id' => [
            'name'     => 'id',
            'type'     => 'bigint',
            'length'   => '20',
            'unsigned' => true,
            'primary'  => true,
            'sortable' => true,
        ],
        'start_date' => [
            'name'       => 'start_date',
            'type'       => 'date',
        ],
        'registration_deadline' => [
            'name'       => 'registration_deadline',
            'type'       => 'date',
        ],
        'capacity' => [
            'name'       => 'capacity',
            'type'       => 'smallint',
            'unsigned'   => true,
        ],
        'days' => [
            'name'       => 'days',
            'type'       => 'json',
        ],
    ];
}
