<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * - Blanko sampul Buku Agenda Guru: default sekolah (school_profiles) dan milik guru (users).
 * - TP dilengkapi urutan ATP, tingkat kelas, dan KKTP (deskripsi kriteria + interval nilai).
 * - curriculum_documents: arsip dokumen CP/TP/ATP/KKTP (PDF/DOCX) per mapel & fase.
 */
class CoverAndCurriculum extends Migration
{
    public function up()
    {
        $cover = [
            'cover_blank'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'cover_blank_opts' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ];
        $this->forge->addColumn('school_profiles', $cover);
        $this->forge->addColumn('users', $cover);

        $this->forge->addColumn('learning_objectives', [
            'atp_order'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'grade_level'    => ['type' => 'SMALLINT', 'null' => true],
            'kktp_criteria'  => ['type' => 'TEXT', 'null' => true],
            'kktp_intervals' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'academic_year_id' => ['type' => 'INT', 'constraint' => 11],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'subject_id'       => ['type' => 'INT', 'constraint' => 11],
            'phase'            => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'doc_type'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'file_name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'original_name'    => ['type' => 'VARCHAR', 'constraint' => 180],
            'file_size'        => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['academic_year_id', 'subject_id']);
        $this->forge->addForeignKey('academic_year_id', 'academic_years', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('curriculum_documents', true);
    }

    public function down()
    {
        $this->forge->dropTable('curriculum_documents', true);
        $this->forge->dropColumn('learning_objectives', ['atp_order', 'grade_level', 'kktp_criteria', 'kktp_intervals']);
        $this->forge->dropColumn('users', ['cover_blank', 'cover_blank_opts']);
        $this->forge->dropColumn('school_profiles', ['cover_blank', 'cover_blank_opts']);
    }
}
