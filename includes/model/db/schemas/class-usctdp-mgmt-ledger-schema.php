<?php

use BerlinDB\Database\Schema;

class Usctdp_Mgmt_Payment_Schema extends Schema
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
        'transaction_id' => [
            'name' => 'transaction_id',
            'type' => 'varchar',
            'length' => 50,
            'index' => true,
        ],
        'family_id' => [
            'name' => 'family_id',
            'type' => 'bigint',
            'unsigned' => true,
            'index' => true,
        ],
        'student_id' => [
            'name' => 'student_id',
            'type' => 'bigint',
            'unsigned' => true,
            'index' => true,
        ],
        'account_type' => [
            'name' => 'account_type',
            'type' => 'varchar',
            'length' => 50,
            'index' => true,
        ],
        'payment_method' => [
            'name' => 'payment_method',
            'type' => 'varchar',
            'length' => 20,
            'default' => ''
        ],
        'reference_id' => [
            'name' => 'reference_id',
            'type' => 'varchar',
            'length' => 100,
            'default' => ''
        ],
        'order_id' => [
            'name' => 'order_id',
            'type' => 'bigint',
            'unsigned' => true,
            'index' => true,
            'default' => 0,
        ],
        'debit' => [
            'name' => 'debit',
            'type' => 'decimal',
            'length' => '10,2',
            'default' => '0.00',
            'unsigned' => false,
        ],
        'credit' => [
            'name' => 'credit',
            'type' => 'decimal',
            'length' => '10,2',
            'default' => '0.00',
            'unsigned' => false,
        ],
        'description' => [
            'name' => 'description',
            'type' => 'text',
        ],
        'created_by' => [
            'name' => 'created_by',
            'type' => 'bigint',
            'unsigned' => true,
            'default' => 0
        ],
        'created_at' => [
            'name' => 'created_at',
            'type' => 'datetime',
        ],
   ];
}
