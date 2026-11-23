<?php

namespace App\Libraries;

use DateTime;
use DateTimeZone;

/** Riwayat aktivitas pengguna dari log audit, dalam kalimat yang mudah dibaca. */
final class ActivityFeed
{
    /** entity_type => [kata benda, ikon] */
    private const NOUNS = [
        'teaching_agenda'    => ['agenda', 'pen'],
        'assessment'         => ['penilaian', 'chart'],
        'learning_objective' => ['TP', 'check'],
        'learning_outcome'   => ['CP', 'target'],
        'teaching_schedule'  => ['jadwal', 'clock'],
        'principal_review'   => ['kesan kepala sekolah', 'sparkle'],
        'grading_config'     => ['bobot nilai', 'layers'],
        'student'            => ['siswa', 'users'],
        'report'             => ['laporan', 'file'],
        'journal'            => ['Buku Jurnal', 'printer'],
        'calendar_event'     => ['kegiatan kalender', 'calendar'],
        'subject'            => ['mata pelajaran', 'book'],
        'class_group'        => ['kelas', 'grid'],
        'user'               => ['pengguna', 'users'],
        'duty_roster'        => ['jadwal piket', 'shield'],
        'promotion'          => ['kelulusan & kenaikan kelas', 'sparkle'],
    ];

    private const VERBS = [
        'create' => ['Menambah', 'success'], 'update' => ['Mengubah', 'info'], 'delete' => ['Menghapus', 'danger'],
        'archive' => ['Menghapus', 'danger'], 'export' => ['Mengekspor', 'info'], 'print' => ['Mencetak', 'info'],
    ];

    /** Tindakan khusus: action|entity => [kalimat, ikon, nada] */
    private const SPECIAL = [
        'login|user'                   => ['Masuk ke aplikasi', 'logout', 'neutral'],
        'logout|user'                  => ['Keluar dari aplikasi', 'logout', 'neutral'],
        'password_change|user'         => ['Mengganti kata sandi', 'shield', 'warning'],
        'update|user_photo'            => ['Mengganti foto profil', 'camera', 'info'],
        'update|user_profile'          => ['Memperbarui profil', 'users', 'info'],
        'scores_update|assessment'     => ['Menginput nilai', 'chart', 'success'],
        'attendance_update|teaching_agenda' => ['Memperbarui kehadiran siswa', 'check', 'success'],
        'update|user_teaching'         => ['Mengubah kelas yang diajar', 'grid', 'info'],
    ];

    /** Aksi yang tidak ditampilkan (bukan aktivitas pengguna sendiri). */
    private const HIDDEN = ['login_failed', 'login_blocked'];

    /** @return list<array{icon: string, tone: string, text: string, detail: string, url: ?string, at: string, ago: string}> */
    public static function forUser(int $userId, int $limit = 10, int $offset = 0): array
    {
        $rows = db_connect()->table('audit_logs')->where('user_id', $userId)->whereNotIn('action', self::HIDDEN)
            ->orderBy('id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        return array_map([self::class, 'describe'], $rows);
    }

    public static function countForUser(int $userId): int
    {
        return db_connect()->table('audit_logs')->where('user_id', $userId)->whereNotIn('action', self::HIDDEN)->countAllResults();
    }

    /** @return array{icon: string, tone: string, text: string, detail: string, url: ?string, at: string, ago: string} */
    public static function describe(array $r): array
    {
        $key = $r['action'] . '|' . $r['entity_type'];
        $new = json_decode((string) $r['new_values'], true) ?: [];
        $old = json_decode((string) $r['old_values'], true) ?: [];
        if (isset(self::SPECIAL[$key])) {
            [$text, $icon, $tone] = self::SPECIAL[$key];
        } else {
            [$noun, $icon] = self::NOUNS[$r['entity_type']] ?? [str_replace('_', ' ', $r['entity_type']), 'list'];
            [$verb, $tone] = self::VERBS[$r['action']] ?? [ucfirst(str_replace('_', ' ', $r['action'])), 'neutral'];
            $text = $verb . ' ' . $noun;
        }
        $detail = '';
        foreach (['topic', 'title', 'full_name', 'name', 'code'] as $f) {
            $v = $new[$f] ?? $old[$f] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $detail = mb_strimwidth(trim($v), 0, 70, '…');
                break;
            }
        }
        $id  = (int) ($r['entity_id'] ?? 0);
        $url = null;
        if ($id && ! in_array($r['action'], ['delete', 'archive'], true)) {
            $url = match ($r['entity_type']) {
                'teaching_agenda'    => site_url('agendas/' . $id . '/edit'),
                'assessment'         => site_url('assessments/' . $id . '/scores'),
                'learning_objective' => site_url('objectives/' . $id . '/edit'),
                'learning_outcome'   => site_url('outcomes/' . $id . '/edit'),
                default              => null,
            };
        }
        [$at, $ago] = self::when((string) $r['created_at']);

        return ['icon' => $icon, 'tone' => $tone, 'text' => $text, 'detail' => $detail, 'url' => $url, 'at' => $at, 'ago' => $ago];
    }

    /** Waktu UTC di log → [tanggal lokal lengkap, "x menit lalu"]. */
    public static function when(string $utc): array
    {
        if ($utc === '') {
            return ['—', ''];
        }
        $tz = new DateTimeZone(config('App')->appTimezone ?: 'Asia/Jakarta');
        $d  = (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone($tz);
        $s  = max(0, time() - $d->getTimestamp());
        $ago = match (true) {
            $s < 60     => 'baru saja',
            $s < 3600   => intdiv($s, 60) . ' menit lalu',
            $s < 86400  => intdiv($s, 3600) . ' jam lalu',
            $s < 172800 => 'kemarin',
            $s < 604800 => intdiv($s, 86400) . ' hari lalu',
            default     => fmt_date($d->format('Y-m-d')),
        };

        return [fmt_date($d->format('Y-m-d')) . ' ' . $d->format('H:i'), $ago];
    }
}
