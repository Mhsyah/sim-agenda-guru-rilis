<?php

namespace App\Database\Migrations;

use App\Libraries\SchemaRepair;
use CodeIgniter\Database\Migration;

/**
 * Perbaikan: pastikan tabel teacher_activities benar-benar ada.
 * Di sebagian hosting migrasi sebelumnya tercatat selesai padahal tabelnya tidak terbuat.
 * Tabel dibuat tanpa foreign key; bila server tetap menolak, galat aslinya ditampilkan.
 */
class EnsureTeacherActivities extends Migration
{
    public function up()
    {
        SchemaRepair::ensureTeacherActivities($this->forge, $this->db);
    }

    public function down()
    {
        // Tabel dikelola migrasi TeacherActivities; tidak dihapus di sini.
    }
}
