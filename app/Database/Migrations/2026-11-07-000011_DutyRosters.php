<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Guru piket per hari untuk sesi Pagi / Siang pada satu tahun pelajaran. */
class DutyRosters extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'academic_year_id' => ['type' => 'INT', 'constraint' => 11],
            'shift'            => ['type' => 'VARCHAR', 'constraint' => 10],
            'day_of_week'      => ['type' => 'SMALLINT'],
            'user_id'          => ['type' => 'INT', 'constraint' => 11],
            'sort'             => ['type' => 'SMALLINT', 'default' => 1],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['academic_year_id', 'shift', 'day_of_week']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('duty_rosters', true);
    }

    public function down()
    {
        $this->forge->dropTable('duty_rosters', true);
    }
}
