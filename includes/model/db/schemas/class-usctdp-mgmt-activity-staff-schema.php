<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Activity_Staff_Schema extends Schema
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

        'activity_id' => [
            'name' => 'activity_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],

        // A staff member may be assigned to more than one activity, and an
        // activity may have more than one staff member. Duplicate assignment
        // of the *same* pair is prevented by a UNIQUE key on (activity_id,
        // staff_id) in the table schema, not here.
        'staff_id' => [
            'name' => 'staff_id',
            'type' => 'bigint',
            'length' => '20',
            'unsigned' => true,
            'index' => true,
        ],
    ];
}
