<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Status kepegawaian akun (aktif / nonaktif / mutasi / keluar) + wajib ganti kata sandi setelah direset admin.
 * Kolom is_active tetap dipakai untuk login (1 hanya bila status "aktif").
 */
class UserEmploymentStatus extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'employment_status'    => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'aktif'],
            'status_date'          => ['type' => 'DATE', 'null' => true],
            'status_note'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'must_change_password' => ['type' => 'SMALLINT', 'null' => false, 'default' => 0],
        ]);
        // Akun yang sudah nonaktif → status "nonaktif"
        $this->db->table('users')->where('is_active', 0)->update(['employment_status' => 'nonaktif']);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['employment_status', 'status_date', 'status_note', 'must_change_password']);
    }
}
