<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Nama file logo sekolah (disimpan di writable/uploads/logo). */
class AddSchoolLogo extends Migration
{
    public function up()
    {
        $this->forge->addColumn('school_profiles', [
            'school_logo' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('school_profiles', 'school_logo');
    }
}
