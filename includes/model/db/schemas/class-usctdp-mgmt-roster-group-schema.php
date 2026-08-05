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

        // The roster's display name. Normally set at creation time to the
        // founding session's title (see
        // Usctdp_Mgmt_Roster_Group_Query::get_or_create_for_session()), but a
        // blank/NULL name still falls back to the primary member session's
        // title at read time - see search_rosters().
        //
        // 'default' must be NULL explicitly: BerlinDB's Column defaults an
        // unspecified default to '', which add_item() would then write into
        // this column instead of leaving it NULL.
        'name' => [
            'name' => 'name',
            'type' => 'tinytext',
            'allow_null' => true,
            'default' => null
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
