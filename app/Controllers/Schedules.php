<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\Lookup;
use App\Libraries\Period;
use App\Models\ScheduleModel;

class Schedules extends CrudController
{
    protected string $modelClass = ScheduleModel::class;
    protected string $table      = 'teaching_schedules';
    protected string $base       = 'schedules';
    protected string $title      = 'Jadwal Mengajar';
    protected string $singular   = 'Jadwal';
    protected string $entity     = 'teaching_schedule';
    protected bool $yearScoped   = true;
    protected bool $archive      = false;
    protected bool $ownerScoped  = true;

    protected function fields(?array $row): array
    {
        return [
            'lesson_hours'   => ['label' => 'Jam ke', 'rules' => 'permit_empty|max_length[20]|regex_match[/^[0-9][0-9 ,\-–]*$/]', 'placeholder' => '1-2', 'help' => 'Contoh: 1-2. Dipakai di kisi Jadwal Mengajar & terisi otomatis di agenda.'],
            'day_of_week'    => ['label' => 'Hari', 'type' => 'select', 'options' => array_slice(day_names(), 0, 6, true), 'rules' => 'required|in_list[1,2,3,4,5,6,7]', 'default' => min(6, (int) date('N'))],
            'class_group_id' => ['label' => 'Kelas', 'type' => 'select', 'options' => $cls = Lookup::teachingClasses(isset($row['class_group_id']) ? (int) $row['class_group_id'] : null), 'rules' => 'required|in_list[' . implode(',', array_keys($cls) ?: [0]) . ']', 'errors' => ['in_list' => 'Pilih kelas yang Anda ajar (atur di Profil → Mapel & kelas).'], 'empty' => '— Pilih kelas —'],
            'subject_id'     => ['label' => 'Mata pelajaran', 'type' => 'select', 'options' => Lookup::teachingSubjectsWith(isset($row['subject_id']) ? (int) $row['subject_id'] : null), 'rules' => 'required|is_natural_no_zero', 'empty' => '— Pilih mapel —'],
            'start_time'     => ['label' => 'Jam mulai', 'type' => 'time', 'rules' => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]', 'default' => '07:30'],
            'end_time'       => ['label' => 'Jam selesai', 'type' => 'time', 'rules' => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]', 'default' => '09:00'],
            'room'           => ['label' => 'Ruang', 'rules' => 'permit_empty|max_length[50]'],
        ];
    }

    protected function formValues(?array $row): array
    {
        if ($row) {
            $row['start_time'] = substr($row['start_time'], 0, 5);
            $row['end_time']   = substr($row['end_time'], 0, 5);
        }

        return $row ?? [];
    }

    protected function columns(): array
    {
        return [];
    }

    protected function prepare(array $data, ?array $old)
    {
        $data['start_time'] = substr($data['start_time'], 0, 5) . ':00';
        $data['end_time']   = substr($data['end_time'], 0, 5) . ':00';
        if ($data['end_time'] <= $data['start_time']) {
            return 'Jam selesai harus setelah jam mulai.';
        }
        // Satu guru tidak dapat mengajar dua kelas pada waktu yang beririsan.
        $clash = (new ScheduleModel())
            ->where('academic_year_id', $old['academic_year_id'] ?? Period::yearId())
            ->where('user_id', Auth::id())
            ->where('day_of_week', (int) $data['day_of_week'])
            ->where('start_time <', $data['end_time'])
            ->where('end_time >', $data['start_time']);
        if ($old) {
            $clash->where('id !=', $old['id']);
        }
        if ($c = $clash->first()) {
            return 'Jadwal bentrok dengan jadwal lain pada ' . day_names()[(int) $data['day_of_week']] . ' pukul ' . fmt_time($c['start_time']) . '–' . fmt_time($c['end_time']) . '.';
        }

        return $data;
    }

    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        return $this->render('schedules/index', [
            'grid'  => self::grid(Period::yearId(), Auth::id()),
            'title' => $this->title,
            'byDay' => self::grouped(Period::yearId(), null, Auth::id()),
            'piket' => \App\Libraries\DutyRoster::forUser((int) Auth::id(), Period::yearId()),
        ]);
    }

    /**
     * Kisi "Jadwal Mengajar": baris = jam ke/pukul, kolom = Senin–Sabtu, isi = kelas (mapel).
     *
     * @return list<array{jam: string, pukul: string, days: array<int, list<string>>}>
     */
    public static function grid(?int $yearId, ?int $userId = null): array
    {
        $rows = [];
        foreach (self::grouped($yearId, null, $userId) as $day => $items) {
            foreach ($items as $s) {
                $key = substr($s['start_time'], 0, 5) . '|' . substr($s['end_time'], 0, 5) . '|' . ($s['lesson_hours'] ?? '');
                $rows[$key] ??= ['jam' => (string) ($s['lesson_hours'] ?? ''), 'pukul' => fmt_time($s['start_time']) . '–' . fmt_time($s['end_time']), 'days' => []];
                $rows[$key]['days'][$day][] = $s['class_name'] . ' (' . $s['subject_name'] . ')';
            }
        }
        ksort($rows);

        return array_values($rows);
    }

    /** @return array<int, list<array>> */
    public static function grouped(?int $yearId, ?int $day = null, ?int $userId = null): array
    {
        $m = (new ScheduleModel())
            ->select('teaching_schedules.*, cg.name AS class_name, s.name AS subject_name, s.code AS subject_code')
            ->join('class_groups cg', 'cg.id = teaching_schedules.class_group_id')
            ->join('subjects s', 's.id = teaching_schedules.subject_id')
            ->where('teaching_schedules.academic_year_id', $yearId);
        if ($day) {
            $m->where('teaching_schedules.day_of_week', $day);
        }
        if ($userId) {
            $m->where('teaching_schedules.user_id', $userId);
        }
        $out = [];
        foreach ($m->orderBy('day_of_week', 'ASC')->orderBy('start_time', 'ASC')->findAll() as $r) {
            $out[(int) $r['day_of_week']][] = $r;
        }

        return $out;
    }
}
