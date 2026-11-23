<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\GradeCalculator;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Models\AssessmentModel;
use App\Models\GradingConfigItemModel;
use App\Models\GradingConfigModel;
use App\Models\ScoreModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/** Pengaturan bobot penilaian per cakupan (tahun, semester, mapel, kelas/semua kelas). */
class GradingConfigs extends BaseController
{
    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $rows = (new GradingConfigModel())
            ->select('grading_configs.*, s.name AS subject_name, cg.name AS class_name')
            ->join('subjects s', 's.id = grading_configs.subject_id')
            ->join('class_groups cg', 'cg.id = grading_configs.class_group_id', 'left')
            ->where('grading_configs.user_id', Auth::id())
            ->where('grading_configs.academic_year_id', Period::yearId())
            ->where('grading_configs.semester_id', Period::semesterId())
            ->orderBy('s.name', 'ASC')->findAll();

        $types = Lookup::assessmentTypeRows();
        foreach ($rows as &$r) {
            $items = array_column((new GradingConfigItemModel())->where('grading_config_id', $r['id'])->findAll(), null, 'assessment_type_id');
            $w     = [];
            foreach ($types as $t) {
                if (isset($items[$t['id']]) && (int) $items[$t['id']]['is_enabled']) {
                    $w[$t['code']] = (float) $items[$t['id']]['weight_percent'];
                }
            }
            $r['weights'] = $w;
            $r['check']   = GradeCalculator::validateWeights($w);
        }
        unset($r);

        return $this->render('grading/index', ['title' => 'Pengaturan Bobot Nilai', 'rows' => $rows]);
    }

    public function new()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        return $this->form(null);
    }

    public function edit(int $id)
    {
        return $this->form($this->find($id));
    }

    private function find(int $id): array
    {
        $row = (new GradingConfigModel())->where('user_id', Auth::id())->find($id);
        if (! $row) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }

    private function itemsOf(?array $row): array
    {
        if (! $row) {
            return [];
        }

        return array_column((new GradingConfigItemModel())->where('grading_config_id', $row['id'])->findAll(), null, 'assessment_type_id');
    }

    private function form(?array $row, bool $needConfirm = false)
    {
        $types    = Lookup::assessmentTypeRows();
        $items    = $this->itemsOf($row);
        $defaults = ['TGS' => 20, 'UH' => 30, 'ATS' => 20, 'AAS' => 30];
        foreach ($types as &$t) {
            $t['weight']  = old('weight.' . $t['id'], isset($items[$t['id']]) ? fmt_num($items[$t['id']]['weight_percent']) : ($defaults[$t['code']] ?? 0), false);
            $t['enabled'] = old('enabled', null, false) !== null ? isset(old('enabled', null, false)[$t['id']]) : (isset($items[$t['id']]) ? (int) $items[$t['id']]['is_enabled'] === 1 : true);
        }
        unset($t);

        return $this->render('grading/form', [
            'title'       => $row ? 'Ubah Pengaturan Bobot' : 'Tambah Pengaturan Bobot',
            'row'         => $row,
            'types'       => $types,
            'subjects'    => Lookup::teachingSubjects(),
            'classes'     => Lookup::teachingClasses(isset($row['class_group_id']) ? (int) $row['class_group_id'] : null),
            'needConfirm' => $needConfirm || session('needConfirm'),
            'hasScores'   => $row ? $this->scoresInScope($row) > 0 : false,
        ]);
    }

    public function create()
    {
        return $this->save(null);
    }

    public function update(int $id)
    {
        return $this->save($this->find($id));
    }

    private function save(?array $old)
    {
        $rules = [
            'name'               => ['label' => 'Nama', 'rules' => 'required|max_length[100]'],
            'subject_id'         => ['label' => 'Mata pelajaran', 'rules' => 'required|is_natural_no_zero'],
            // "Semua kelas" (kosong) atau kelas yang diajar
            'class_group_id'     => ['label' => 'Kelas', 'rules' => 'permit_empty|in_list[' . implode(',', array_keys(Lookup::teachingClasses(isset($old['class_group_id']) ? (int) $old['class_group_id'] : null)) ?: [0]) . ']',
                'errors' => ['in_list' => 'Pilih kelas yang Anda ajar.']],
            'aggregation_method' => ['label' => 'Metode UH', 'rules' => 'required|in_list[' . implode(',', array_keys(GradeCalculator::METHODS)) . ']'],
            'rounding_rule'      => ['label' => 'Pembulatan', 'rules' => 'required|in_list[' . implode(',', array_keys(GradeCalculator::ROUNDING)) . ']'],
        ];
        if (! $this->validate($rules)) {
            return $this->back('Periksa kembali isian yang ditandai.', $this->validator->getErrors());
        }

        $types   = Lookup::assessmentTypeRows();
        $weights = (array) $this->request->getPost('weight');
        $enabled = (array) ($this->request->getPost('enabled') ?? []);
        $items   = [];
        $active  = [];
        foreach ($types as $t) {
            $raw = str_replace(',', '.', trim((string) ($weights[$t['id']] ?? '0')));
            if ($raw === '') {
                $raw = '0';
            }
            if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
                return $this->back('Bobot ' . $t['code'] . ' harus angka 0–100.');
            }
            $on = isset($enabled[$t['id']]) ? 1 : 0;
            $items[$t['id']] = ['weight_percent' => round((float) $raw, 2), 'is_enabled' => $on];
            if ($on) {
                $active[$t['code']] = round((float) $raw, 2);
            }
        }
        if ($active === []) {
            return $this->back('Aktifkan minimal satu kategori penilaian.');
        }
        $check = GradeCalculator::validateWeights($active);

        $data = [
            'academic_year_id'   => $old['academic_year_id'] ?? Period::yearId(),
            'semester_id'        => $old['semester_id'] ?? Period::semesterId(),
            'subject_id'         => (int) $this->request->getPost('subject_id'),
            'class_group_id'     => ((int) $this->request->getPost('class_group_id')) ?: null,
            'name'               => trim((string) $this->request->getPost('name')),
            'aggregation_method' => $this->request->getPost('aggregation_method'),
            'rounding_rule'      => $this->request->getPost('rounding_rule'),
            'is_active'          => $this->request->getPost('is_active') ? 1 : 0,
            'user_id'            => $old['user_id'] ?? Auth::id(),
        ];
        if (! $data['semester_id']) {
            return $this->back('Belum ada semester aktif.');
        }

        // Satu konfigurasi aktif per cakupan.
        if ($data['is_active']) {
            $dup = (new GradingConfigModel())->where('user_id', $data['user_id'])->where('academic_year_id', $data['academic_year_id'])
                ->where('semester_id', $data['semester_id'])->where('subject_id', $data['subject_id'])
                ->where('class_group_id', $data['class_group_id'])->where('is_active', 1);
            if ($old) {
                $dup->where('id !=', $old['id']);
            }
            if ($dup->countAllResults() > 0) {
                return $this->back('Sudah ada pengaturan bobot aktif untuk cakupan mapel/kelas ini. Nonaktifkan yang lama atau ubah cakupannya.');
            }
        }

        // Perubahan bobot setelah nilai masuk wajib dikonfirmasi.
        $oldItems = $this->itemsOf($old);
        $changed  = false;
        if ($old) {
            foreach ($items as $tid => $it) {
                $o = $oldItems[$tid] ?? null;
                if (! $o || (float) $o['weight_percent'] !== (float) $it['weight_percent'] || (int) $o['is_enabled'] !== $it['is_enabled']) {
                    $changed = true;
                }
            }
            $changed = $changed || $old['aggregation_method'] !== $data['aggregation_method'];
        }
        $scopeHasScores = $this->scoresInScope($data) > 0;
        if (($changed || ! $old) && $scopeHasScores && ! $this->request->getPost('confirm_change')) {
            return redirect()->back()->withInput()->with('needConfirm', true)
                ->with('warning', 'Sudah ada nilai pada cakupan ini. Perubahan bobot akan mengubah hasil rekap. Centang konfirmasi lalu simpan lagi.');
        }

        $db = db_connect();
        $db->transException(true)->transStart();
        if ($old) {
            (new GradingConfigModel())->update($old['id'], $data);
            $id = (int) $old['id'];
        } else {
            $id = (int) (new GradingConfigModel())->insert($data);
        }
        foreach ($items as $tid => $it) {
            if (isset($oldItems[$tid])) {
                (new GradingConfigItemModel())->update($oldItems[$tid]['id'], $it);
            } else {
                (new GradingConfigItemModel())->insert($it + ['grading_config_id' => $id, 'assessment_type_id' => $tid]);
            }
        }
        $db->transComplete();

        Audit::log($old ? 'weights_update' : 'weights_create', 'grading_config', $id,
            $old ? ['config' => $old, 'items' => array_values($oldItems)] : null,
            ['config' => $data, 'items' => $items, 'confirmed_with_scores' => $scopeHasScores]);

        $msg = 'Pengaturan bobot tersimpan.';
        $redirect = redirect()->to(site_url('grading'))->with('success', $msg);
        if (! $check['valid']) {
            $redirect->with('warning', 'Total bobot kategori aktif = ' . fmt_num($check['sum']) . '%. Rekap final hanya dapat dihitung bila totalnya tepat 100%.');
        }

        return $redirect;
    }

    public function delete(int $id)
    {
        $row = $this->find($id);
        (new GradingConfigModel())->delete($id);
        Audit::log('delete', 'grading_config', $id, $row);

        return redirect()->to(site_url('grading'))->with('success', 'Pengaturan bobot dihapus.');
    }

    private function scoresInScope(array $cfg): int
    {
        $a = (new AssessmentModel())->select('id')->where('user_id', $cfg['user_id'] ?? Auth::id())
            ->where('academic_year_id', $cfg['academic_year_id'])->where('semester_id', $cfg['semester_id'])
            ->where('subject_id', $cfg['subject_id']);
        if (! empty($cfg['class_group_id'])) {
            $a->where('class_group_id', $cfg['class_group_id']);
        }
        $ids = array_column($a->findAll(), 'id');

        return $ids ? (new ScoreModel())->whereIn('assessment_id', $ids)->countAllResults() : 0;
    }
}
