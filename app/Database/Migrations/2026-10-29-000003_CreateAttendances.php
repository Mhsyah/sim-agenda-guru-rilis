<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kehadiran siswa per pertemuan (terhubung ke agenda/jurnal).
 * Status: H = Hadir, S = Sakit, I = Izin, A = Alpa.
 */
class CreateAttendances extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'teaching_agenda_id' => ['type' => 'INT', 'constraint' => 11],
            'student_id'         => ['type' => 'INT', 'constraint' => 11],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 1, 'default' => 'H'],
            'notes'              => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['teaching_agenda_id', 'student_id']);
        $this->forge->addKey('student_id');
        $this->forge->addForeignKey('teaching_agenda_id', 'teaching_agendas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('attendances', true);
    }

    public function down()
    {
        $this->forge->dropTable('attendances', true, true);
    }
}
