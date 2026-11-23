<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Lampiran materi pada agenda + foto profil pengguna (file disimpan di writable/uploads). */
class MaterialsAndPhotos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('teaching_agendas', [
            'material_file' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'material_name' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'material_size' => ['type' => 'INT', 'null' => true],
        ]);
        $this->forge->addColumn('users', [
            'photo' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('teaching_agendas', ['material_file', 'material_name', 'material_size']);
        $this->forge->dropColumn('users', 'photo');
    }
}
