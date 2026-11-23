<?php

namespace App\Libraries;

/**
 * Pembaca .xlsx minimal (lembar pertama) tanpa pustaka tambahan: ZipArchive + XML bawaan PHP.
 * Mengembalikan baris berupa list nilai teks. Tanggal Excel (angka seri) dibiarkan angka; ubah dengan excelDate().
 */
final class SimpleXlsx
{
    /** @return list<list<string>>|string baris, atau pesan galat */
    public static function rows(string $path, int $maxRows = 5000)
    {
        if (! class_exists(\ZipArchive::class)) {
            return 'Ekstensi PHP zip belum aktif; simpan file sebagai CSV.';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return 'File bukan .xlsx yang valid. Simpan ulang dari Excel sebagai .xlsx atau .csv.';
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = self::xml($xml);
            foreach ($sx?->si ?? [] as $si) {
                $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map(static fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        $sheet = 'xl/worksheets/sheet1.xml';
        if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $w = self::xml($wb);
            $r = self::xml($rels);
            $first = $w?->sheets->sheet[0] ?? null;
            if ($first && $r) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($r->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $t = ltrim((string) $rel['Target'], '/');
                        $sheet = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                    }
                }
            }
        }
        $data = $zip->getFromName($sheet);
        $zip->close();
        if ($data === false) {
            return 'Lembar kerja pertama tidak ditemukan di file .xlsx.';
        }
        $sx = self::xml($data);
        if (! $sx) {
            return 'Isi file .xlsx tidak dapat dibaca.';
        }
        $out = [];
        foreach ($sx->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $idx = self::colIndex((string) $c['r']);
                $t   = (string) $c['t'];
                $v   = $t === 'inlineStr' ? (string) ($c->is->t ?? '') : (string) ($c->v ?? '');
                if ($t === 's') {
                    $v = $strings[(int) $v] ?? '';
                }
                $cells[$idx] = trim($v);
            }
            if ($cells) {
                $max  = max(array_keys($cells));
                $line = array_fill(0, $max + 1, '');
                foreach ($cells as $i => $v) {
                    $line[$i] = $v;
                }
                $out[] = $line;
            }
            if (count($out) >= $maxRows) {
                break;
            }
        }

        return $out;
    }

    private static function xml(string $s): ?\SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);
        $x    = simplexml_load_string($s, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $x === false ? null : $x;
    }

    /** "C12" → 2 */
    private static function colIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($ref)) ?: 'A';
        $n       = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** Angka seri tanggal Excel → Y-m-d (null bila bukan angka). */
    public static function excelDate(string $v): ?string
    {
        if (! is_numeric($v) || (float) $v < 1000 || (float) $v > 80000) {
            return null;
        }

        return gmdate('Y-m-d', (int) round(((float) $v - 25569) * 86400));
    }
}
