<?php

namespace App\Libraries;

use App\Controllers\Schedules;
use Throwable;

/**
 * Pengingat otomatis untuk lonceng notifikasi di bar atas.
 * Setiap item punya kunci tetap (mis. "piket:2026-11-09:pagi") sehingga status "sudah dibaca" bisa disimpan per akun;
 * kunci memuat tanggal/jumlah agar pengingat muncul lagi bila keadaannya berubah.
 */
final class Notifications
{
    /** @return list<array{key: string, icon: string, tone: string, title: string, body: string, url: ?string, read: bool}> */
    public static function forUser(?array $user): array
    {
        if (! $user) {
            return [];
        }
        $uid = (int) $user['id'];
        try {
            // Daftar notifikasi dihitung paling sering tiap 90 detik (dipanggil di setiap halaman); status "dibaca" selalu terbaru.
            $build = static fn () => ($user['role'] ?? '') === 'admin' ? self::admin() : self::teacher($uid);
            $items = ENVIRONMENT === 'testing' ? $build() : cache()->remember('notif_' . ($user['role'] ?? '') . '_' . $uid, 90, $build);            $read  = $items ? array_flip(array_column(db_connect()->table('notification_reads')->select('nkey')->where('user_id', $uid)
                ->whereIn('nkey', array_column($items, 'key'))->get()->getResultArray(), 'nkey')) : [];
            foreach ($items as &$i) {
                $i['read'] = isset($read[$i['key']]);
            }
            unset($i);
            usort($items, static fn ($a, $b) => $a['read'] <=> $b['read']); // belum dibaca di atas
        } catch (Throwable $e) {
            log_message('error', 'Notifikasi gagal: ' . $e->getMessage());
            $items = [];
        }

        return $items;
    }

    public static function unread(?array $user): int
    {
        return count(array_filter(self::forUser($user), static fn ($i) => ! $i['read']));
    }

    /** Tandai satu notifikasi sebagai sudah dibaca; kembalikan tautannya (null bila tidak ada / kunci tak dikenal). */
    public static function markRead(array $user, string $key): ?string
    {
        foreach (self::forUser($user) as $i) {
            if ($i['key'] !== $key) {
                continue;
            }
            if (! $i['read']) {
                db_connect()->table('notification_reads')->insert(['user_id' => (int) $user['id'], 'nkey' => $key, 'read_at' => gmdate('Y-m-d H:i:s')]);
            }

            return $i['url'];
        }

        return null;
    }

    /** Tandai semua notifikasi saat ini sebagai sudah dibaca. */
    public static function markAllRead(array $user): int
    {
        $db  = db_connect();
        $uid = (int) $user['id'];
        $n   = 0;
        foreach (self::forUser($user) as $i) {
            if (! $i['read']) {
                $db->table('notification_reads')->insert(['user_id' => $uid, 'nkey' => $i['key'], 'read_at' => gmdate('Y-m-d H:i:s')]);
                $n++;
            }
        }
        // bersihkan penanda lama (> 60 hari) agar tabel tetap kecil
        $db->table('notification_reads')->where('user_id', $uid)->where('read_at <', gmdate('Y-m-d H:i:s', time() - 60 * 86400))->delete();

        return $n;
    }

    private static function item(string $key, string $icon, string $tone, string $title, string $body = '', ?string $url = null): array
    {
        return compact('key', 'icon', 'tone', 'title', 'body', 'url');
    }

    /** @return list<array> */
    private static function teacher(int $uid): array
    {
        $out   = [];
        $yid   = Period::yearId();
        $sid   = Period::semesterId();
        $today = date('Y-m-d');
        $dow   = (int) date('N');
        $db    = db_connect();

        if (session('pw_default')) {
            $out[] = self::item('pw-default', 'shield', 'danger', 'Ganti kata sandi awal', 'Akun Anda masih memakai kata sandi awal.', site_url('settings/security'));
        }
        if ($yid) {
            // Piket hari ini
            foreach (DutyRoster::forUser($uid, $yid) as $p) {
                if ($p['day'] === $dow) {
                    $out[] = self::item('piket:' . $today . ':' . $p['shift'], 'shield', 'warning', 'Hari ini Anda piket ' . (DutyRoster::SHIFTS[$p['shift']] ?? $p['shift']), 'Sesuai jadwal piket sekolah.', site_url('schedules'));
                }
            }
            // Jadwal hari ini yang sudah lewat tetapi agendanya belum ditulis
            $done = [];
            foreach ($db->table('teaching_agendas')->select('class_group_id, subject_id')->where('user_id', $uid)->where('agenda_date', $today)->where('deleted_at', null)->get()->getResultArray() as $w) {
                $done[$w['class_group_id'] . '-' . $w['subject_id']] = true;
            }
            $now = date('H:i:s');
            foreach (Schedules::grouped($yid, $dow, $uid)[$dow] ?? [] as $s) {
                if ($s['end_time'] <= $now && ! isset($done[$s['class_group_id'] . '-' . $s['subject_id']])) {
                    $out[] = self::item('agenda:' . $today . ':' . $s['id'], 'pen', 'warning', 'Agenda ' . $s['class_name'] . ' belum ditulis',
                        $s['subject_name'] . ' · ' . fmt_time($s['start_time']) . '–' . fmt_time($s['end_time']),
                        site_url('agendas/new?class_group_id=' . $s['class_group_id'] . '&subject_id=' . $s['subject_id'] . '&agenda_date=' . $today));
                }
            }
            // Nilai belum lengkap
            if ($sid) {
                // hanya mapel yang diajarkan (dihitung sebelum membangun kueri beralias)
                $taught = array_map('intval', array_column($db->table('user_subjects')->select('subject_id')->where('user_id', $uid)->get()->getResultArray(), 'subject_id'));
                $sc = $db->prefixTable('assessment_scores');
                $en = $db->prefixTable('student_enrollments');
                $as = $db->prefixTable('assessments');
                $st = $db->prefixTable('students');
                $rows = $db->table('assessments')->select('assessments.id, assessments.title, cg.name AS class_name')
                    ->select("(SELECT COUNT(*) FROM {$sc} x WHERE x.assessment_id = {$as}.id) AS graded,
                        (SELECT COUNT(*) FROM {$en} e JOIN {$st} st ON st.id = e.student_id AND st.deleted_at IS NULL WHERE e.class_group_id = {$as}.class_group_id AND e.status = 'aktif') AS enrolled", false)
                    ->join('class_groups cg', 'cg.id = assessments.class_group_id')
                    ->where('assessments.semester_id', $sid)->where('assessments.user_id', $uid)->where('assessments.deleted_at', null);
                if ($taught) {
                    $rows->whereIn('assessments.subject_id', $taught);
                }
                $rows = $rows->orderBy('assessments.assessment_date', 'DESC')->limit(30)->get()->getResultArray();
                foreach (array_slice(array_filter($rows, static fn ($r) => (int) $r['graded'] < (int) $r['enrolled']), 0, 3) as $r) {
                    $out[] = self::item('nilai:' . $r['id'] . ':' . $r['graded'], 'chart', 'info', 'Nilai belum lengkap: ' . $r['title'],
                        $r['class_name'] . ' · ' . $r['graded'] . '/' . $r['enrolled'] . ' siswa', site_url('assessments/' . $r['id'] . '/scores'));
                }
            }
        }
        // Kegiatan kalender 3 hari ke depan
        foreach ($db->table('school_calendar_events')->where('start_date >=', $today)->where('start_date <=', date('Y-m-d', strtotime('+3 days')))
            ->orderBy('start_date', 'ASC')->limit(5)->get()->getResultArray() as $e) {
            $out[] = self::item('event:' . $e['id'], 'calendar', 'info', $e['title'],
                ($e['start_date'] === $today ? 'Hari ini' : fmt_date($e['start_date'])) . ($e['end_date'] !== $e['start_date'] ? ' – ' . fmt_date($e['end_date']) : ''), site_url('calendar'));
        }

        return $out;
    }

    /** @return list<array> */
    private static function admin(): array
    {
        $out = [];
        $db  = db_connect();
        $yid = Period::yearId();

        $never = $db->table('users')->where('role', 'teacher')->where('is_active', 1)->where('last_login_at', null)->countAllResults();
        if ($never) {
            $out[] = self::item('nologin:' . $never, 'users', 'info', $never . ' guru belum pernah login', 'Bagikan email & kata sandi awal kepada guru.', site_url('users?role=teacher'));
        }
        if ($yid) {
            $noSched = $db->table('users u')->where('u.role', 'teacher')->where('u.is_active', 1)
                ->where('NOT EXISTS (SELECT 1 FROM ' . $db->prefixTable('teaching_schedules') . ' t WHERE t.user_id = u.id AND t.academic_year_id = ' . (int) $yid . ')', null, false)
                ->countAllResults();
            if ($noSched) {
                $out[] = self::item('nosched:' . $yid . ':' . $noSched, 'clock', 'warning', $noSched . ' guru belum punya jadwal', 'Tahun pelajaran aktif.', site_url('teacher-schedules?mode=guru'));
            }
            $noClass = $db->table('students s')->where('s.deleted_at', null)->where('s.status', 'aktif')
                ->where('NOT EXISTS (SELECT 1 FROM ' . $db->prefixTable('student_enrollments') . ' e WHERE e.student_id = s.id AND e.academic_year_id = ' . (int) $yid . ')', null, false)
                ->countAllResults();
            if ($noClass) {
                $out[] = self::item('noclass:' . $yid . ':' . $noClass, 'grid', 'warning', $noClass . ' siswa aktif belum punya kelas', 'Tempatkan siswa ke kelas/rombel.', site_url('students'));
            }
        }
        if ($yid && ($over = \App\Libraries\ClassCapacity::over($yid))) {
            $out[] = self::item('cap:' . $yid . ':' . implode(',', array_map(static fn ($c) => $c['id'] . '-' . $c['n'], $over)), 'grid', 'warning',
                count($over) . ' rombel melebihi ' . \App\Libraries\ClassCapacity::MAX . ' siswa', implode(', ', array_column($over, 'name')) . ' — batas Kemendikdasmen.', site_url('classes'));
        }
        if ($pg = \App\Libraries\ClassCapacity::pendingGraduation()) {
            $out[] = self::item('grad:' . $pg['year'] . ':' . $pg['count'], 'users', 'warning', $pg['count'] . ' siswa kelas akhir belum diluluskan',
                'T.P. ' . $pg['year'] . ' — proses kelulusan.', site_url('promotions'));
        }
        $upd = \App\Libraries\AppUpdate::autoCheck();
        if ($new = \App\Libraries\AppUpdate::available($upd)) {
            $out[] = self::item('update:' . $new['version'], 'zap', 'info', 'Versi ' . $new['version'] . ' tersedia', 'Terpasang v' . app_version() . ' — lihat catatan rilis.', site_url('system-status#ss-ver'));
        } elseif ($n = \App\Libraries\AppUpdate::pendingCommits($upd)) {
            $out[] = self::item('update-commit:' . ($upd['commit']['sha'] ?? ''), 'zap', 'info', $n . ' pembaruan kode di GitHub', 'Belum terpasang di aplikasi ini.', site_url('system-status#ss-ver'));
        }
        $last = BackupService::list()[0]['time'] ?? null;
        if (! $last || $last < time() - 7 * 86400) {
            $out[] = self::item('backup:' . date('o-W'), 'database', 'warning', $last ? 'Backup terakhir lebih dari 7 hari lalu' : 'Belum ada backup data',
                $last ? 'Terakhir: ' . fmt_date(date('Y-m-d', $last)) : 'Buat backup secara berkala.', site_url('settings/backup'));
        }
        $log = WRITEPATH . 'logs/log-' . date('Y-m-d') . '.log';
        $err = is_file($log) ? preg_match_all('/^(ERROR|CRITICAL|ALERT|EMERGENCY) - /m', (string) @file_get_contents($log)) : 0;
        if ($err) {
            $out[] = self::item('errors:' . date('Y-m-d') . ':' . $err, 'alert', 'danger', $err . ' galat tercatat hari ini', 'Periksa Status Sistem.', site_url('system-status'));
        }

        return $out;
    }
}
