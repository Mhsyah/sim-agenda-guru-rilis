<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Libraries\Period;
use App\Models\CalendarEventModel;
use CodeIgniter\Model;

class Calendar extends CrudController
{
    protected string $modelClass = CalendarEventModel::class;
    protected string $table      = 'school_calendar_events';
    protected string $base       = 'calendar';
    protected string $title      = 'Kalender Pendidikan';
    protected string $singular   = 'Kegiatan kalender';
    protected string $entity     = 'calendar_event';
    protected array $searchFields = ['school_calendar_events.title'];
    protected array $order       = ['start_date', 'ASC'];
    protected bool $yearScoped   = true;
    protected bool $archive      = false;
    protected bool $audit        = true;

    protected function filterDefs(): array
    {
        return ['event_type' => ['label' => 'Jenis', 'options' => event_types()]];
    }

    protected function fields(?array $row): array
    {
        $today = date('Y-m-d');

        return [
            'title'      => ['label' => 'Nama kegiatan', 'rules' => 'required|max_length[150]', 'span' => 'full'],
            'event_type' => ['label' => 'Jenis', 'type' => 'select', 'options' => event_types(), 'rules' => 'required|in_list[' . implode(',', array_keys(event_types())) . ']'],
            'start_date' => ['label' => 'Tanggal mulai', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => $today],
            'end_date'   => ['label' => 'Tanggal selesai', 'type' => 'date', 'rules' => 'required|valid_date[Y-m-d]', 'default' => $today, 'help' => 'Sama dengan tanggal mulai untuk kegiatan satu hari.'],
            'color'      => ['label' => 'Warna di kalender', 'type' => 'color', 'rules' => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
                'default' => event_type_colors()[$row['event_type'] ?? 'kegiatan'] ?? '#3b82f6', 'attrs' => ['data-event-color' => '1'],
                'help' => 'Otomatis mengikuti jenis kegiatan; boleh diganti.'],
            'notes'      => ['label' => 'Catatan', 'type' => 'textarea', 'rules' => 'permit_empty|max_length[2000]', 'span' => 'full'],
        ];
    }

    protected function columns(): array
    {
        return [
            'date'       => ['label' => 'Tanggal', 'fn' => static fn ($r) => fmt_date($r['start_date']) . ($r['end_date'] !== $r['start_date'] ? ' – ' . fmt_date($r['end_date']) : '')],
            'title'      => ['label' => 'Kegiatan', 'class' => 'col-main', 'fn' => static fn ($r) => '<strong>' . esc($r['title']) . '</strong>' . ($r['notes'] ? '<span class="block text-sm text-muted">' . excerpt($r['notes'], 90) . '</span>' : '')],
            'event_type' => ['label' => 'Jenis', 'fn' => static fn ($r) => '<span class="cal-chip" style="--ev:' . esc(event_color($r), 'attr') . '">' . esc(event_types()[$r['event_type']] ?? $r['event_type']) . '</span>'],
        ];
    }

    protected function canEdit(): bool
    {
        return Auth::isAdmin();
    }

    protected function headerLinks(): array
    {
        return [
            ['label' => 'Tampilan bulanan', 'url' => site_url('calendar'), 'icon' => 'calendar'],
            ...(Auth::isTeacher() ? [['label' => 'Cetak / ekspor', 'url' => site_url('reports/calendar'), 'icon' => 'printer']] : []),
        ];
    }

    protected function prepare(array $data, ?array $old)
    {
        if ($data['end_date'] < $data['start_date']) {
            return 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.';
        }

        return $data;
    }

    public function index()
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }
        if ($this->request->getGet('view') === 'list') {
            return parent::index();
        }

        $month = (string) $this->request->getGet('month');
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $first = $month . '-01';
        $last  = date('Y-m-t', strtotime($first));

        // Kegiatan yang beririsan dengan bulan ini (perbandingan tanggal portabel).
        $events = (new CalendarEventModel())
            ->where('start_date <=', $last)->where('end_date >=', $first)
            ->orderBy('start_date', 'ASC')->findAll();

        $byDay = [];
        foreach ($events as $e) {
            $d   = max($e['start_date'], $first);
            $end = min($e['end_date'], $last);
            while ($d <= $end) {
                $byDay[$d][] = $e;
                $d = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }

        // Grid Senin–Minggu.
        $start = date('Y-m-d', strtotime($first . ' -' . ((int) date('N', strtotime($first)) - 1) . ' days'));
        $weeks = [];
        $d     = $start;
        do {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = ['date' => $d, 'in' => substr($d, 0, 7) === $month, 'events' => $byDay[$d] ?? []];
                $d = date('Y-m-d', strtotime($d . ' +1 day'));
            }
            $weeks[] = $week;
        } while ($d <= $last);

        $teach   = Auth::isTeacher() ? self::teachingDays($first, $last, $byDay) : [];
        $history = Auth::isTeacher() ? db_connect()->table('teaching_agendas a')
            ->select('a.id, a.agenda_date, a.topic, a.lesson_hours, a.updated_at, a.created_at, cg.name AS class_name, s.name AS subject_name')
            ->join('class_groups cg', 'cg.id = a.class_group_id')->join('subjects s', 's.id = a.subject_id')
            ->where('a.user_id', (int) Auth::id())->where('a.deleted_at', null)
            ->orderBy('a.updated_at', 'DESC')->orderBy('a.id', 'DESC')->limit(8)->get()->getResultArray() : [];

        return $this->render('calendar/index', [
            'teach'  => $teach,
            'history' => $history,
            'title'  => Auth::isTeacher() ? 'Kalender & Agenda' : $this->title,
            'month'  => $month,
            'prev'   => date('Y-m', strtotime($first . ' -1 month')),
            'next'   => date('Y-m', strtotime($first . ' +1 month')),
            'weeks'  => $weeks,
            'events' => $events,
            'year'   => Period::get()['year'],
            'canEdit' => Auth::isAdmin(),
        ]);
    }

    /**
     * Guru: jadwal mengajar & agenda per tanggal pada bulan ini, untuk menulis agenda langsung dari kalender.
     *
     * @return array<string, array{slots: list<array>, extra: list<array>, holiday: ?string, done: int, total: int}>
     */
    private static function teachingDays(string $first, string $last, array $byDay): array
    {
        $uid  = (int) Auth::id();
        $yid  = (int) Period::yearId();
        $db   = db_connect();
        $sem  = $db->table('semesters')->where('academic_year_id', $yid)->orderBy('start_date', 'ASC')->get()->getResultArray();
        $from = $sem ? $sem[0]['start_date'] : $first;
        $to   = $sem ? end($sem)['end_date'] : $last;
        $sched = [];
        foreach (Schedules::grouped($yid, null, $uid) as $dow => $items) {
            $sched[$dow] = $items;
        }
        $agendas = [];
        $at = $db->prefixTable('attendances');
        foreach ($db->table('teaching_agendas a')->select('a.id, a.agenda_date, a.class_group_id, a.subject_id, a.topic, a.lesson_hours, a.status, a.completion, cg.name AS class_name, s.name AS subject_name, s.code AS subject_code, lo.code AS tp_code, lo.description AS tp_desc')
            ->select("(SELECT COUNT(*) FROM {$at} x WHERE x.teaching_agenda_id = a.id) AS att_total, (SELECT COUNT(*) FROM {$at} x WHERE x.teaching_agenda_id = a.id AND x.status = 'H') AS att_present", false)
            ->join('class_groups cg', 'cg.id = a.class_group_id')->join('subjects s', 's.id = a.subject_id')
            ->join('learning_objectives lo', 'lo.id = a.learning_objective_id', 'left')
            ->where('a.user_id', $uid)->where('a.deleted_at', null)->where('a.agenda_date >=', $first)->where('a.agenda_date <=', $last)
            ->orderBy('a.agenda_date', 'ASC')->get()->getResultArray() as $a) {
            $agendas[$a['agenda_date']][] = $a;
        }
        $out = [];
        for ($t = strtotime($first); $t <= strtotime($last); $t += 86400) {
            $d       = date('Y-m-d', $t);
            $holiday = null;
            foreach ($byDay[$d] ?? [] as $e) {
                if ($e['event_type'] === 'libur') {
                    $holiday = $e['title'];
                }
            }
            $slots = [];
            $used  = [];
            if ($d >= $from && $d <= $to) {
                foreach ($sched[(int) date('N', $t)] ?? [] as $s) {
                    $ag = null;
                    foreach ($agendas[$d] ?? [] as $i => $a) {
                        if (! isset($used[$i]) && (int) $a['class_group_id'] === (int) $s['class_group_id'] && (int) $a['subject_id'] === (int) $s['subject_id']) {
                            $ag       = $a;
                            $used[$i] = true;
                            break;
                        }
                    }
                    $slots[] = ['class' => $s['class_name'], 'subject' => $s['subject_name'], 'jam' => (string) ($s['lesson_hours'] ?? ''),
                        'time' => fmt_time($s['start_time']) . '–' . fmt_time($s['end_time']), 'done' => (bool) $ag, 'topic' => $ag['topic'] ?? ''] + self::agendaInfo($ag) + [
                        'code' => (string) ($s['subject_code'] ?? ''), 'clr' => subject_color($s['subject_code'] ?: $s['subject_name']),
                        'url' => $ag ? site_url('agendas/' . $ag['id'] . '/edit')
                            : site_url('agendas/new?' . http_build_query(['class_group_id' => $s['class_group_id'], 'subject_id' => $s['subject_id'], 'agenda_date' => $d]))];
                }
            }
            $extra = [];
            foreach ($agendas[$d] ?? [] as $i => $a) {
                if (! isset($used[$i])) { // agenda di luar jadwal (mis. pengganti)
                    $extra[] = ['class' => $a['class_name'], 'subject' => $a['subject_name'], 'topic' => $a['topic'], 'jam' => (string) ($a['lesson_hours'] ?? ''),
                        'code' => (string) ($a['subject_code'] ?? ''), 'clr' => subject_color($a['subject_code'] ?: $a['subject_name']), 'url' => site_url('agendas/' . $a['id'] . '/edit')] + self::agendaInfo($a);
                }
            }
            if ($slots || $extra) {
                $out[$d] = ['slots' => $slots, 'extra' => $extra, 'holiday' => $holiday,
                    'done' => count(array_filter($slots, static fn ($s) => $s['done'])), 'total' => count($slots)];
            }
        }

        return $out;
    }

    /** Hari libur yang memuat tanggal tertentu (dipakai peringatan agenda). */
    /** Ringkasan agenda untuk popup kalender: TP, kehadiran, status, tautan hapus. */
    private static function agendaInfo(?array $a): array
    {
        if (! $a) {
            return ['id' => null, 'tp' => '', 'att' => '', 'status' => '', 'statusLabel' => '', 'complete' => '', 'del' => ''];
        }
        $labels = ['selesai' => 'Terlaksana', 'terlambat' => 'Guru terlambat', 'batal' => 'Berhalangan', 'draf' => 'Draf'];

        return [
            'id'          => (int) $a['id'],
            'tp'          => trim(($a['tp_code'] ? $a['tp_code'] . ' · ' : '') . mb_strimwidth((string) ($a['tp_desc'] ?? ''), 0, 90, '…')),
            'att'         => (int) $a['att_total'] > 0 ? (int) $a['att_present'] . '/' . (int) $a['att_total'] : '',
            'status'      => (string) $a['status'],
            'statusLabel' => $labels[$a['status']] ?? (string) $a['status'],
            'complete'    => (string) ($a['completion'] ?? ''),
            'del'         => site_url('agendas/' . $a['id'] . '/delete'),
        ];
    }

    public static function holidayOn(string $date): ?array
    {
        return (new CalendarEventModel())->where('event_type', 'libur')
            ->where('start_date <=', $date)->where('end_date >=', $date)->first();
    }
}
