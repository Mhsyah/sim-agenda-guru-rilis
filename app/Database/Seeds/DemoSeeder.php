<?php

namespace App\Database\Seeds;

use App\Models\AcademicYearModel;
use App\Models\AgendaModel;
use App\Models\AssessmentModel;
use App\Models\CalendarEventModel;
use App\Models\ClassGroupModel;
use App\Models\EnrollmentModel;
use App\Models\GradingConfigItemModel;
use App\Models\GradingConfigModel;
use App\Models\LearningObjectiveModel;
use App\Models\LearningOutcomeModel;
use App\Models\OutcomeObjectiveModel;
use App\Models\ScheduleModel;
use App\Models\ScoreModel;
use App\Models\SemesterModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use CodeIgniter\Database\Seeder;

/**
 * Data contoh fiktif untuk mencoba aplikasi. Nama siswa dibuat acak dan tidak merujuk orang nyata.
 * Tanggal kalender adalah contoh; verifikasi kalender resmi sekolah Anda.
 */
class DemoSeeder extends Seeder
{
    public function run()
    {
        $this->call('ReferenceSeeder');
        mt_srand(2026);

        $y = (int) date('Y') - ((int) date('n') < 7 ? 1 : 0);
        $yearName = $y . '/' . ($y + 1);
        if ((new AcademicYearModel())->where('name', $yearName)->first()) {
            return;
        }
        (new AcademicYearModel())->set('is_active', 0)->where('is_active', 1)->update();
        $yid = (new AcademicYearModel())->insert(['name' => $yearName, 'start_date' => "$y-07-13", 'end_date' => ($y + 1) . '-06-30', 'is_active' => 1]);
        $s1  = (new SemesterModel())->insert(['academic_year_id' => $yid, 'name' => 'Ganjil', 'start_date' => "$y-07-13", 'end_date' => "$y-12-31", 'is_active' => 1]);
        (new SemesterModel())->insert(['academic_year_id' => $yid, 'name' => 'Genap', 'start_date' => ($y + 1) . '-01-01', 'end_date' => ($y + 1) . '-06-30', 'is_active' => 0]);

        $mtk = (new SubjectModel())->where('code', 'MTK')->first()['id'] ?? (new SubjectModel())->insert(['code' => 'MTK', 'name' => 'Matematika', 'phase' => 'D', 'is_active' => 1]);
        $ipa = (new SubjectModel())->where('code', 'IPA')->first()['id'] ?? (new SubjectModel())->insert(['code' => 'IPA', 'name' => 'Ilmu Pengetahuan Alam', 'phase' => 'D', 'is_active' => 1]);

        // Data pembelajaran contoh milik guru pertama; bila belum ada guru, dibuat akun "Guru Contoh"
        // (nonaktif sampai admin mengatur kata sandinya di Data Guru & Pengguna).
        $uid = $this->db->table('users')->where('role', 'teacher')->orderBy('id', 'ASC')->get()->getRowArray()['id'] ?? null;
        if (! $uid) {
            $this->db->table('users')->insert(['name' => 'Guru Contoh, S.Pd.', 'email' => 'guru.contoh@contoh.sch.id', 'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'role' => 'teacher', 'is_active' => 0, 'theme_pref' => 'system', 'created_at' => date('Y-m-d H:i:s')]);
            $uid = (int) $this->db->insertID();
        }

        $classes = [];
        foreach (['VII A' => 7, 'VII B' => 7, 'VIII A' => 8] as $name => $lvl) {
            $classes[$name] = (new ClassGroupModel())->insert(['academic_year_id' => $yid, 'name' => $name, 'grade_level' => $lvl, 'is_active' => 1]);
        }

        $first = ['Adit', 'Bunga', 'Cahya', 'Dimas', 'Eka', 'Fajar', 'Gita', 'Hana', 'Intan', 'Joko', 'Kirana', 'Laras', 'Mega', 'Nanda', 'Oki', 'Putri', 'Raka', 'Sari', 'Tegar', 'Umi', 'Vino', 'Wulan', 'Yoga', 'Zahra'];
        $last  = ['Pratama', 'Lestari', 'Saputra', 'Wijaya', 'Rahmawati', 'Nugroho', 'Anggraini', 'Hidayat', 'Permata', 'Kusuma', 'Ramadhan', 'Safitri'];
        $nis   = 26001;
        $studentsByClass = [];
        foreach ($classes as $cname => $cid) {
            for ($i = 0; $i < 24; $i++) {
                $sid = (new StudentModel())->insert([
                    'nis' => (string) $nis, 'nisn' => (string) (100000000 + $nis * 7),
                    'full_name' => $first[mt_rand(0, count($first) - 1)] . ' ' . $last[mt_rand(0, count($last) - 1)],
                    'gender' => mt_rand(0, 1) ? 'L' : 'P', 'birth_date' => ($y - 13) . '-' . sprintf('%02d-%02d', mt_rand(1, 12), mt_rand(1, 28)), 'status' => 'aktif',
                ]);
                (new EnrollmentModel())->insert(['student_id' => $sid, 'class_group_id' => $cid, 'academic_year_id' => $yid, 'status' => 'aktif']);
                $studentsByClass[$cname][] = $sid;
                $nis++;
            }
        }

        // Kurikulum
        $cp1 = (new LearningOutcomeModel())->insert(['academic_year_id' => $yid, 'subject_id' => $mtk, 'phase' => 'D', 'element_name' => 'Bilangan', 'code' => 'CP-MTK-D-BIL', 'description' => 'Peserta didik dapat membaca, menulis, dan membandingkan bilangan bulat, bilangan rasional dan irasional, serta menerapkan operasi aritmetika pada bilangan.', 'is_active' => 1]);
        $cp2 = (new LearningOutcomeModel())->insert(['academic_year_id' => $yid, 'subject_id' => $mtk, 'phase' => 'D', 'element_name' => 'Aljabar', 'code' => 'CP-MTK-D-ALJ', 'description' => 'Peserta didik dapat mengenali, memprediksi dan menggeneralisasi pola dalam bentuk susunan benda dan bilangan serta menyatakan situasi ke dalam bentuk aljabar.', 'is_active' => 1]);
        $cp3 = (new LearningOutcomeModel())->insert(['academic_year_id' => $yid, 'subject_id' => $ipa, 'phase' => 'D', 'element_name' => 'Pemahaman IPA', 'code' => 'CP-IPA-D-01', 'description' => 'Peserta didik dapat mengidentifikasi sifat dan karakteristik zat serta membedakan perubahan fisika dan kimia.', 'is_active' => 1]);
        $tps = [];
        foreach ([
            [$mtk, 'TP-7.1', 'Membandingkan dan mengurutkan bilangan bulat menggunakan garis bilangan.', 160, [$cp1]],
            [$mtk, 'TP-7.2', 'Melakukan operasi hitung bilangan bulat dan pecahan untuk menyelesaikan masalah kontekstual.', 240, [$cp1]],
            [$mtk, 'TP-7.3', 'Menyatakan pola bilangan ke dalam bentuk aljabar sederhana.', 200, [$cp2]],
            [$mtk, 'TP-7.4', 'Menyelesaikan persamaan linear satu variabel.', 240, [$cp2]],
            [$ipa, 'TP-IPA-7.1', 'Mengklasifikasikan zat berdasarkan wujud dan sifatnya.', 160, [$cp3]],
            [$ipa, 'TP-IPA-7.2', 'Membedakan perubahan fisika dan kimia melalui percobaan sederhana.', 200, [$cp3]],
        ] as [$sub, $code, $desc, $min, $cps]) {
            $tid = (new LearningObjectiveModel())->insert(['academic_year_id' => $yid, 'subject_id' => $sub, 'code' => $code, 'description' => $desc, 'estimated_minutes' => $min, 'is_active' => 1]);
            foreach ($cps as $cp) {
                (new OutcomeObjectiveModel())->insert(['learning_outcome_id' => $cp, 'learning_objective_id' => $tid]);
            }
            $tps[$code] = $tid;
        }

        // Kalender contoh
        foreach ([
            ['Masa Pengenalan Lingkungan Sekolah (contoh)', 'kegiatan', "$y-07-13", "$y-07-15"],
            ['Hari Kemerdekaan RI', 'libur', "$y-08-17", "$y-08-17"],
            ['Asesmen Tengah Semester Ganjil (contoh)', 'ujian', "$y-09-21", "$y-09-25"],
            ['Rapat evaluasi pembelajaran (contoh)', 'rapat', date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+3 days'))],
            ['Asesmen Akhir Semester Ganjil (contoh)', 'ujian', "$y-11-30", "$y-12-05"],
            ['Libur semester ganjil (contoh)', 'libur', "$y-12-21", ($y + 1) . '-01-02'],
        ] as [$t, $type, $a, $b]) {
            (new CalendarEventModel())->insert(['academic_year_id' => $yid, 'title' => $t, 'event_type' => $type, 'start_date' => $a, 'end_date' => $b]);
        }

        // Jadwal
        foreach ([
            [1, 'VII A', $mtk, '07:30', '09:00'], [1, 'VII B', $mtk, '09:15', '10:45'], [2, 'VIII A', $ipa, '07:30', '09:00'],
            [3, 'VII A', $mtk, '10:00', '11:30'], [4, 'VII B', $mtk, '07:30', '09:00'], [4, 'VIII A', $ipa, '09:15', '10:45'],
            [5, 'VII A', $ipa, '07:30', '08:50'],
        ] as [$d, $c, $sub, $a, $b]) {
            (new ScheduleModel())->insert(['user_id' => $uid, 'lesson_hours' => null, 'academic_year_id' => $yid, 'class_group_id' => $classes[$c], 'subject_id' => $sub, 'day_of_week' => $d, 'start_time' => "$a:00", 'end_time' => "$b:00", 'room' => 'R. ' . str_replace(' ', '', $c)]);
        }

        // Agenda 3 minggu terakhir untuk VII A MTK
        $topics = ['Garis bilangan dan urutan bilangan bulat', 'Penjumlahan & pengurangan bilangan bulat', 'Perkalian & pembagian bilangan bulat', 'Pecahan senilai', 'Operasi pecahan dalam konteks'];
        $start  = max("$y-07-20", date('Y-m-d', strtotime('-21 days')));
        $d      = $start;
        $i      = 0;
        while ($d < date('Y-m-d') && $i < count($topics)) {
            if ((int) date('N', strtotime($d)) === 1 || (int) date('N', strtotime($d)) === 3) {
                (new AgendaModel())->insert([
                    'user_id' => $uid, 'agenda_date' => $d, 'semester_id' => $s1, 'class_group_id' => $classes['VII A'], 'subject_id' => $mtk,
                    'learning_objective_id' => $i < 3 ? $tps['TP-7.1'] : $tps['TP-7.2'], 'topic' => $topics[$i],
                    'activities' => 'Apersepsi, diskusi kelompok, latihan soal bertahap, dan refleksi bersama.',
                    'reflection' => $i % 2 ? 'Sebagian siswa masih keliru pada tanda negatif.' : 'Siswa antusias; waktu latihan perlu ditambah.',
                    'follow_up' => 'Remedial singkat untuk siswa yang belum tuntas.', 'status' => 'selesai',
                ]);
                $i++;
            }
            $d = date('Y-m-d', strtotime($d . ' +1 day'));
        }

        // Asesmen + nilai VII A MTK (UH lengkap, UH2 sebagian, ATS lengkap)
        $types = array_column($this->db->table('assessment_types')->get()->getResultArray(), 'id', 'code');
        $mk = static fn ($type, $title, $date, $tp) => (new AssessmentModel())->insert([
            'user_id' => $uid, 'assessment_type_id' => $types[$type], 'academic_year_id' => $yid, 'semester_id' => $s1, 'class_group_id' => $classes['VII A'],
            'subject_id' => $mtk, 'learning_objective_id' => $tp, 'title' => $title, 'assessment_date' => $date, 'max_score' => 100, 'status' => 'terbuka',
        ]);
        $uh1 = $mk('UH', 'UH 1 — Bilangan bulat', "$y-08-24", $tps['TP-7.1']);
        $uh2 = $mk('UH', 'UH 2 — Operasi pecahan', "$y-09-14", $tps['TP-7.2']);
        $ats = $mk('ATS', 'ATS Ganjil', "$y-09-22", null);
        foreach ($studentsByClass['VII A'] as $k => $sid) {
            (new ScoreModel())->insert(['assessment_id' => $uh1, 'student_id' => $sid, 'score' => mt_rand(60, 98)]);
            (new ScoreModel())->insert(['assessment_id' => $ats, 'student_id' => $sid, 'score' => mt_rand(55, 95)]);
            if ($k < 18) {
                (new ScoreModel())->insert(['assessment_id' => $uh2, 'student_id' => $sid, 'score' => mt_rand(58, 100)]);
            }
        }

        $cfg = (new GradingConfigModel())->insert(['user_id' => $uid, 'academic_year_id' => $yid, 'semester_id' => $s1, 'class_group_id' => null, 'subject_id' => $mtk, 'name' => 'Bobot Matematika (semua kelas)', 'is_active' => 1, 'rounding_rule' => '2', 'aggregation_method' => 'mean']);
        foreach (['UH' => 40, 'ATS' => 25, 'AAS' => 35] as $code => $w) {
            (new GradingConfigItemModel())->insert(['grading_config_id' => $cfg, 'assessment_type_id' => $types[$code], 'weight_percent' => $w, 'is_enabled' => 1]);
        }
    }
}
