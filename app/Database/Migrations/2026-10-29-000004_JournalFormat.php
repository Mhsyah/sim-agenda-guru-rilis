<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penyesuaian dengan format "Jurnal Agenda Guru":
 * - Agenda: jam ke, pertemuan ke / banyak pertemuan, selesai/belum + alasan, jenis tes, keterangan (KET).
 * - Jadwal: jam ke.
 * - Profil: data kop sekolah & kepala sekolah.
 * - Kesan-kesan Kepala Sekolah.
 */
class JournalFormat extends Migration
{
    public function up()
    {
        $this->forge->addColumn('teaching_agendas', [
            'lesson_hours'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'meeting_no'        => ['type' => 'SMALLINT', 'null' => true],
            'meeting_total'     => ['type' => 'SMALLINT', 'null' => true],
            'completion'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'selesai'],
            'incomplete_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'assessment_kind'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'remarks'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addColumn('teaching_schedules', [
            'lesson_hours' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ]);
        $this->forge->addColumn('school_profiles', [
            'header_line'    => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'npsn'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'school_city'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'school_phone'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'school_email'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'school_website' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'principal_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'principal_nip'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
        ]);

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'academic_year_id' => ['type' => 'INT', 'constraint' => 11],
            'review_date'      => ['type' => 'DATE'],
            'reviewer_name'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'notes'            => ['type' => 'TEXT'],
            'is_signed'        => ['type' => 'SMALLINT', 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('principal_reviews', true);
    }

    public function down()
    {
        $this->forge->dropTable('principal_reviews', true, true);
        $this->forge->dropColumn('teaching_agendas', ['lesson_hours', 'meeting_no', 'meeting_total', 'completion', 'incomplete_reason', 'assessment_kind', 'remarks']);
        $this->forge->dropColumn('teaching_schedules', 'lesson_hours');
        $this->forge->dropColumn('school_profiles', ['header_line', 'npsn', 'school_city', 'school_phone', 'school_email', 'school_website', 'principal_name', 'principal_nip']);
    }
}
