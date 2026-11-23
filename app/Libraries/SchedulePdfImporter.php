<?php

namespace App\Libraries;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Baca PDF jadwal pelajaran sekolah (format kisi: Hari | Jam | Pukul | tiap kelas KD MP | Piket),
 * mis. "JADWAL PAGI 2627.pdf". Menghasilkan draf: blok jadwal, struktur jam & kegiatan, piket,
 * serta daftar masalah + saran perbaikan (kode guru/mapel yang tidak sesuai pembagian mengajar).
 */
final class SchedulePdfImporter
{
    /** Singkatan mapel di PDF → kode mapel di aplikasi */
    private const MP_ALIAS = ['BIND' => 'BIN', 'BINDO' => 'BIN', 'BINDONESIA' => 'BIN', 'BING' => 'BIG', 'BINGG' => 'BIG', 'PRKY' => 'PKY', 'PRAKARYA' => 'PKY',
        'SB' => 'SBD', 'SBK' => 'SBD', 'SENBUD' => 'SBD', 'PKN' => 'PP', 'PPKN' => 'PP', 'PENJAS' => 'PJOK', 'TIK' => 'INF'];

    /** @return array{draft: array, issues: list<array>, summary: array} */
    public function parse(string $file, int $yearId, bool $applySuggestions = true): array
    {
        try {
            $pages = (new Parser())->parseFile($file)->getPages();
        } catch (Throwable $e) {
            throw new RuntimeException('PDF tidak dapat dibaca: ' . $e->getMessage());
        }
        if (! $pages) {
            throw new RuntimeException('PDF kosong.');
        }
        $items = [];
        foreach ($pages[0]->getDataTm() as $it) {
            $t = trim((string) $it[1]);
            if ($t !== '') {
                $items[] = ['x' => (float) $it[0][4], 'y' => (float) $it[0][5], 't' => $t, 'rot' => abs((float) $it[0][1]) > 0.001];
            }
        }
        $items = array_values(array_filter($items, static fn ($i) => ! $i['rot']));

        // ---------- Kepala kolom kelas & sub-kolom KD/MP
        $heads = array_values(array_filter($items, static fn ($i) => preg_match('/^(\d{1,2}|[IVX]+)\s?[A-Z]$/', $i['t'])));
        if (count($heads) < 2) {
            throw new RuntimeException('Kolom kelas tidak ditemukan. Pastikan PDF berformat jadwal sekolah (kolom kelas berisi KD dan MP).');
        }
        $headY = self::mode(array_map(static fn ($i) => round($i['y']), $heads));
        $heads = array_values(array_filter($heads, static fn ($i) => abs($i['y'] - $headY) < 3));
        usort($heads, static fn ($a, $b) => $a['x'] <=> $b['x']);
        $subs = array_values(array_filter($items, static fn ($i) => in_array($i['t'], ['KD', 'MP'], true) && $i['y'] < $headY && $i['y'] > $headY - 20));
        usort($subs, static fn ($a, $b) => $a['x'] <=> $b['x']);
        if (count($subs) !== 2 * count($heads)) {
            throw new RuntimeException('Sub-kolom KD/MP tidak lengkap (' . count($subs) . ' untuk ' . count($heads) . ' kelas).');
        }

        // ---------- Pemetaan ke data aplikasi
        $db       = db_connect();
        $classes  = [];
        foreach ($db->table('class_groups')->select('id, name')->where('academic_year_id', $yearId)->where('deleted_at', null)->get()->getResultArray() as $c) {
            $classes[self::normClass($c['name'])] = ['id' => (int) $c['id'], 'name' => $c['name']];
        }
        $colClass = [];
        $missingC = [];
        foreach ($heads as $i => $h) {
            $k = self::normClass($h['t']);
            $colClass[$i] = $classes[$k] ?? null;
            if (! $colClass[$i]) {
                $missingC[] = $h['t'];
            }
        }
        $users = [];
        $names = [];
        foreach ($db->table('users')->select('id, name, teacher_code')->where('role', 'teacher')->get()->getResultArray() as $u) {
            if ((string) $u['teacher_code'] !== '') {
                $users[(string) $u['teacher_code']] = ['id' => (int) $u['id'], 'name' => $u['name']];
            }
            $names[(int) $u['id']] = $u['name'];
        }
        $subjects = [];
        foreach ($db->table('subjects')->select('id, code, name')->where('deleted_at', null)->get()->getResultArray() as $s) {
            $subjects[strtoupper((string) $s['code'])] = ['id' => (int) $s['id'], 'code' => $s['code'], 'name' => $s['name']];
        }
        $teach = []; // [kelas][mapel] => [guru…] ; [guru][kelas] => [mapel…]
        $tByUC = [];
        foreach ($db->table('user_teaching')->get()->getResultArray() as $t) {
            $teach[(int) $t['class_group_id']][(int) $t['subject_id']][] = (int) $t['user_id'];
            $tByUC[(int) $t['user_id']][(int) $t['class_group_id']][]   = (int) $t['subject_id'];
        }

        // ---------- Baris waktu
        $timeItems = array_values(array_filter($items, static fn ($i) => preg_match('/^(\d{1,2})[.:](\d{2})\s*-\s*(\d{1,2})[.:](\d{2})$/', str_replace(' ', ' ', $i['t']))));
        usort($timeItems, static fn ($a, $b) => $b['y'] <=> $a['y']);
        $firstKdX = $subs[0]['x'] - 6;
        $rows     = [];
        $day      = 1;
        $prevS    = null;
        $prevE    = null;
        $fixes    = [];
        foreach ($timeItems as $ti) {
            preg_match('/^(\d{1,2})[.:](\d{2})\s*-\s*(\d{1,2})[.:](\d{2})$/', $ti['t'], $m);
            $s = sprintf('%02d:%02d', $m[1], $m[2]);
            $e = sprintf('%02d:%02d', $m[3], $m[4]);
            if ($prevS !== null && self::min($s) < self::min($prevS) - 120) {
                $day++;
                $prevE = null;
            } elseif ($prevE !== null && self::min($s) < self::min($prevE)) {
                $fixes[] = ['day' => $day, 'text' => 'Jam ' . $ti['t'] . ' tumpang tindih dengan jam sebelumnya; mulai disesuaikan menjadi ' . str_replace(':', '.', $prevE) . '.'];
                $s       = $prevE;
            }
            $line = array_values(array_filter($items, static fn ($i) => abs($i['y'] - $ti['y']) < 2.2));
            $jam  = null;
            foreach ($line as $l) {
                if ($l['x'] < $ti['x'] && ctype_digit($l['t'])) {
                    $jam = (int) $l['t'];
                }
            }
            $cells = [];
            $label = null;
            $hasKd = (bool) array_filter($line, static fn ($l) => $l['x'] >= $firstKdX && ctype_digit($l['t']) && $l['x'] < end($subs)['x'] + 20);
            foreach ($line as $l) {
                if ($l['x'] < $firstKdX) {
                    continue;
                }
                if (! $hasKd) {
                    if (! preg_match('/^\d+\.\s/', $l['t']) && $l['x'] < end($subs)['x'] + 20) {
                        $label = trim(($label ?? '') . ' ' . $l['t']);
                    }
                    continue;
                }
                $cx = $l['x'] + 4;
                $k  = 0;
                $bd = INF;
                foreach ($subs as $si => $sb) {
                    if (abs($sb['x'] + 4 - $cx) < $bd) {
                        $bd = abs($sb['x'] + 4 - $cx);
                        $k  = $si;
                    }
                }
                $isKd = $subs[$k]['t'] === 'KD';
                if ($bd < 12 && (($isKd && ctype_digit($l['t'])) || (! $isKd && preg_match('/^[A-Z][A-Z.]{0,9}$/', $l['t'])))) {
                    $cells[intdiv($k, 2)][$isKd ? 'kd' : 'mp'] = $l['t'];
                } elseif (! ctype_digit($l['t']) && preg_match('/[A-Za-z]{2}/', str_replace(' ', '', $l['t'])) && ! preg_match('/^\d+\.\s/', $l['t']) && $l['x'] < end($subs)['x'] + 20) {
                    $label = trim(($label ?? '') . ' ' . $l['t']);
                }
            }
            $cells = array_filter($cells, static fn ($c) => isset($c['kd'], $c['mp']));
            $rows[] = ['day' => $day, 'y' => $ti['y'], 'start' => $s, 'end' => $e, 'jam' => $jam, 'cells' => $cells, 'label' => $label ? self::cleanLabel($label) : null];
            $prevS = $s;
            $prevE = $e;
        }
        if (! $rows) {
            throw new RuntimeException('Baris jam (mis. "07.10 - 07.45") tidak ditemukan.');
        }

        // ---------- Sesi: dari judul PAGI/SIANG, atau dari jam mulai pelajaran
        $txt   = strtoupper(implode(' ', array_column($items, 't')));
        $first = min(array_map(static fn ($r) => $r['start'], array_filter($rows, static fn ($r) => $r['cells']) ?: $rows));
        $shift = preg_match('/\bSIANG\b/', $txt) && ! preg_match('/\bPAGI\b/', $txt) ? 'siang' : (preg_match('/\bPAGI\b/', $txt) && ! preg_match('/\bSIANG\b/', $txt) ? 'pagi' : ($first < '12:00' ? 'pagi' : 'siang'));

        // ---------- Sel → blok, dengan pemeriksaan & saran perbaikan
        $issues = [];
        $slots  = [];
        $cellsOut = [];
        $busy   = []; // [guru][hari] => [[s,e,kelas]] — termasuk jadwal kelas lain (sesi lain) yang sudah ada
        $mine   = array_map(static fn ($c) => $c['id'], array_filter($colClass));
        foreach ($db->table('teaching_schedules')->where('academic_year_id', $yearId)->whereNotIn('class_group_id', $mine ?: [0])->get()->getResultArray() as $x) {
            $busy[(int) $x['user_id']][(int) $x['day_of_week']][] = [substr($x['start_time'], 0, 5), substr($x['end_time'], 0, 5), (int) $x['class_group_id']];
        }
        foreach ($rows as $ri => $r) {
            if (! $r['cells']) {
                $slots[] = ['day' => $r['day'], 'start' => $r['start'], 'end' => $r['end'], 'kind' => 'kegiatan', 'lesson_no' => null, 'label' => $r['label'] ?: 'Istirahat'];
                continue;
            }
            $slots[] = ['day' => $r['day'], 'start' => $r['start'], 'end' => $r['end'], 'kind' => 'pelajaran', 'lesson_no' => $r['jam'], 'label' => null];
            foreach ($r['cells'] as $col => $c) {
                $cls = $colClass[$col] ?? null;
                if (! $cls) {
                    continue;
                }
                $where = self::dayName($r['day']) . ' jam ' . ($r['jam'] ?? '?') . ', ' . $cls['name'];
                $u     = $users[$c['kd']] ?? null;
                $sub   = self::subject($c['mp'], $subjects);
                if (! $u) {
                    $issues[] = ['level' => 'error', 'where' => $where, 'text' => 'Kode guru ' . $c['kd'] . ' tidak dikenal (atur "Kode guru" di Data Guru). Sel dilewati.'];
                    continue;
                }
                if (! $sub) {
                    $issues[] = ['level' => 'error', 'where' => $where, 'text' => 'Mapel "' . $c['mp'] . '" tidak dikenal. Sel dilewati.'];
                    continue;
                }
                $uid = $u['id'];
                $sid = $sub['id'];
                // Cocok dengan pembagian mengajar?
                if ($teach && ! in_array($sid, $tByUC[$uid][$cls['id']] ?? [], true)) {
                    $alt  = array_values(array_filter($teach[$cls['id']][$sid] ?? [], fn ($t) => ! $this->busyAt($busy, $t, $r['day'], $r['start'], $r['end']) && ! $this->rowHasTeacher($rows[$ri], $t, $users)));
                    $note = 'Kode ' . $c['kd'] . ' (' . $u['name'] . ') tidak tercatat mengajar ' . $sub['code'] . ' di ' . $cls['name'] . '.';
                    if ($alt) {
                        $altCode = array_search($alt[0], array_map(static fn ($x) => $x['id'], $users), true);
                        $note   .= ' Saran: kode ' . $altCode . ' (' . $names[$alt[0]] . ').';
                        if ($applySuggestions) {
                            $uid   = $alt[0];
                            $note .= ' → diterapkan.';
                        }
                    } elseif (($own = $tByUC[$uid][$cls['id']] ?? []) && count($own) === 1) {
                        $altSub = array_values(array_filter($subjects, static fn ($s) => $s['id'] === $own[0]))[0] ?? null;
                        $note  .= ' Saran: mapel ' . ($altSub['code'] ?? '?') . '.';
                        if ($applySuggestions && $altSub) {
                            $sid   = $altSub['id'];
                            $note .= ' → diterapkan.';
                        }
                    }
                    $issues[] = ['level' => 'warning', 'where' => $where, 'text' => $note];
                }
                if ($this->busyAt($busy, $uid, $r['day'], $r['start'], $r['end'])) {
                    $issues[] = ['level' => 'error', 'where' => $where, 'text' => 'Bentrok: ' . ($names[$uid] ?? '') . ' sudah mengajar kelas lain pada jam ini.'];
                }
                $busy[$uid][$r['day']][] = [$r['start'], $r['end'], $cls['id']];
                $cellsOut[] = ['class_group_id' => $cls['id'], 'subject_id' => $sid, 'user_id' => $uid, 'day' => $r['day'], 'start' => $r['start'], 'end' => $r['end'], 'lesson_no' => $r['jam']];
            }
        }
        foreach ($fixes as $f) {
            $issues[] = ['level' => 'info', 'where' => self::dayName($f['day']), 'text' => $f['text']];
        }
        foreach ($missingC as $mc) {
            $issues[] = ['level' => 'error', 'where' => 'Kolom ' . $mc, 'text' => 'Kelas ' . $mc . ' belum ada di Kelas/Rombel T.P. aktif. Kolom ini dilewati.'];
        }

        // ---------- Piket ("1. NAMA", "2. NAMA" di kolom paling kanan, per blok hari)
        $piket = [];
        $dayY  = [];
        foreach ($rows as $r) {
            $dayY[$r['day']] = [min($dayY[$r['day']][0] ?? INF, $r['y']), max($dayY[$r['day']][1] ?? -INF, $r['y'])];
        }
        foreach ($items as $i) {
            if ($i['x'] > end($subs)['x'] + 10 && preg_match('/^\d+\.\s*(.+)$/', $i['t'], $m)) {
                foreach ($dayY as $d => [$lo, $hi]) {
                    if ($i['y'] >= $lo - 3 && $i['y'] <= $hi + 3) {
                        $uid = self::matchName($m[1], $names);
                        if ($uid) {
                            $piket[$d][] = $uid;
                        } else {
                            $issues[] = ['level' => 'warning', 'where' => 'Piket ' . self::dayName($d), 'text' => 'Nama "' . $m[1] . '" tidak cocok dengan guru mana pun.'];
                        }
                    }
                }
            }
        }

        $blocks = ScheduleKit::mergeBlocks($cellsOut);

        return [
            'draft'   => [
                'source'    => 'upload',
                'year_id'   => $yearId,
                'shift'     => $shift,
                'class_ids' => array_values(array_unique(array_map(static fn ($c) => $c['id'], array_filter($colClass)))),
                'blocks'    => $blocks,
                'slots'     => $slots,
                'piket'     => $piket,
                'issues'    => $issues,
            ],
            'issues'  => $issues,
            'summary' => ['classes' => count(array_filter($colClass)), 'cells' => count($cellsOut), 'blocks' => count($blocks), 'days' => $day, 'shift' => $shift,
                'activities' => count(array_filter($slots, static fn ($s) => $s['kind'] === 'kegiatan')), 'piket' => array_sum(array_map('count', $piket))],
        ];
    }

    private function busyAt(array $busy, int $uid, int $day, string $s, string $e): bool
    {
        foreach ($busy[$uid][$day] ?? [] as [$a, $b]) {
            if ($a < $e && $b > $s) {
                return true;
            }
        }

        return false;
    }

    /** Guru $t sudah tercantum di sel lain pada baris yang sama? */
    private function rowHasTeacher(array $row, int $t, array $users): bool
    {
        foreach ($row['cells'] as $c) {
            if (($users[$c['kd']]['id'] ?? 0) === $t) {
                return true;
            }
        }

        return false;
    }

    private static function subject(string $mp, array $subjects): ?array
    {
        $k = strtoupper(preg_replace('/[^A-Za-z]/', '', $mp));

        return $subjects[$k] ?? $subjects[self::MP_ALIAS[$k] ?? ''] ?? null;
    }

    /** "7A" / "VII A" / "VII-A" → "7A" */
    private static function normClass(string $n): string
    {
        $n = strtoupper(preg_replace('/[\s.\-]+/', '', $n));
        if (preg_match('/^([IVX]+)([A-Z]\d*)$/', $n, $m) && roman_to_int($m[1])) {
            return roman_to_int($m[1]) . $m[2];
        }

        return $n;
    }

    private static function cleanLabel(string $l): string
    {
        $l = preg_replace('/\b(\w) (?=\w\b)/u', '$1', $l); // "I s t i r a h a t" → "Istirahat"
        $l = preg_replace('/\s+/', ' ', trim($l));
        if (preg_match('/^istirahat$/i', str_replace(' ', '', $l))) {
            return 'Istirahat';
        }

        return mb_substr($l, 0, 80);
    }

    /** Cocokkan nama piket dengan guru (abaikan gelar, tanda baca, huruf besar/kecil). */
    private static function matchName(string $raw, array $names): ?int
    {
        $norm = static function (string $s): string {
            $s = strtolower(explode(',', $s)[0]);
            $s = preg_replace('/\b(dra?|drs|hj?|ir)\.?\s/', '', $s);

            return preg_replace('/[^a-z]/', '', str_replace('0', 'o', $s));
        };
        $words = static function (string $s): array {
            $s = strtolower(explode(',', $s)[0]);
            $s = preg_replace('/\b(dra?|drs|hj?|ir)\.?\s/', '', $s);

            return array_values(array_filter(preg_split('/[^a-z0-9]+/', str_replace('0', 'o', $s)), static fn ($w) => $w !== ''));
        };
        $qw = $words($raw);
        foreach ($names as $id => $n) { // semua kata di PDF = awalan kata nama guru, berurutan
            $cw = $words($n);
            $j  = 0;
            foreach ($cw as $w) {
                if ($j < count($qw) && str_starts_with($w, $qw[$j])) {
                    $j++;
                }
            }
            if (count($qw) >= 2 && $j === count($qw)) {
                return (int) $id;
            }
        }
        $q    = $norm($raw);
        $best = null;
        $bs   = 0;
        foreach ($names as $id => $n) {
            $c = $norm($n);
            similar_text($q, $c, $pct);
            if (str_starts_with($c, $q) || str_starts_with($q, $c)) {
                $pct = max($pct, 90);
            }
            if ($pct > $bs) {
                $bs   = $pct;
                $best = $id;
            }
        }

        return $bs >= 72 ? $best : null;
    }

    private static function min(string $hm): int
    {
        [$h, $m] = explode(':', $hm);

        return (int) $h * 60 + (int) $m;
    }

    private static function mode(array $v): float
    {
        $c = array_count_values(array_map('strval', $v));
        arsort($c);

        return (float) array_key_first($c);
    }

    public static function dayName(int $d): string
    {
        return day_names()[$d] ?? ('Hari ' . $d);
    }
}
