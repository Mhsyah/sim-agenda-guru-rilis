<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Dapodik;
use App\Libraries\Period;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Admin: sinkronisasi data GTK, peserta didik, dan rombel dengan Dapodik.
 * Tarik (Web Service) atau unggah berkas ekspor → pratinjau perbedaan → terapkan yang dicentang.
 */
class DapodikSync extends BaseController
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function index()
    {
        $s = Dapodik::settings();

        return $this->render('dapodik/index', [
            'title'    => 'Sinkron Dapodik',
            'set'      => $s,
            'hasToken' => Dapodik::decrypt((string) $s['token']) !== '',
        ]);
    }

    public function save()
    {
        $s    = Dapodik::settings();
        $url  = trim((string) $this->request->getPost('url'));
        $npsn = preg_replace('/\D/', '', (string) $this->request->getPost('npsn'));
        $tok  = trim((string) $this->request->getPost('token'));
        if ($url !== '' && ! preg_match('#^https?://[^\s/]+(:\d+)?(/.*)?$#i', $url)) {
            return redirect()->to(site_url('dapodik'))->with('error', 'Alamat Web Service harus diawali http:// atau https://, mis. http://192.168.1.10:5774');
        }
        if ($npsn !== '' && strlen($npsn) !== 8) {
            return redirect()->to(site_url('dapodik'))->with('error', 'NPSN harus 8 angka.');
        }
        $s['url']  = rtrim($url, '/');
        $s['npsn'] = $npsn;
        if ($tok !== '') {
            $s['token'] = Dapodik::encrypt($tok); // token disimpan terenkripsi, tidak pernah ditampilkan lagi
        }
        if ($this->request->getPost('forget')) {
            $s['token'] = '';
        }
        Dapodik::saveSettings($s);
        Audit::log('update', 'dapodik_settings', null, null, ['url' => $s['url'], 'npsn' => $s['npsn'], 'token' => $tok !== '' ? 'diganti' : 'tetap']);

        return redirect()->to(site_url('dapodik'))->with('success', 'Pengaturan Web Service Dapodik disimpan.');
    }

    /** Langkah 1a: tarik data dari Web Service Dapodik. */
    public function pull(string $kind)
    {
        $this->kind($kind);
        $rows = Dapodik::fetch($kind);
        if (is_string($rows)) {
            return redirect()->to(site_url('dapodik'))->with('error', $rows);
        }

        return $this->preview($kind, $rows, 'Web Service Dapodik');
    }

    /** Langkah 1b: unggah berkas ekspor Dapodik. */
    public function upload(string $kind)
    {
        $this->kind($kind);
        $file = $this->request->getFile('file');
        if (! $file || $file->getError() !== UPLOAD_ERR_OK || ! (is_uploaded_file($file->getTempName()) || ENVIRONMENT === 'testing') || $file->getSize() > self::MAX_BYTES) {
            return redirect()->to(site_url('dapodik'))->with('error', 'Pilih berkas ekspor Dapodik (.xlsx/.csv, maks. 5 MB).');
        }
        $rows = Dapodik::parseFile($kind, $file->getTempName(), $file->getClientName());
        if (is_string($rows)) {
            return redirect()->to(site_url('dapodik'))->with('error', $rows);
        }

        return $this->preview($kind, $rows, 'Berkas ' . $file->getClientName());
    }

    private function kind(string $kind): void
    {
        if (! isset(Dapodik::KINDS[$kind])) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! Period::yearId()) {
            throw PageNotFoundException::forPageNotFound('Belum ada tahun pelajaran aktif.');
        }
    }

    private function preview(string $kind, array $rows, string $source)
    {
        if (! $rows) {
            return redirect()->to(site_url('dapodik'))->with('warning', 'Tidak ada data ' . Dapodik::KINDS[$kind]['label'] . ' yang terbaca dari ' . $source . '.');
        }
        $token = bin2hex(random_bytes(12));
        file_put_contents(Dapodik::dir() . 'pull-' . $token . '.json', json_encode(['kind' => $kind, 'source' => $source, 'rows' => $rows, 'time' => time()]));
        // berkas tarikan lama (> 1 hari) dibersihkan
        foreach (glob(Dapodik::dir() . 'pull-*.json') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
        $diff = Dapodik::diff($kind, $rows);
        $s    = Dapodik::settings();
        $s['last'][$kind] = ['time' => time(), 'source' => $source, 'counts' => $diff['counts'], 'applied' => $s['last'][$kind]['applied'] ?? null];
        Dapodik::saveSettings($s);

        return $this->render('dapodik/preview', [
            'title'  => 'Pratinjau Sinkron ' . Dapodik::KINDS[$kind]['label'],
            'kind'   => $kind,
            'source' => $source,
            'diff'   => $diff,
            'token'  => $token,
        ]);
    }

    /** Langkah 2: terapkan baris yang dicentang. */
    public function apply()
    {
        $token = (string) $this->request->getPost('token');
        $path  = Dapodik::dir() . 'pull-' . (preg_match('/^[a-f0-9]{24}$/', $token) ? $token : 'x') . '.json';
        $pull  = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($pull) || ! isset(Dapodik::KINDS[$pull['kind'] ?? ''])) {
            return redirect()->to(site_url('dapodik'))->with('error', 'Pratinjau sudah kedaluwarsa. Tarik/unggah ulang data Dapodik.');
        }
        $keys = array_map('strval', (array) ($this->request->getPost('keys') ?? []));
        if (! $keys) {
            return redirect()->to(site_url('dapodik'))->with('info', 'Tidak ada baris yang dipilih; tidak ada data yang diubah.');
        }
        $res = Dapodik::apply($pull['kind'], $pull['rows'], $keys);
        @unlink($path);
        $s = Dapodik::settings();
        $s['last'][$pull['kind']]['applied'] = ['time' => time(), 'created' => $res['created'], 'updated' => $res['updated']];
        Dapodik::saveSettings($s);
        Audit::log('dapodik_sync', $pull['kind'], null, null, ['sumber' => $pull['source'], 'baru' => $res['created'], 'diperbarui' => $res['updated'], 'dilewati' => count($res['skipped'])]);

        $msg = Dapodik::KINDS[$pull['kind']]['label'] . ': ' . $res['created'] . ' baru, ' . $res['updated'] . ' diperbarui' . ($res['skipped'] ? ', ' . count($res['skipped']) . ' dilewati.' : '.');

        return redirect()->to(site_url('dapodik'))->with($res['skipped'] ? 'warning' : 'success', $msg)
            ->with('dapodik_result', ['credentials' => $res['credentials'], 'skipped' => $res['skipped']]);
    }
}
