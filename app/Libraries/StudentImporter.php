<?php

namespace App\Libraries;

use App\Controllers\Students;
use App\Models\ClassGroupModel;
use App\Models\StudentModel;
use Throwable;

/**
 * Impor siswa dari CSV dengan validasi per baris.
 * Kolom: nis, nisn, full_name (wajib), gender (L/P), birth_date (YYYY-MM-DD atau DD/MM/YYYY), class.
 * Siswa dengan NIS/NISN yang sudah ada akan diperbarui, bukan diduplikasi.
 */
final class StudentImporter
{
    private const ALIASES = [
        'nis' => 'nis', 'nisn' => 'nisn',
        'full_name' => 'full_name', 'nama' => 'full_name', 'nama_lengkap' => 'full_name', 'name' => 'full_name',
        'gender' => 'gender', 'jk' => 'gender', 'l/p' => 'gender', 'jenis_kelamin' => 'gender',
        'birth_date' => 'birth_date', 'tanggal_lahir' => 'birth_date', 'tgl_lahir' => 'birth_date',
        'class' => 'class', 'kelas' => 'class',
    ];

    /** @return array{ok: int, created: int, updated: int, errors: list<array{line: int, reason: string}>, total: int} */
    public function import(string $content, int $yearId, ?int $defaultClassId): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines   = array_values(array_filter(explode("\n", $content), static fn ($l) => trim($l) !== ''));
        $result  = ['ok' => 0, 'created' => 0, 'updated' => 0, 'restored' => 0, 'errors' => [], 'total' => 0, 'rows' => [], 'ignored' => []];
        if (count($lines) < 2) {
            $result['errors'][] = ['line' => 1, 'reason' => 'File kosong atau hanya berisi header.'];

            return $result;
        }
        $delim  = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $rawHead = str_getcsv($lines[0], $delim, '"', '');
        $header  = array_map(static fn ($h) => self::ALIASES[strtolower(trim(str_replace(' ', '_', $h)))] ?? null, $rawHead);
        foreach ($rawHead as $i => $h) {
            if ($header[$i] === null && trim($h) !== '') {
                $result['ignored'][] = trim($h);
            }
        }
        if (! in_array('full_name', $header, true)) {
            $result['errors'][] = ['line' => 1, 'reason' => 'Header wajib memuat kolom full_name (atau "nama").'];

            return $result;
        }

        $classes = [];
        foreach ((new ClassGroupModel())->where('academic_year_id', $yearId)->findAll() as $c) {
            $classes[mb_strtolower(trim($c['name']))] = (int) $c['id'];
        }
        $seen = ['nis' => [], 'nisn' => [], 'name' => []];

        foreach (array_slice($lines, 1) as $i => $line) {
            $lineNo = $i + 2;
            $result['total']++;
            $cells = str_getcsv($line, $delim, '"', '');
            $row   = [];
            foreach ($header as $idx => $key) {
                if ($key) {
                    $row[$key] = trim((string) ($cells[$idx] ?? ''));
                }
            }

            $shown = array_intersect_key($row, array_flip(['full_name', 'nis', 'nisn', 'gender', 'birth_date', 'class']));
            $err   = $this->validateRow($row, $classes, $seen, $defaultClassId);
            if ($err) {
                $dup = str_starts_with($err, 'DUP: ');
                $err = $dup ? substr($err, 5) : $err;
                $result['duplicates'] = ($result['duplicates'] ?? 0) + (int) $dup;
                $result['errors'][] = ['line' => $lineNo, 'reason' => $err];
                $result['rows'][]   = ['line' => $lineNo, 'data' => $shown, 'status' => $dup ? 'duplicate' : 'error', 'reason' => $err];

                continue;
            }

            try {
                $data = [
                    'nis'        => $row['nis'] ?? '' ?: null,
                    'nisn'       => $row['nisn'] ?? '' ?: null,
                    'full_name'  => preg_replace('/\s+/', ' ', $row['full_name']),
                    'gender'     => isset($row['gender']) && $row['gender'] !== '' ? strtoupper($row['gender'][0]) : null,
                    'birth_date' => $row['birth_date_norm'] ?? null,
                    'status'     => 'aktif',
                ];
                // Cari juga siswa yang pernah dihapus (arsip): dipulihkan, bukan dibuat ganda/bentrok.
                $existing = null;
                if ($data['nis']) {
                    $existing = (new StudentModel())->withDeleted()->where('nis', $data['nis'])->first();
                }
                if (! $existing && $data['nisn']) {
                    $existing = (new StudentModel())->withDeleted()->where('nisn', $data['nisn'])->first();
                }
                if ($existing) {
                    $wasDeleted = ! empty($existing['deleted_at']);
                    (new StudentModel())->withDeleted()->update($existing['id'], $data);
                    if ($wasDeleted) {
                        db_connect()->table('students')->where('id', $existing['id'])->update(['deleted_at' => null]);
                    }
                    $sid    = (int) $existing['id'];
                    $action = $wasDeleted ? 'restored' : 'updated';
                    $note   = $wasDeleted ? 'Siswa ini pernah dihapus — dipulihkan & diperbarui.' : 'NIS/NISN sudah terdaftar — data siswa diperbarui.';
                } else {
                    $sid    = (int) (new StudentModel())->insert($data);
                    $action = 'created';
                    $note   = null;
                }
                $result[$action]++;
                $classId = $row['class_id'] ?? $defaultClassId;
                if ($classId) {
                    Students::enroll($sid, $classId);
                }
                $result['ok']++;
                $result['rows'][] = ['line' => $lineNo, 'data' => $shown, 'status' => $action, 'reason' => $note];
            } catch (Throwable $e) {
                $result['errors'][] = ['line' => $lineNo, 'reason' => 'Gagal disimpan: data bentrok dengan siswa lain.'];
                $result['rows'][]   = ['line' => $lineNo, 'data' => $shown, 'status' => 'error', 'reason' => 'Gagal disimpan: data bentrok dengan siswa lain.'];
                log_message('error', 'Import siswa baris ' . $lineNo . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    private function validateRow(array &$row, array $classes, array &$seen, ?int $defaultClassId): ?string
    {
        if (($row['full_name'] ?? '') === '') {
            return 'Nama lengkap kosong.';
        }
        if (mb_strlen($row['full_name']) > 150) {
            return 'Nama lengkap melebihi 150 karakter.';
        }
        foreach (['nis' => 30, 'nisn' => 20] as $k => $max) {
            $v = $row[$k] ?? '';
            if ($v === '') {
                continue;
            }
            if (mb_strlen($v) > $max) {
                return strtoupper($k) . ' melebihi ' . $max . ' karakter.';
            }
            if ($k === 'nisn' && ! ctype_digit($v)) {
                return 'NISN hanya boleh berisi angka.';
            }
            if (isset($seen[$k][$v])) {
                return 'DUP: ' . strtoupper($k) . ' ' . $v . ' muncul lebih dari sekali di file.';
            }
            $seen[$k][$v] = true;
        }
        // Nama kembar di file tanpa NIS/NISN → hampir pasti baris ganda.
        if (($row['nis'] ?? '') === '' && ($row['nisn'] ?? '') === '') {
            $nk = mb_strtolower(preg_replace('/\s+/', ' ', trim($row['full_name'])));
            if (isset($seen['name'][$nk])) {
                return 'DUP: ' . 'Nama "' . $row['full_name'] . '" muncul lebih dari sekali tanpa NIS/NISN (kemungkinan data ganda).';
            }
            $seen['name'][$nk] = true;
        }
        if (($g = strtoupper($row['gender'] ?? '')) !== '' && ! in_array($g[0], ['L', 'P'], true)) {
            return 'Jenis kelamin harus L atau P.';
        }
        if (($d = $row['birth_date'] ?? '') !== '') {
            $norm = null;
            if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $d, $m)) {
                $norm = [$m[1], $m[2], $m[3]];
            } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $d, $m)) {
                $norm = [$m[3], $m[2], $m[1]];
            }
            if (! $norm || ! checkdate((int) $norm[1], (int) $norm[2], (int) $norm[0])) {
                return 'Tanggal lahir tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).';
            }
            $row['birth_date_norm'] = sprintf('%04d-%02d-%02d', $norm[0], $norm[1], $norm[2]);
        }
        if (($c = $row['class'] ?? '') !== '') {
            $key = mb_strtolower($c);
            if (! isset($classes[$key])) {
                return 'Kelas "' . $c . '" tidak ditemukan pada tahun ajaran aktif.';
            }
            $row['class_id'] = $classes[$key];
        }

        return null;
    }
}
