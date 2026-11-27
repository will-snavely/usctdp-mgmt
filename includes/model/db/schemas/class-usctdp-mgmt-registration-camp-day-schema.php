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

        // A registration can cover many slots, but not the same slot twice
        // - enforced by the UNIQUE key on (registration_id, activity_date,
        // start_time) in the table schema, not here.
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

        // Which of that date's schedule slots this is - a camp can run the
        // same weekday more than once (e.g. Thursday 11 AM and 6 PM).
        // Nullable only for legacy rows the 1.1.0 migration couldn't
        // resolve; every new row has one.
        'start_time' => [
            'name' => 'start_time',
            'type' => 'time',
            'allow_null' => true,
            'sortable' => true,
        ],
    ];
}
