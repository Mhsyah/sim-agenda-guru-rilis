<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Backup & restore portabel (JSON) untuk MariaDB/MySQL dan PostgreSQL.
 * Satu-satunya bagian khusus vendor (reset sequence PostgreSQL) diisolasi di resetSequences().
 */
final class BackupService
{
    /** Urutan sesuai dependensi foreign key (induk lebih dulu). */
    public const TABLES = [
        'users', 'school_profiles', 'academic_years', 'semesters', 'subjects', 'user_subjects', 'class_groups', 'user_teaching', 'students',
        'student_enrollments', 'learning_outcomes', 'learning_objectives', 'outcome_objectives', 'curriculum_documents',
        'school_calendar_events', 'teaching_schedules', 'duty_rosters', 'subject_loads', 'time_slots', 'teaching_agendas', 'attendances', 'assessment_types', 'assessments',
        'grading_configs', 'grading_config_items', 'assessment_scores', 'principal_reviews', 'audit_logs',
    ];

    public const FORMAT = 1;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public static function dir(): string
    {
        $dir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . 'index.html', '');
            file_put_contents($dir . '.htaccess', "Require all denied\n");
        }

        return $dir;
    }

    public function export(): string
    {
        $tables = [];
        foreach (self::TABLES as $t) {
            $tables[$t] = $this->db->table($t)->orderBy('id', 'ASC')->get()->getResultArray();
        }
        $payload = json_encode($tables, JSON_UNESCAPED_UNICODE);

        return json_encode([
            'app'        => 'agenda-guru',
            'version'    => app_version(),
            'format'     => self::FORMAT,
            'driver'     => $this->db->DBDriver,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'checksum'   => hash('sha256', $payload),
            'counts'     => array_map('count', $tables),
            'tables'     => $tables,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** Simpan backup ke writable/backups dan kembalikan nama file. */
    public function store(string $label = ''): string
    {
        $name = 'agenda-guru-' . date('Ymd-His') . ($label ? '-' . preg_replace('/[^a-z0-9-]/', '', strtolower($label)) : '') . '.json';
        file_put_contents(self::dir() . $name, $this->export(), LOCK_EX);
        @chmod(self::dir() . $name, 0640);

        return $name;
    }

    /** @return list<array{name: string, size: int, time: int}> */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . 'agenda-guru-*.json') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    public static function path(string $name): ?string
    {
        if (! preg_match('/^agenda-guru-[A-Za-z0-9-]+\.json$/', $name)) {
            return null;
        }
        $p = self::dir() . $name;

        return is_file($p) ? $p : null;
    }

    /** @return array{data: array, counts: array<string,int>} */
    public function validate(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ($data['app'] ?? '') !== 'agenda-guru' || ! isset($data['tables']) || ! is_array($data['tables'])) {
            throw new RuntimeException('File bukan backup Agenda Guru yang valid.');
        }
        if ((int) ($data['format'] ?? 0) !== self::FORMAT) {
            throw new RuntimeException('Format backup tidak didukung oleh versi aplikasi ini.');
        }
        if (isset($data['checksum']) && hash('sha256', json_encode($data['tables'], JSON_UNESCAPED_UNICODE)) !== $data['checksum']) {
            throw new RuntimeException('Checksum backup tidak cocok. File mungkin rusak atau telah diubah.');
        }
        $counts = [];
        foreach ($data['tables'] as $t => $rows) {
            if (! in_array($t, self::TABLES, true)) {
                throw new RuntimeException('Tabel tidak dikenal di backup: ' . $t);
            }
            if (! is_array($rows)) {
                throw new RuntimeException('Data tabel ' . $t . ' tidak valid.');
            }
            $cols = $this->db->getFieldNames($t);
            foreach ($rows as $r) {
                if (! is_array($r) || array_diff(array_keys($r), $cols)) {
                    throw new RuntimeException('Kolom pada tabel ' . $t . ' tidak cocok dengan skema saat ini.');
                }
            }
            $counts[$t] = count($rows);
        }
        if (empty($data['tables']['users'])) {
            throw new RuntimeException('Backup tidak memuat akun pengguna; pemulihan dibatalkan agar Anda tidak terkunci.');
        }

        return ['data' => $data, 'counts' => $counts];
    }

    /** Ganti seluruh data dengan isi backup dalam satu transaksi. */
    public function restore(string $json): array
    {
        ['data' => $data, 'counts' => $counts] = $this->validate($json);

        $this->db->transException(true)->transStart();
        foreach (array_reverse(self::TABLES) as $t) {
            $this->db->table($t)->emptyTable();
        }
        foreach (self::TABLES as $t) {
            $rows = $data['tables'][$t] ?? [];
            foreach (array_chunk($rows, 200) as $chunk) {
                $this->db->table($t)->insertBatch($chunk);
            }
        }
        $this->resetSequences();
        $this->db->transComplete();

        return $counts;
    }

    /** Adapter khusus PostgreSQL: setel ulang sequence SERIAL setelah insert dengan id eksplisit. */
    private function resetSequences(): void
    {
        if ($this->db->DBDriver !== 'Postgre') {
            return; // MySQL/MariaDB menyesuaikan AUTO_INCREMENT secara otomatis.
        }
        foreach (self::TABLES as $t) {
            $tbl = $this->db->prefixTable($t);
            $this->db->query("SELECT setval(pg_get_serial_sequence('{$tbl}', 'id'), COALESCE((SELECT MAX(id) FROM {$tbl}), 0) + 1, false)");
        }
    }
}
