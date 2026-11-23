<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Data referensi wajib: jenis asesmen TGS/UH/ATS/AAS. Aman dijalankan berulang. */
class ReferenceSeeder extends Seeder
{
    public function run()
    {
        $types = [
            ['code' => 'TGS', 'name' => 'Tugas Harian', 'sort_order' => 0],
            ['code' => 'UH', 'name' => 'Ulangan Harian', 'sort_order' => 1],
            ['code' => 'ATS', 'name' => 'Asesmen Tengah Semester', 'sort_order' => 2],
            ['code' => 'AAS', 'name' => 'Asesmen Akhir Semester', 'sort_order' => 3],
        ];
        foreach ($types as $t) {
            if (! $this->db->table('assessment_types')->where('code', $t['code'])->countAllResults()) {
                $this->db->table('assessment_types')->insert($t + ['is_active' => 1]);
            }
        }
    }
}
