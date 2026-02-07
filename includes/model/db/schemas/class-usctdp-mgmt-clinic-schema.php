<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Clinic_Schema extends Schema
{
    public $columns = [
        'activity_id' => [
            'name' => 'activity_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'primary' => true,
            'sortable' => true,
        ],
        'day_of_week' => [
            'name' => 'day_of_week',
            'type' => 'tinyint',
            'unsigned' => true,
        ],
        'start_time' => [
            'name' => 'start_time',
            'type' => 'time',
        ],
        'end_time' => [
            'name' => 'end_time',
            'type' => 'time',
        ],
        'capacity' => [
            'name' => 'capacity',
            'type' => 'smallint',
            'unsigned' => true,
        ],
        'level' => [
            'name' => 'level',
            'type' => 'tinytext',
        ],
        'notes' => [
            'name' => 'notes',
            'type' => 'text',
        ],
    ];
}
