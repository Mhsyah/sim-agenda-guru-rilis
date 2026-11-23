<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Kode guru (nomor pada jadwal pelajaran sekolah, mis. "15"). */
class TeacherCode extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', ['teacher_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true]]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'teacher_code');
    }
}
