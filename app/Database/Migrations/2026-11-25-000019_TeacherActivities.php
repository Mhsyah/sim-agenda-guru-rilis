<?php

namespace App\Database\Migrations;

use App\Libraries\SchemaRepair;
use CodeIgniter\Database\Migration;

/**
 * Kegiatan guru di luar KBM (rapat, upacara, piket, pelatihan, kepanitiaan, …) dengan lampiran bukti opsional.
 * Dibuat tanpa foreign key agar diterima semua server hosting; galat pembuatan tabel tidak lagi diam.
 */
class TeacherActivities extends Migration
{
    public function up()
    {
        SchemaRepair::ensureTeacherActivities($this->forge, $this->db);
    }

    public function down()
    {
        $this->forge->dropTable('teacher_activities', true);
    }
}
