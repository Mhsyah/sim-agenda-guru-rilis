<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tugas Harian: jenis asesmen "TGS", penanda siswa tidak mengumpulkan, dan aturan per asesmen
 * (tidak mengumpulkan dihitung 0 / tidak dihitung).
 */
class DailyTasks extends Migration
{
    public function up()
    {
        if (! $this->db->table('assessment_types')->where('code', 'TGS')->countAllResults()) {
            $this->db->table('assessment_types')->insert(['code' => 'TGS', 'name' => 'Tugas Harian', 'is_active' => 1, 'sort_order' => 0]);
        }
        $this->forge->addColumn('assessment_scores', ['is_missing' => ['type' => 'SMALLINT', 'default' => 0]]);
        $this->forge->addColumn('assessments', ['missing_policy' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'nol']]);
    }

    public function down()
    {
        $this->forge->dropColumn('assessments', 'missing_policy');
        $this->forge->dropColumn('assessment_scores', 'is_missing');
        $this->db->table('assessment_types')->where('code', 'TGS')->delete();
    }
}
