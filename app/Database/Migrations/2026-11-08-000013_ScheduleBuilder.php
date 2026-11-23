<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penyusun jadwal:
 * - subject_loads: beban JP per minggu tiap mapel per tingkat kelas;
 * - time_slots: struktur hari (jam pelajaran & kegiatan non-mengajar seperti Pembiasaan, Istirahat, MBG) per sesi Pagi/Siang.
 */
class ScheduleBuilder extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'academic_year_id' => ['type' => 'INT', 'constraint' => 11],
            'subject_id'       => ['type' => 'INT', 'constraint' => 11],
            'grade_level'      => ['type' => 'SMALLINT'],
            'jp'               => ['type' => 'SMALLINT', 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['academic_year_id', 'subject_id', 'grade_level']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('subject_loads', true);

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'academic_year_id' => ['type' => 'INT', 'constraint' => 11],
            'shift'            => ['type' => 'VARCHAR', 'constraint' => 10],
            'day_of_week'      => ['type' => 'SMALLINT'],
            'start_time'       => ['type' => 'TIME'],
            'end_time'         => ['type' => 'TIME'],
            'kind'             => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pelajaran'],
            'lesson_no'        => ['type' => 'SMALLINT', 'null' => true],
            'label'            => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['academic_year_id', 'shift', 'day_of_week']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('time_slots', true);
    }

    public function down()
    {
        $this->forge->dropTable('time_slots', true);
        $this->forge->dropTable('subject_loads', true);
    }
}
