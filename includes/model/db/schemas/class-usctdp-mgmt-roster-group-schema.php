<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Roster_Group_Schema extends Schema
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

        // Explicit override for the roster's display name. NULL means "use
        // the default" (the primary member session's title) - see
        // Usctdp_Mgmt_Roster_Group_Query::search_rosters().
        'name' => [
            'name' => 'name',
            'type' => 'tinytext',
            'allow_null' => true
        ],

        'drive_id' => [
            'name' => 'drive_id',
            'type' => 'tinytext',
            'default' => ''
        ],

        'updated_at' => [
            'name' => 'updated_at',
            'type' => 'datetime',
            'allow_null' => true
        ],

        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
            'allow_null' => true
        ]
    ];
}
