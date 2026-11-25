<?php

namespace App\Controllers;

use App\Libraries\Lookup;
use App\Libraries\Period;

/** Admin: rekap kegiatan guru di luar KBM per semester (jumlah per kelompok + daftar kegiatan). */
class ActivityRecap extends BaseController
{
    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        $db    = db_connect();
        $sems  = Lookup::semesters();
        $semId = (int) ($this->request->getGet('semester_id') ?? 0) ?: (int) Period::semesterId();
        $uid   = (int) $this->request->getGet('user_id');
        $type  = (string) $this->request->getGet('activity_type');
        $types = Activities::types();
        if (! isset($types[$type])) {
            $type = '';
        }
        $teachers = [];
        foreach ($db->table('users')->select('id, name, employment_status')->where('role', 'teacher')->orderBy('name', 'ASC')->get()->getResultArray() as $t) {
            $teachers[(int) $t['id']] = $t['name'] . (($t['employment_status'] ?? 'aktif') !== 'aktif' ? ' (' . ucfirst((string) $t['employment_status']) . ')' : '');
        }

        $q = $db->table('teacher_activities k')->select('k.*, u.name AS teacher_name')->join('users u', 'u.id = k.user_id')
            ->where('k.academic_year_id', (int) Period::yearId());
        if ($semId) {
            $q->where('k.semester_id', $semId);
        }
        if ($uid) {
            $q->where('k.user_id', $uid);
        }
        if ($type !== '') {
            $q->where('k.activity_type', $type);
        }
        $rows = $q->orderBy('k.activity_date', 'DESC')->orderBy('k.start_time', 'ASC')->get()->getResultArray();

        // Ringkasan per guru: jumlah per kelompok jenis
        $groups  = array_keys(Activities::GROUPS);
        $summary = [];
        foreach ($rows as $r) {
            $id = (int) $r['user_id'];
            $summary[$id] ??= ['name' => $r['teacher_name'], 'total' => 0, 'last' => $r['activity_date']] + array_fill_keys($groups, 0);
            $summary[$id]['total']++;
            $summary[$id][Activities::groupOf($r['activity_type'])]++;
            $summary[$id]['last'] = max($summary[$id]['last'], $r['activity_date']);
        }
        uasort($summary, static fn ($a, $b) => $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']));

        if ($this->request->getGet('format') === 'csv') {
            return csv_response('kegiatan-guru-' . date('Ymd') . '.csv', ['Tanggal', 'Guru', 'Jenis', 'Kegiatan', 'Mulai', 'Selesai', 'Tempat', 'Uraian'],
                array_map(static fn ($r) => [$r['activity_date'], $r['teacher_name'], $types[$r['activity_type']] ?? $r['activity_type'], $r['title'], $r['start_time'], $r['end_time'], $r['location'], $r['description']], $rows));
        }

        return $this->render('activities/recap', [
            'title'    => 'Rekap Kegiatan Guru',
            'sems'     => $sems,
            'semId'    => $semId,
            'uid'      => $uid,
            'type'     => $type,
            'types'    => $types,
            'teachers' => $teachers,
            'rows'     => $rows,
            'summary'  => $summary,
            'groups'   => $groups,
        ]);
    }
}
