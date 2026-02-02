<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Transaction_Schema extends Schema
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

        'family_id' => [
            'name'       => 'family_id',
            'type'       => 'bigint',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0,
        ],

        'created_by' => [
            'name'       => 'created_by',
            'type'       => 'bigint',
            'unsigned'   => true,
            'default'    => 0
        ],

        'created_at' => [
            'name'       => 'created_at',
            'type'       => 'datetime',
        ],

        'kind' => [
            'name'       => 'kind',
            'type'       => 'tinyint',
            'unsigned'   => true,
            'default'    => 0
        ],

        'method' => [
            'name'       => 'method',
            'type'       => 'tinyint',
            'unsigned'   => true
        ],

        'amount' => [
            'name'       => 'amount',
            'type'       => 'int',
            'unsigned'   => false,
            'default'    => 0
        ],

        'check_number' => [
            'name'       => 'check_number',
            'type'       => 'tinytext',
            'default'    => ''
        ],

        'check_status' => [
            'name'       => 'check_status',
            'type'       => 'tinyint',
            'unsigned'   => true,
            'default'    => 0
        ],

        'check_date_received' => [
            'name'       => 'check_date_received',
            'type'       => 'date'
        ],

        'check_cleared_date' => [
            'name'       => 'check_cleared_date',
            'type'       => 'date'
        ],

        'woocommerce_order_id' => [
            'name'       => 'woocommerce_order_id',
            'type'       => 'bigint',
            'unsigned'   => true,
            'index'      => true,
            'default'    => 0
        ],

        'paypal_transaction_id' => [
            'name'       => 'paypal_transaction_id',
            'type'       => 'tinytext',
            'index'      => true,
            'default'    => ''
        ],

        'history' => [
            'name'       => 'history',
            'type'       => 'json',
            'default'    => '[]'
        ],

        'notes' => [
            'name'       => 'notes',
            'type'       => 'text',
            'default' => '',
        ],
    ];
}
