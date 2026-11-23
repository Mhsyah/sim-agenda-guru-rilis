<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Libraries\RecapService;
use App\Models\AssessmentModel;
use App\Models\AssessmentTypeModel;
use App\Models\ScoreModel;
use CodeIgniter\Model;

class Assessments extends CrudController
{
    protected string $modelClass = AssessmentModel::class;
    protected string $table      = 'assessments';
    protected string $base       = 'assessments';
    protected string $title      = 'Asesmen & Nilai';
    protected string $singular   = 'Asesmen';
    protected string $entity     = 'assessment';
    public const MISSING_POLICIES = ['nol' => 'Dihitung nilai 0', 'abaikan' => 'Tidak dihitung dalam rata-rata'];

    protected ?string $intro     = 'Buat Tugas Harian, UH, ATS, atau AAS per kelas dan mapel pada semester aktif, lalu input nilainya.';
    protected array $searchFields = ['assessments.title'];
    protected array $order       = ['assessment_date', 'DESC', 'assessments.id', 'DESC'];
    protected bool $yearScoped   = true;
    protected bool $audit        = true;
    protected bool $ownerScoped  = true;
    protected bool $taughtOnly   = true;
    protected ?string $deleteLabel   = 'Hapus';
    protected ?string $deleteConfirm = 'Hapus asesmen ini? Nilai yang sudah diinput ikut disembunyikan dari rekap.';

    public function index()
    {
        $type = strtoupper((string) $this->request->getGet('type'));
        if ($type !== '') {
            $t = (new AssessmentTypeModel())->where('code', $type)->first();
            if ($t) {
                $_GET['assessment_type_id'] = $t['id'];
                $this->request->setGlobal('get', $_GET);
            }
        }

        return parent::index();
    }

    /** Tab jenis asesmen (Semua, Tugas Harian, UH, ATS, AAS) beserta jumlahnya. */
    protected function extraIndexData(array $filters): array
    {
        $taught = \App\Libraries\Lookup::teachingSubjectIds();
        $q      = db_connect()->table('assessments')->select('assessment_type_id, COUNT(*) AS n')
            ->where('user_id', (int) \App\Libraries\Auth::id())->where('academic_year_id', (int) \App\Libraries\Period::yearId())->where('deleted_at', null);
        if ($taught !== null) {
            $q->whereIn('subject_id', $taught ?: [0]);
        }
        $counts = array_column($q->groupBy('assessment_type_id')->get()->getResultArray(), 'n', 'assessment_type_id');
        $tabs   = [['code' => '', 'label' => 'Semua', 'n' => array_sum($counts)]];
        foreach (\App\Libraries\Lookup::assessmentTypeRows() as $t) {
            $tabs[] = ['code' => $t['code'], 'label' => $t['code'] === 'TGS' ? 'Tugas Harian' : $t['code'], 'title' => $t['name'], 'n' => (int) ($counts[$t['id']] ?? 0)];
        }

        return ['typeTabs' => $tabs, 'typeActive' => strtoupper((string) $this->request->getGet('type'))];
    }

    protected function filterDefs(): array
    {
        return [
            'assessment_type_id' => ['label' => 'Jenis', 'options' => Lookup::assessmentTypes()],
            'class_group_id'     => ['label' => 'Kelas', 'options' => Lookup::teachingClasses()],
            'subject_id'         => ['label' => 'Mapel', 'options' => Lookup::teachingSubjects()],
        ];
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        $sc = db_connect()->prefixTable('assessment_scores');
        $en = db_connect()->prefixTable('student_enrollments');
        $as = db_connect()->prefixTable('assessments');
        $st = db_connect()->prefixTable('students'); // siswa terarsip tidak dihitung
        $m->select('assessments.*, t.code AS type_code, cg.name AS class_name, s.name AS subject_name')
            ->select("(SELECT COUNT(*) FROM {$sc} x WHERE x.assessment_id = {$as}.id) AS graded,
                (SELECT COUNT(*) FROM {$en} e JOIN {$st} st ON st.id = e.student_id AND st.deleted_at IS NULL WHERE e.class_group_id = {$as}.class_group_id AND e.status = 'aktif') AS enrolled", false)
            ->join('assessment_types t', 't.id = assessments.assessment_type_id')
            ->join('class_groups cg', 'cg.id = assessments.class_group_id')
            ->join('subjects s', 's.id = assessments.subject_id')
            ->where('assessments.semester_id', Period::semesterId());

        return parent::listQuery($m, $filters);
    }

    protected function fields(?array $row): array
    {
        $typeId = $this->request->getGet('assessment_type_id');
        if (! $typeId && ($code = $this->request->getGet('type'))) {
            $typeId = (new AssessmentTypeModel())->where('code', strtoupper($code))->first()['id'] ?? null;
        }

        return [
            'assessment_type_id'    => ['label' => 'Jenis asesmen', 'type' => 'select', 'options' => Lookup::assessmentTypes(), 'rules' => 'required|is_natural_no_zero', 'default' => $typeId],
            'assessment_date'       => ['label' => 'Tanggal', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => date('Y-m-d')],
            'title'                 => ['label' => 'Judul', 'rules' => 'required|max_length[150]', 'placeholder' => 'UH 1 — Bilangan bulat', 'span' => 'full'],
            'class_group_id'        => ['label' => 'Kelas', 'type' => 'select', 'options' => ($cls = Lookup::teachingClasses(isset($row['class_group_id']) ? (int) $row['class_group_id'] : null)), 'rules' => 'required|in_list[' . implode(',', array_keys($cls) ?: [0]) . ']', 'errors' => ['in_list' => 'Pilih kelas yang Anda ajar.'], 'empty' => '— Pilih kelas —'],
            'subject_id'            => ['label' => 'Mata pelajaran', 'type' => 'select', 'options' => Lookup::teachingSubjectsWith(isset($row['subject_id']) ? (int) $row['subject_id'] : null), 'rules' => 'required|is_natural_no_zero', 'empty' => '— Pilih mapel —'],
            'learning_objective_id' => ['label' => 'TP yang diukur (opsional)', 'type' => 'select', 'groups' => Lookup::objectivesForSemester(isset($row['learning_objective_id']) ? (int) $row['learning_objective_id'] : null), 'filterBy' => 'subject_id', 'rules' => 'permit_empty|is_natural_no_zero', 'empty' => '— Tanpa TP —', 'span' => 'full'],
            'max_score'             => ['label' => 'Nilai maksimum', 'type' => 'number', 'rules' => 'required|decimal|greater_than[0]|less_than_equal_to[1000]', 'default' => 100, 'attrs' => ['min' => '0.01', 'step' => '0.01'], 'help' => 'Nilai dinormalisasi ke skala 0–100 saat rekap.'],
            'status'                => ['label' => 'Status', 'type' => 'select', 'options' => ['terbuka' => 'Terbuka (nilai dapat diubah)', 'selesai' => 'Selesai'], 'rules' => 'required|in_list[terbuka,selesai]', 'default' => 'terbuka'],
            'missing_policy'        => ['label' => 'Siswa tidak mengumpulkan / tidak ikut', 'type' => 'select', 'options' => self::MISSING_POLICIES, 'rules' => 'required|in_list[nol,abaikan]', 'default' => 'nol',
                'help' => 'Berlaku untuk siswa yang ditandai "Tidak kumpul" saat input nilai.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'assessment_date' => ['label' => 'Tanggal', 'fn' => static fn ($r) => '<span class="whitespace-nowrap">' . fmt_date($r['assessment_date']) . '</span>'],
            'title'           => ['label' => 'Asesmen', 'class' => 'col-main', 'fn' => static fn ($r) => badge($r['type_code'], 'info') . ' <strong>' . esc($r['title']) . '</strong><span class="block text-sm text-muted">Maks. ' . fmt_num($r['max_score']) . '</span>'],
            'class'           => ['label' => 'Kelas · Mapel', 'fn' => static fn ($r) => esc($r['class_name']) . '<span class="block text-sm text-muted">' . esc($r['subject_name']) . '</span>'],
            'progress'        => ['label' => 'Nilai terisi', 'fn' => static function ($r) {
                $g = (int) $r['graded'];
                $e = (int) $r['enrolled'];
                $tone = $e > 0 && $g >= $e ? 'success' : ($g > 0 ? 'warning' : 'neutral');

                return badge($g . ' / ' . $e . ($e > 0 && $g >= $e ? ' · Lengkap' : ' · Belum lengkap'), $tone);
            }],
        ];
    }

    protected function rowLinks(array $row): array
    {
        return [['label' => 'Input nilai', 'url' => site_url('assessments/' . $row['id'] . '/scores'), 'icon' => 'pen']];
    }

    protected function prepare(array $data, ?array $old)
    {
        if (! $old) {
            if (! Period::semesterId()) {
                return 'Belum ada semester aktif. Tambahkan semester terlebih dahulu.';
            }
            $data['semester_id'] = Period::semesterId();
        } else {
            $max = db_connect()->table('assessment_scores')->selectMax('score')
                ->where('assessment_id', $old['id'])->get()->getRowArray()['score'] ?? null;
            if ($max !== null && (float) $data['max_score'] < (float) $max) {
                return 'Nilai maksimum tidak boleh lebih kecil dari nilai tertinggi yang sudah diinput (' . fmt_num($max) . ').';
            }
        }

        return $data;
    }

    protected function redirectAfterSave(int $id): string
    {
        return site_url('assessments/' . $id . '/scores');
    }

    // ---------------------------------------------------------------- Input nilai

    public function scores(int $id)
    {
        $a = $this->detail($id);

        return $this->render('assessments/scores', [
            'title'    => 'Input Nilai — ' . $a['title'],
            'a'        => $a,
            'students' => RecapService::enrolledStudents((int) $a['class_group_id']),
            'scores'   => array_column((new ScoreModel())->where('assessment_id', $id)->findAll(), null, 'student_id'),
            // KKTP dari TP yang diukur (bila asesmen terhubung ke TP)
            'kktp'     => $a['learning_objective_id'] ? db_connect()->table('learning_objectives')->select('code, description, kktp_criteria, kktp_intervals')
                ->where('id', (int) $a['learning_objective_id'])->get()->getRowArray() : null,
        ]);
    }

    public function saveScores(int $id)
    {
        $a = $this->detail($id);
        if ($a['status'] === 'selesai') {
            return redirect()->back()->with('error', 'Asesmen berstatus selesai. Ubah status menjadi terbuka untuk mengedit nilai.');
        }
        $max      = (float) $a['max_score'];
        $students = array_column(RecapService::enrolledStudents((int) $a['class_group_id']), null, 'id');
        $input    = (array) ($this->request->getPost('score') ?? []);
        $notes    = (array) ($this->request->getPost('notes') ?? []);
        $missing  = array_map('intval', array_keys((array) ($this->request->getPost('missing') ?? [])));
        $existing = array_column((new ScoreModel())->where('assessment_id', $id)->findAll(), null, 'student_id');

        $errors = [];
        $clean  = [];
        foreach ($input as $sid => $raw) {
            $sid = (int) $sid;
            if (! isset($students[$sid])) {
                continue;
            }
            if (in_array($sid, $missing, true)) {
                $clean[$sid] = 'missing'; // tidak mengumpulkan: disimpan 0 + penanda

                continue;
            }
            $raw = str_replace(',', '.', trim((string) $raw));
            if ($raw === '') {
                $clean[$sid] = null;

                continue;
            }
            if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max) {
                $errors['score.' . $sid] = $students[$sid]['full_name'] . ': nilai harus angka 0–' . fmt_num($max) . '.';

                continue;
            }
            $clean[$sid] = round((float) $raw, 2);
        }
        if ($errors) {
            return redirect()->back()->withInput()->with('errors', $errors)
                ->with('error', count($errors) . ' nilai di luar rentang 0–' . fmt_num($max) . ' dan belum disimpan. Perbaiki isian yang ditandai.');
        }

        $changes = [];
        $db      = db_connect();
        $db->transException(true)->transStart();
        foreach ($clean as $sid => $val) {
            $note = trim((string) ($notes[$sid] ?? '')) ?: null;
            $cur  = $existing[$sid] ?? null;
            if ($val === null) {
                if ($cur) {
                    (new ScoreModel())->delete($cur['id']);
                    $changes[$sid] = [(float) $cur['score'], null];
                }

                continue;
            }
            $miss = $val === 'missing' ? 1 : 0;
            $num  = $miss ? 0.0 : $val;
            $log  = $miss ? 'tidak kumpul' : $val;
            if ($cur) {
                $wasMiss = (int) ($cur['is_missing'] ?? 0);
                if ((float) $cur['score'] !== $num || ($cur['notes'] ?? null) !== $note || $wasMiss !== $miss) {
                    (new ScoreModel())->update($cur['id'], ['score' => $num, 'notes' => $note, 'is_missing' => $miss]);
                    if ((float) $cur['score'] !== $num || $wasMiss !== $miss) {
                        $changes[$sid] = [$wasMiss ? 'tidak kumpul' : (float) $cur['score'], $log];
                    }
                }
            } else {
                (new ScoreModel())->insert(['assessment_id' => $id, 'student_id' => $sid, 'score' => $num, 'notes' => $note, 'is_missing' => $miss]);
                $changes[$sid] = [null, $log];
            }
        }
        $db->transComplete();

        if ($changes) {
            Audit::log('scores_update', 'assessment', $id,
                array_map(static fn ($c) => $c[0], $changes),
                array_map(static fn ($c) => $c[1], $changes));
        }

        return redirect()->to(site_url('assessments/' . $id . '/scores'))
            ->with('success', $changes ? count($changes) . ' perubahan nilai tersimpan.' : 'Tidak ada perubahan nilai.');
    }

    private function detail(int $id): array
    {
        $a = (new AssessmentModel())
            ->select('assessments.*, t.code AS type_code, t.name AS type_name, cg.name AS class_name, s.name AS subject_name, lo.code AS tp_code, lo.description AS tp_desc')
            ->join('assessment_types t', 't.id = assessments.assessment_type_id')
            ->join('class_groups cg', 'cg.id = assessments.class_group_id')
            ->join('subjects s', 's.id = assessments.subject_id')
            ->join('learning_objectives lo', 'lo.id = assessments.learning_objective_id', 'left')
            ->where('assessments.id', $id)->where('assessments.user_id', Auth::id())->first();
        if (! $a) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Asesmen tidak ditemukan.');
        }

        return $a;
    }
}
