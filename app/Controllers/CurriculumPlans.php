<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Kktp;
use App\Libraries\KopSettings;
use App\Libraries\Lookup;
use App\Libraries\Period;

/**
 * Kurikulum guru: ATP (Alur Tujuan Pembelajaran) dan KKTP (Kriteria Ketercapaian TP).
 * Keduanya dibentuk dari data TP (urutan ATP, kelas, semester, kriteria & interval) — dapat disaring dan dicetak dengan kop.
 */
class CurriculumPlans extends BaseController
{
    /** Saringan dari query string (mapel, kelas/tingkat, semester). */
    private function filters(): array
    {
        $subjects = Lookup::teachingSubjects();
        $sid      = (int) $this->request->getGet('subject_id');

        return [
            'subjects'   => $subjects,
            'semesters'  => Lookup::semesters(),
            'subject_id' => isset($subjects[$sid]) ? $sid : (int) array_key_first($subjects),
            'grade'      => max(0, min(13, (int) $this->request->getGet('grade'))),
            'semester'   => (int) $this->request->getGet('semester_id'),
        ];
    }

    /** TP sesuai saringan, urut kelas → semester (Ganjil, Genap, keduanya) → urutan ATP → kode. */
    private function objectives(array $f): array
    {
        if (! $f['subject_id']) {
            return [];
        }
        $db = db_connect();
        // kode CP terkait diambil terpisah (portabel MySQL/PostgreSQL)
        $q = $db->table('learning_objectives')->select('learning_objectives.*')
            ->where('academic_year_id', (int) Period::yearId())->where('subject_id', $f['subject_id'])
            ->where('deleted_at', null)->where('is_active', 1);
        if ($f['grade']) {
            $q->groupStart()->where('grade_level', $f['grade'])->orWhere('grade_level', null)->groupEnd();
        }
        if ($f['semester']) {
            $q->groupStart()->where('semester_id', $f['semester'])->orWhere('semester_id', null)->groupEnd();
        }
        $rows = $q->get()->getResultArray();
        $semOrder = array_flip(array_keys($f['semesters']));
        usort($rows, static fn ($a, $b) => [(int) ($a['grade_level'] ?? 99), $a['semester_id'] === null ? 99 : ($semOrder[$a['semester_id']] ?? 98), (int) ($a['atp_order'] ?? 9999), (string) $a['code']]
            <=> [(int) ($b['grade_level'] ?? 99), $b['semester_id'] === null ? 99 : ($semOrder[$b['semester_id']] ?? 98), (int) ($b['atp_order'] ?? 9999), (string) $b['code']]);
        $cps = [];
        if ($rows) {
            foreach ($db->table('outcome_objectives x')->select('x.learning_objective_id, c.code, c.element_name')
                ->join('learning_outcomes c', 'c.id = x.learning_outcome_id')
                ->whereIn('x.learning_objective_id', array_column($rows, 'id'))->get()->getResultArray() as $r) {
                $cps[(int) $r['learning_objective_id']][] = $r['code'] ?: $r['element_name'];
            }
        }
        foreach ($rows as &$r) {
            $r['cp_codes'] = $cps[(int) $r['id']] ?? [];
            $r['bands']    = Kktp::bands($r['kktp_intervals']);
        }
        unset($r);

        return $rows;
    }

    private function page(string $view, string $title)
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $f       = $this->filters();
        $profile = self::schoolProfile();

        return $this->render($view, [
            'title'   => $title,
            'f'       => $f,
            'rows'    => $this->objectives($f),
            'kopSet'  => KopSettings::get($profile),
            'teacher' => Auth::user(),
        ]);
    }

    public function atp()
    {
        return $this->page('curriculum/atp', 'Alur Tujuan Pembelajaran (ATP)');
    }

    public function kktp()
    {
        return $this->page('curriculum/kktp', 'Kriteria Ketercapaian TP (KKTP)');
    }

    /** Simpan urutan ATP (nomor urut per TP). */
    public function saveAtp()
    {
        $allowed = array_keys(Lookup::teachingSubjects());
        $orders  = (array) ($this->request->getPost('atp_order') ?? []);
        $db      = db_connect();
        $n       = 0;
        foreach ($orders as $id => $val) {
            $id  = (int) $id;
            $val = trim((string) $val);
            if ($val !== '' && (! ctype_digit($val) || (int) $val < 1 || (int) $val > 999)) {
                return redirect()->back()->with('error', 'Urutan ATP harus angka 1–999.');
            }
            $tp = $db->table('learning_objectives')->select('id, subject_id, atp_order')->where('id', $id)->where('deleted_at', null)->get()->getRowArray();
            if (! $tp || ! in_array((int) $tp['subject_id'], $allowed, true)) {
                continue; // hanya TP mapel yang diajarkan
            }
            $new = $val === '' ? null : (int) $val;
            if ((string) $tp['atp_order'] !== (string) $new) {
                $db->table('learning_objectives')->where('id', $id)->update(['atp_order' => $new, 'updated_at' => date('Y-m-d H:i:s')]);
                $n++;
            }
        }
        Audit::log('update', 'atp', null, null, ['diubah' => $n]);

        return redirect()->back()->with('success', $n ? 'Urutan ATP disimpan (' . $n . ' TP diubah).' : 'Tidak ada urutan yang berubah.');
    }
}
