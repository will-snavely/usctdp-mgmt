<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Registration_Camp_Day_Schema extends Schema
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

        // A registration can cover many days, but not the same day twice -
        // enforced by the UNIQUE key on (registration_id, activity_date) in
        // the table schema, not here.
        'registration_id' => [
            'name' => 'registration_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],

        'activity_date' => [
            'name' => 'activity_date',
            'type' => 'date',
            'index' => true,
            'sortable' => true,
        ],
    ];
}
