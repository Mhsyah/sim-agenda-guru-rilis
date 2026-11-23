<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Semester pada CP & TP: semester_id (Ganjil/Genap pada T.P. yang sama); NULL = berlaku untuk kedua semester.
 */
class CurriculumSemester extends Migration
{
    public function up()
    {
        $col = ['semester_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]];
        $this->forge->addColumn('learning_outcomes', $col);
        $this->forge->addColumn('learning_objectives', $col);
    }

    public function down()
    {
        $this->forge->dropColumn('learning_objectives', 'semester_id');
        $this->forge->dropColumn('learning_outcomes', 'semester_id');
    }
}
