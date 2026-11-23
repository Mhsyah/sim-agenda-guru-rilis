<?php

namespace App\Libraries;

/**
 * Penyusun jadwal otomatis untuk satu sesi (Pagi/Siang).
 *
 * Aturan:
 * - hanya mengisi slot "pelajaran" pada struktur jam; slot kegiatan (Pembiasaan, Istirahat, MBG, …) tidak diisi;
 * - satu guru tidak boleh mengajar dua kelas pada waktu yang beririsan (termasuk jadwal sesi lain yang sudah ada);
 * - satu kelas hanya satu mapel per jam; satu mapel paling banyak satu blok per hari per kelas;
 * - JP dipecah menjadi blok 2 jam berurutan (sisa 1 jam), dan tidak melewati jeda kegiatan.
 * Beberapa percobaan acak dijalankan; hasil dengan JP tak terjadwal paling sedikit yang dipakai.
 */
final class ScheduleGenerator
{
    /** @var array<int, list<array{no: int, start: string, end: string, seg: int}>> */
    private array $periods = [];

    /**
     * @param list<int> $classIds kelas pada sesi ini
     * @return array{blocks: list<array>, issues: list<string>, stats: array}
     */
    public function run(int $yearId, string $shift, array $classIds, int $attempts = 40): array
    {
        $db     = db_connect();
        $issues = [];

        // Slot pelajaran per hari, dikelompokkan per segmen (dipisah jeda/kegiatan)
        foreach (ScheduleKit::slots($yearId, $shift) as $d => $slots) {
            $seg  = 0;
            $prev = null;
            foreach ($slots as $s) {
                if ($s['kind'] !== 'pelajaran') {
                    $seg++;
                    $prev = null;
                    continue;
                }
                if ($prev !== null && $prev !== $s['start_time']) {
                    $seg++;
                }
                $this->periods[$d][] = ['no' => (int) ($s['lesson_no'] ?: count($this->periods[$d] ?? []) + 1), 'start' => $s['start_time'], 'end' => $s['end_time'], 'seg' => $seg];
                $prev = $s['end_time'];
            }
        }
        if (! $this->periods) {
            return ['blocks' => [], 'issues' => ['Struktur jam sesi ' . (ScheduleKit::SHIFTS[$shift] ?? $shift) . ' belum diisi. Buka "Beban JP & Struktur Jam" terlebih dahulu.'], 'stats' => []];
        }
        $capacity = array_sum(array_map('count', $this->periods));

        // Kebutuhan: kelas × mapel (JP dari beban JP per tingkat) × guru (pembagian mengajar)
        $classes  = [];
        foreach ($db->table('class_groups')->whereIn('id', $classIds ?: [0])->get()->getResultArray() as $c) {
            $classes[(int) $c['id']] = $c;
        }
        $loads    = ScheduleKit::loads($yearId);
        $subjects = array_column($db->table('subjects')->select('id, name')->get()->getResultArray(), 'name', 'id');
        $teach    = [];
        foreach ($db->table('user_teaching ut')->select('ut.user_id, ut.subject_id, ut.class_group_id')
            ->join('users u', 'u.id = ut.user_id')->where('u.is_active', 1)->whereIn('ut.class_group_id', $classIds ?: [0])->get()->getResultArray() as $t) {
            $teach[(int) $t['class_group_id']][(int) $t['subject_id']][] = (int) $t['user_id'];
        }
        $chunks = [];
        $demand = 0;
        $tLoad  = [];
        foreach ($classes as $cid => $c) {
            $g    = (int) $c['grade_level'];
            $need = 0;
            foreach ($loads as $sid => $byGrade) {
                $jp = (int) ($byGrade[$g] ?? 0);
                if ($jp <= 0) {
                    continue;
                }
                $tids = $teach[$cid][$sid] ?? [];
                if (! $tids) {
                    $issues[] = 'Belum ada guru pengajar ' . ($subjects[$sid] ?? '#' . $sid) . ' di ' . $c['name'] . ' (atur di Data Guru → Mapel & kelas diajarkan).';
                    continue;
                }
                if (count($tids) > 1) {
                    $issues[] = ($subjects[$sid] ?? '') . ' di ' . $c['name'] . ' diajar lebih dari satu guru; dipakai guru pertama.';
                }
                $need   += $jp;
                $tLoad[$tids[0]] = ($tLoad[$tids[0]] ?? 0) + $jp;
                foreach (self::split($jp, count($this->periods)) as $size) {
                    $chunks[] = ['c' => $cid, 's' => $sid, 't' => $tids[0], 'n' => $size];
                }
            }
            $demand += $need;
            if ($need > $capacity) {
                $issues[] = $c['name'] . ': beban ' . $need . ' JP melebihi ' . $capacity . ' jam pelajaran yang tersedia per minggu.';
            }
        }
        if (! $chunks) {
            return ['blocks' => [], 'issues' => array_merge($issues, ['Beban JP belum diisi untuk tingkat kelas pada sesi ini.']), 'stats' => []];
        }

        // Jadwal guru yang sudah ada di luar kelas-kelas ini (mis. sesi lain) → tidak boleh beririsan
        $busy = [];
        foreach ($db->table('teaching_schedules')->where('academic_year_id', $yearId)->whereNotIn('class_group_id', $classIds ?: [0])->get()->getResultArray() as $r) {
            $busy[(int) $r['user_id']][(int) $r['day_of_week']][] = [$r['start_time'], $r['end_time']];
        }

        $best = null;
        $t0   = microtime(true);
        for ($a = 0; $a < $attempts && microtime(true) - $t0 < 15; $a++) {
            $res = $this->attempt($chunks, $busy, $tLoad, $a);
            if (! $best || $res['missing'] < $best['missing'] || ($res['missing'] === $best['missing'] && $res['spread'] < $best['spread'])) {
                $best = $res;
            }
            if ($best['missing'] === 0 && $a >= 25) {
                break;
            }
        }

        $cells = [];
        foreach ($best['placed'] as $p) {
            foreach ($p['idx'] as $i) {
                $per     = $this->periods[$p['d']][$i];
                $cells[] = ['class_group_id' => $p['c'], 'subject_id' => $p['s'], 'user_id' => $p['t'], 'day' => $p['d'], 'start' => $per['start'], 'end' => $per['end'], 'lesson_no' => $per['no']];
            }
        }
        $users = array_column($db->table('users')->select('id, name')->get()->getResultArray(), 'name', 'id');
        foreach ($best['unplaced'] as $u) {
            $issues[] = 'Tidak muat tanpa bentrok: ' . ($subjects[$u['s']] ?? '') . ' ' . $classes[$u['c']]['name'] . ' (' . ($users[$u['t']] ?? '') . ') ' . $u['n'] . ' JP.';
        }

        return [
            'blocks' => ScheduleKit::mergeBlocks($cells),
            'issues' => $issues,
            'stats'  => ['demand' => $demand, 'placed' => $demand - $best['missing'], 'missing' => $best['missing'], 'capacity' => $capacity * count($classes), 'attempts' => $a],
        ];
    }

    /** Pecah JP menjadi blok: 2 jam (sisa 1); bila blok lebih banyak dari hari, pakai blok 3. @return list<int> */
    private static function split(int $jp, int $days): array
    {
        $out = array_fill(0, intdiv($jp, 2), 2);
        if ($jp % 2) {
            $out[] = 1;
        }
        while (count($out) > $days && in_array(2, $out, true)) {
            $i = array_search(1, $out, true);
            if ($i !== false) {               // 2 + 1 → 3
                unset($out[$i]);
                $out[array_search(2, $out, true)] = 3;
            } else {                          // 2 + 2 → 3 + 1
                $j = array_search(2, $out, true);
                unset($out[$j]);
                $out[array_search(2, $out, true)] = 3;
                $out[] = 1;
            }
            $out = array_values($out);
        }
        rsort($out);

        return $out;
    }

    private function attempt(array $chunks, array $busy, array $tLoad, int $seed): array
    {
        mt_srand(1000 + $seed);
        // Urutan: guru tersibuk & blok terbesar dulu, sedikit diacak
        foreach ($chunks as &$c) {
            $c['r'] = $tLoad[$c['t']] + mt_rand(0, 4);
        }
        unset($c);
        usort($chunks, static fn ($x, $y) => [$y['r'], $y['n']] <=> [$x['r'], $x['n']]);

        $P       = $this->periods;
        $cUse    = [];   // [kelas][hari][k] => indeks penempatan
        $tAt     = [];   // [guru][hari][k] => indeks penempatan (sesi ini)
        $subjDay = [];   // [kelas][mapel][hari] => jumlah blok
        $dayLoad = [];   // [kelas][hari] => JP
        $placed  = [];
        $ext     = $busy; // jadwal guru di luar sesi ini (berdasarkan jam)

        $teacherFree = function (int $t, int $d, int $k, ?int $ignore = null) use (&$tAt, &$ext, $P): bool {
            if (isset($tAt[$t][$d][$k]) && $tAt[$t][$d][$k] !== $ignore) {
                return false;
            }

            return ! $this->clash($ext[$t][$d] ?? [], $P[$d][$k]['start'], $P[$d][$k]['end']);
        };
        $options = function (array $ch, bool $relaxed, ?int $ignore = null) use (&$cUse, &$subjDay, &$dayLoad, &$tAt, $P, $teacherFree): array {
            $opt = [];
            foreach ($P as $d => $pers) {
                $same = $subjDay[$ch['c']][$ch['s']][$d] ?? 0;
                if ($ignore !== null && ($cur = $this->placedRef[$ignore] ?? null) && $cur['d'] === $d) {
                    $same--;
                }
                if ($same > 0 && ! $relaxed) {
                    continue;
                }
                $n = count($pers);
                for ($i = 0; $i + $ch['n'] <= $n; $i++) {
                    $idx = range($i, $i + $ch['n'] - 1);
                    if ($pers[$idx[0]]['seg'] !== $pers[end($idx)]['seg']) {
                        continue; // tidak melewati jeda/kegiatan
                    }
                    $ok = true;
                    foreach ($idx as $k) {
                        if ((isset($cUse[$ch['c']][$d][$k]) && $cUse[$ch['c']][$d][$k] !== $ignore) || ! $teacherFree($ch['t'], $d, $k, $ignore)) {
                            $ok = false;
                            break;
                        }
                    }
                    if ($ok) {
                        $opt[] = [($dayLoad[$ch['c']][$d] ?? 0) * 3 + count($tAt[$ch['t']][$d] ?? []) * 2 + $same * 20 + $i * 0.2 + mt_rand(0, 100) / 100, $d, $idx];
                    }
                }
            }
            usort($opt, static fn ($x, $y) => $x[0] <=> $y[0]);

            return $opt;
        };
        $put = function (array $ch, int $d, array $idx, ?int $pi = null) use (&$cUse, &$tAt, &$subjDay, &$dayLoad, &$placed): int {
            $pi ??= count($placed);
            $placed[$pi] = ['c' => $ch['c'], 's' => $ch['s'], 't' => $ch['t'], 'd' => $d, 'idx' => $idx, 'n' => count($idx)];
            foreach ($idx as $k) {
                $cUse[$ch['c']][$d][$k] = $pi;
                $tAt[$ch['t']][$d][$k]  = $pi;
            }
            $subjDay[$ch['c']][$ch['s']][$d] = ($subjDay[$ch['c']][$ch['s']][$d] ?? 0) + 1;
            $dayLoad[$ch['c']][$d]           = ($dayLoad[$ch['c']][$d] ?? 0) + count($idx);
            $this->placedRef = $placed;

            return $pi;
        };
        $take = function (int $pi) use (&$cUse, &$tAt, &$subjDay, &$dayLoad, &$placed): array {
            $p = $placed[$pi];
            foreach ($p['idx'] as $k) {
                unset($cUse[$p['c']][$p['d']][$k], $tAt[$p['t']][$p['d']][$k]);
            }
            $subjDay[$p['c']][$p['s']][$p['d']]--;
            $dayLoad[$p['c']][$p['d']] -= count($p['idx']);

            return $p;
        };

        $unplaced = [];
        $queue    = $chunks;
        while ($queue) {
            $ch  = array_shift($queue);
            $opt = $options($ch, false) ?: ($ch['n'] === 1 ? $options($ch, true) : []);
            if ($opt) {
                $put($ch, $opt[0][1], $opt[0][2]);
                continue;
            }
            if ($ch['n'] > 1) { // pecah menjadi jam tunggal lalu coba lagi
                for ($k = 0; $k < $ch['n']; $k++) {
                    $queue[] = ['n' => 1] + $ch;
                }
                continue;
            }
            $unplaced[] = $ch;
        }

        // Tahap perbaikan: geser satu pelajaran guru yang menghalangi ke slot lain, lalu isi slot yang kosong.
        $left = [];
        foreach ($unplaced as $u) {
            $done = false;
            foreach ($P as $d => $pers) {
                foreach (array_keys($pers) as $k) {
                    if (isset($cUse[$u['c']][$d][$k])) {
                        continue;
                    }
                    $pi = $tAt[$u['t']][$d][$k] ?? null;
                    if ($pi === null) {
                        if ($teacherFree($u['t'], $d, $k)) {
                            $put($u, $d, [$k]);
                            $done = true;
                            break 2;
                        }
                        continue;
                    }
                    $blk = $placed[$pi];
                    $old = $take($pi);
                    $alt = array_values(array_filter($options(['c' => $old['c'], 's' => $old['s'], 't' => $old['t'], 'n' => $old['n']], true),
                        static fn ($o) => ! ($o[1] === $d && in_array($k, $o[2], true))));
                    if ($alt) {
                        $put($old, $alt[0][1], $alt[0][2], $pi);
                        if ($teacherFree($u['t'], $d, $k)) {
                            $put($u, $d, [$k]);
                            $done = true;
                            break 2;
                        }
                        $take($pi);
                    }
                    $put($blk, $blk['d'], $blk['idx'], $pi); // kembalikan
                }
            }
            // Sisi kelas: guru kosong di (d2,k2) tetapi kelas terisi pelajaran 1 jam → pindahkan pelajaran itu ke slot kosong kelas (d,k)
            if (! $done) {
                $free = [];
                foreach ($P as $d => $pers) {
                    foreach (array_keys($pers) as $k) {
                        if (! isset($cUse[$u['c']][$d][$k])) {
                            $free[] = [$d, $k];
                        }
                    }
                }
                foreach ($P as $d2 => $pers2) {
                    foreach (array_keys($pers2) as $k2) {
                        $mi = $cUse[$u['c']][$d2][$k2] ?? null;
                        if ($mi === null || $placed[$mi]['n'] !== 1 || ! $teacherFree($u['t'], $d2, $k2)) {
                            continue;
                        }
                        $m = $placed[$mi];
                        foreach ($free as [$d, $k]) {
                            if ($teacherFree($m['t'], $d, $k)) {
                                $take($mi);
                                $put($m, $d, [$k], $mi);
                                $put($u, $d2, [$k2]);
                                $done = true;
                                break 3;
                            }
                        }
                    }
                }
            }
            if (! $done) {
                $left[] = $u;
            }
        }

        $miss = [];
        foreach ($left as $u) {
            $k        = $u['c'] . '-' . $u['s'];
            $miss[$k] = ['n' => ($miss[$k]['n'] ?? 0) + $u['n']] + $u;
        }
        $spread = 0;
        foreach ($dayLoad as $days) {
            $spread += max($days) - min($days);
        }
        $split = 0; // mapel yang muncul > 1 blok sehari (kurang ideal)
        foreach ($subjDay as $bySubj) {
            foreach ($bySubj as $byDay) {
                foreach ($byDay as $n) {
                    $split += max(0, $n - 1);
                }
            }
        }

        return ['placed' => array_values($placed), 'unplaced' => array_values($miss), 'missing' => array_sum(array_column($left, 'n')), 'spread' => $spread + $split * 3];
    }

    /** @var array<int, array> salinan penempatan (untuk menghitung blok mapel sehari saat memindah) */
    private array $placedRef = [];

    private function clash(array $intervals, string $start, string $end): bool
    {
        foreach ($intervals as [$a, $b]) {
            if ($a < $end && $b > $start) {
                return true;
            }
        }

        return false;
    }
}
