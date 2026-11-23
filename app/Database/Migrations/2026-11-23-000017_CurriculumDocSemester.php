<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Semester pada arsip dokumen CP/TP/ATP/KKTP (NULL = kedua semester). */
class CurriculumDocSemester extends Migration
{
    public function up()
    {
        $this->forge->addColumn('curriculum_documents', ['semester_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]]);
    }

    public function down()
    {
        $this->forge->dropColumn('curriculum_documents', 'semester_id');
    }
}
