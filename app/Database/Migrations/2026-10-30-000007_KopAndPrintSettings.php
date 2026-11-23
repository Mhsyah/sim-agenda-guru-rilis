<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pengaturan kop surat & cetak dokumen (JSON), logo instansi/pemerintah (kiri kop),
 * dan gambar tanda tangan kepala sekolah.
 */
class KopAndPrintSettings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('school_profiles', [
            'kop_settings'        => ['type' => 'TEXT', 'null' => true],
            'gov_logo'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'principal_signature' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('school_profiles', ['kop_settings', 'gov_logo', 'principal_signature']);
    }
}
