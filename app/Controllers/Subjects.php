<?php

namespace App\Controllers;

use App\Models\SubjectModel;

class Subjects extends CrudController
{
    protected string $modelClass = SubjectModel::class;
    protected string $table      = 'subjects';
    protected string $base       = 'subjects';
    protected string $title      = 'Mata Pelajaran';
    protected string $singular   = 'Mata pelajaran';
    protected string $entity     = 'subject';
    protected array $searchFields = ['subjects.name', 'subjects.code'];
    protected array $order       = ['name', 'ASC'];
    protected bool $audit        = true;

    /**
     * Tambah mapel cepat dari formulir pengguna (AJAX). Bila nama sudah ada, mapel lama dipakai (dan diaktifkan).
     * Balasan JSON: {ok, id, label, created, csrf} atau {ok:false, error, csrf}.
     */
    public function quick()
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('name')) ?? '');
        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $out  = static fn (array $d) => service('response')->setJSON($d + ['csrf' => csrf_hash()]);
        if ($name === '' || mb_strlen($name) > 100) {
            return $out(['ok' => false, 'error' => 'Nama mata pelajaran wajib diisi (maks. 100 karakter).']);
        }
        if ($code !== '' && ! preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) {
            return $out(['ok' => false, 'error' => 'Kode hanya huruf, angka, - atau _ (maks. 20).']);
        }
        $m     = new SubjectModel();
        $exist = $m->where('LOWER(name)', mb_strtolower($name))->first();
        if ($exist) {
            if (! (int) $exist['is_active']) {
                $m->update($exist['id'], ['is_active' => 1]);
            }
            $row     = $m->find($exist['id']);
            $created = false;
        } else {
            if ($code !== '' && (new SubjectModel())->where('code', $code)->first()) {
                return $out(['ok' => false, 'error' => 'Kode ' . $code . ' sudah dipakai mapel lain.']);
            }
            $id      = (int) $m->insert(['name' => $name, 'code' => $code ?: null, 'is_active' => 1]);
            $row     = $m->find($id);
            $created = true;
            \App\Libraries\Audit::log('create', 'subject', $id, null, ['name' => $name, 'code' => $code ?: null, 'via' => 'form pengguna']);
        }
        \App\Libraries\Lookup::reset();

        return $out(['ok' => true, 'id' => (int) $row['id'], 'label' => $row['name'] . ($row['code'] ? ' (' . $row['code'] . ')' : ''), 'created' => $created]);
    }

    protected function filterDefs(): array
    {
        return ['is_active' => ['label' => 'Status', 'options' => ['1' => 'Aktif', '0' => 'Nonaktif']]];
    }

    protected function fields(?array $row): array
    {
        return [
            'name'      => ['label' => 'Nama mata pelajaran', 'rules' => 'required|max_length[100]', 'placeholder' => 'Matematika'],
            'code'      => ['label' => 'Kode', 'rules' => 'permit_empty|max_length[20]|alpha_dash|is_unique[subjects.code,id,{id}]', 'placeholder' => 'MTK', 'help' => 'Opsional, harus unik.'],
            'phase'     => ['label' => 'Fase', 'type' => 'select', 'options' => phases(), 'rules' => 'permit_empty|in_list[A,B,C,D,E,F]', 'empty' => '— Pilih fase —'],
            'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => $row ? null : 1],
        ];
    }

    protected function columns(): array
    {
        return [
            'code'   => ['label' => 'Kode', 'fn' => static fn ($r) => subject_chip($r['code'] ?? null)],
            'name'   => ['label' => 'Mata pelajaran', 'fn' => static fn ($r) => '<strong>' . esc($r['name']) . '</strong>'],
            'phase'  => ['label' => 'Fase', 'fn' => static fn ($r) => $r['phase'] ? 'Fase ' . esc($r['phase']) : '—'],
            'status' => ['label' => 'Status', 'fn' => static fn ($r) => (int) $r['is_active'] ? badge('Aktif', 'success') : badge('Nonaktif')],
        ];
    }
}
