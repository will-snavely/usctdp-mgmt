<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Roster_Group_Session_Schema extends Schema
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

        'roster_group_id' => [
            'name' => 'roster_group_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
            'default' => 0,
        ],

        // A session belongs to at most one roster group - enforced with a
        // UNIQUE key in the table schema, not just here.
        'session_id' => [
            'name' => 'session_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
            'default' => 0,
        ],

        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
            'allow_null' => true
        ]
    ];
}
