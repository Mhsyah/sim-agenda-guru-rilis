<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Auth;
use App\Libraries\Period;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Model;

/**
 * Pengendali CRUD generik. Setiap modul mendefinisikan field formulir, kolom tabel,
 * filter, dan hook validasi tambahan; tampilan formulir & tabel dibuat konsisten
 * (label, pesan galat terbaca screen reader, konfirmasi hapus lewat dialog).
 */
abstract class CrudController extends BaseController
{
    protected string $modelClass;
    protected string $table;
    protected string $base;
    protected string $title;
    protected string $singular;
    protected string $entity;
    protected ?string $intro = null;
    protected int $perPage = 20;
    /** @var list<string> */
    protected array $searchFields = [];
    protected array $order = ['id', 'DESC'];
    protected bool $yearScoped = false;
    protected bool $archive = true;
    /** Label & konfirmasi tombol hapus di tabel (null = "Arsipkan" bawaan). */
    protected ?string $deleteLabel = null;
    protected ?string $deleteConfirm = null;
    /** Saring daftar milik guru ke mapel yang diajarkan (kolom subject_id). */
    protected bool $taughtOnly = false;
    protected bool $audit = false;
    /** Data milik masing-masing guru (kolom user_id). */
    protected bool $ownerScoped = false;
    protected string $indexView = 'crud/index';
    protected string $formView = 'crud/form';

    /** @return array<string, array<string, mixed>> */
    abstract protected function fields(?array $row): array;

    /** @return array<string, string|array{label: string, fn?: callable, class?: string}> */
    abstract protected function columns(): array;

    protected function model(): Model
    {
        return new $this->modelClass();
    }

    /** @return array<string, array{label: string, options: array}> */
    protected function filterDefs(): array
    {
        return [];
    }

    private ?array $defsCache = null;

    /**
     * Definisi filter di-cache dan dihitung SEBELUM query daftar dibangun:
     * query dropdown di tengah pembangunan query akan menghapus alias join
     * yang dilacak CodeIgniter pada koneksi bersama.
     */
    protected function defs(): array
    {
        return $this->defsCache ??= $this->filterDefs();
    }

    /** Kolom "tabel.kolom" diberi awalan tabel (DBPrefix) agar aman dipakai di LOWER(...) pencarian. */
    protected function prefixed(string $col): string
    {
        if (! str_contains($col, '.')) {
            return $col;
        }
        [$t, $c] = explode('.', $col, 2);

        return $t === $this->table ? db_connect()->prefixTable($t) . '.' . $c : $col;
    }

    protected function listQuery(Model $m, array $filters): Model
    {
        foreach ($this->defs() as $name => $def) {
            if (($filters[$name] ?? '') !== '' && empty($def['custom'])) {
                $m->where($this->table . '.' . $name, $filters[$name]);
            }
        }

        return $m;
    }

    /** @return array|string data siap simpan, atau pesan galat */
    protected function prepare(array $data, ?array $old)
    {
        return $data;
    }

    protected function afterSave(int $id, array $data, ?array $old): void
    {
    }

    protected function deleteBlocker(array $row): ?string
    {
        return null;
    }

    /** @return list<array{label: string, url: string, icon?: string}> */
    protected function rowLinks(array $row): array
    {
        return [];
    }

    /** @return list<array{label: string, url: string, icon?: string}> */
    protected function headerLinks(): array
    {
        return [];
    }

    /** Apakah pengguna boleh menambah/mengubah/menghapus di modul ini. */
    protected function canEdit(): bool
    {
        return true;
    }

    /** Hook: dipanggil setelah baris halaman ini dimuat (mis. untuk memuat data terkait sekaligus). */
    protected function beforeRenderRows(array $rows): void
    {
    }

    protected function formValues(?array $row): array
    {
        return $row ?? [];
    }

    protected function extraIndexData(array $filters): array
    {
        return [];
    }

    protected function findOr404(int $id): array
    {
        $row = $this->model()->find($id);
        if (! $row || ($this->ownerScoped && (int) ($row['user_id'] ?? 0) !== (int) Auth::id())) {
            throw PageNotFoundException::forPageNotFound($this->singular . ' tidak ditemukan.');
        }

        return $row;
    }

    protected function currentFilters(): array
    {
        $f = ['q' => trim((string) $this->request->getGet('q'))];
        foreach ($this->defs() as $name => $_) {
            $f[$name] = (string) ($this->request->getGet($name) ?? ($_['default'] ?? ''));
        }

        return $f;
    }

    public function index()
    {
        if ($this->yearScoped && ($r = $this->requirePeriod())) {
            return $r;
        }
        $filters = $this->currentFilters();
        // Hitung sebelum membangun query utama: query lain di tengah jalan merusak awalan alias tabel CI.
        $taught     = $this->taughtOnly ? \App\Libraries\Lookup::teachingSubjectIds() : null;
        $hiddenNote = null;
        if ($taught !== null) {
            $other = $this->model()->where($this->table . '.user_id', Auth::id())->whereNotIn($this->table . '.subject_id', $taught ?: [0]);
            if ($this->yearScoped) {
                $other->where($this->table . '.academic_year_id', Period::yearId());
            }
            if ($n = $other->countAllResults()) {
                $hiddenNote = $n . ' ' . mb_strtolower($this->singular) . ' pada mapel yang tidak lagi Anda ajarkan disembunyikan. Tambahkan mapelnya di Profil Saya bila masih diajarkan.';
            }
        }
        $m       = $this->model();
        if ($this->yearScoped) {
            $m->where($this->table . '.academic_year_id', Period::yearId());
        }
        if ($this->ownerScoped) {
            $m->where($this->table . '.user_id', Auth::id());
        }
        $m = $this->listQuery($m, $filters);
        if ($taught !== null) {
            $m->whereIn($this->table . '.subject_id', $taught ?: [0]);
        }

        if ($filters['q'] !== '' && $this->searchFields) {
            $m->groupStart();
            foreach ($this->searchFields as $i => $col) {
                $i === 0
                    ? $m->like($this->prefixed($col), $filters['q'], 'both', null, true)
                    : $m->orLike($this->prefixed($col), $filters['q'], 'both', null, true);
            }
            $m->groupEnd();
        }
        foreach (array_chunk($this->order, 2) as [$col, $dir]) {
            $m->orderBy($col, $dir);
        }

        $rows  = $m->paginate($this->perPage);
        $pager = $m->pager;
        $this->beforeRenderRows($rows);

        $cols = [];
        foreach ($this->columns() as $key => $c) {
            $cols[$key] = is_array($c) ? $c : ['label' => $c];
        }
        $table = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($cols as $key => $c) {
                $cells[$key] = isset($c['fn']) ? (string) $c['fn']($row) : esc((string) ($row[$key] ?? ''));
            }
            $table[] = ['id' => $row['id'], 'cells' => $cells, 'links' => $this->rowLinks($row), 'row' => $row];
        }

        return $this->render($this->indexView, [
            'title'       => $this->title,
            'singular'    => $this->singular,
            'intro'       => $this->intro,
            'base'        => $this->base,
            'cols'        => $cols,
            'table'       => $table,
            'pager'       => $pager,
            'filters'     => $filters,
            'filterDefs'  => $this->defs(),
            'searchable'  => $this->searchFields !== [],
            'archive'     => $this->archive,
            'headerLinks' => $this->headerLinks(),
            'canEdit'     => $this->canEdit(),
        ] + $this->extraIndexData($filters) + array_filter(['deleteLabel' => $this->deleteLabel, 'deleteConfirm' => $this->deleteConfirm, 'hiddenNote' => $hiddenNote]));
    }

    public function new()
    {
        if ($this->yearScoped && ($r = $this->requirePeriod())) {
            return $r;
        }

        return $this->form(null);
    }

    public function edit(int $id)
    {
        return $this->form($this->findOr404($id));
    }

    protected function form(?array $row)
    {
        $values = $this->formValues($row);
        $fields = $this->fields($row);
        foreach ($fields as $name => &$f) {
            $f['type'] ??= 'text';
            $f['required'] ??= str_contains((string) ($f['rules'] ?? ''), 'required');
            $f['value'] = old($name, $values[$name] ?? ($this->request->getGet($name) ?? ($f['default'] ?? '')), false);
        }
        unset($f);

        return $this->render($this->formView, [
            'title'    => ($row ? 'Ubah ' : 'Tambah ') . $this->singular,
            'section'  => $this->title,
            'base'     => $this->base,
            'row'      => $row,
            'fields'   => $fields,
            'action'   => $row ? site_url($this->base . '/' . $row['id']) : site_url($this->base),
            'extra'    => $this->formExtra($row),
        ]);
    }

    protected function formExtra(?array $row): array
    {
        return [];
    }

    public function create()
    {
        return $this->save(null);
    }

    public function update(int $id)
    {
        return $this->save($id);
    }

    protected function save(?int $id)
    {
        $old    = $id ? $this->findOr404($id) : null;
        $fields = $this->fields($old);

        $rules = [];
        foreach ($fields as $name => $f) {
            if (! empty($f['rules'])) {
                $rules[$name] = ['label' => $f['label'], 'rules' => str_replace('{id}', (string) ($id ?? 0), $f['rules'])] + (isset($f['errors']) ? ['errors' => $f['errors']] : []);
            }
        }
        if ($rules && ! $this->validate($rules)) {
            return $this->back('Periksa kembali isian yang ditandai.', $this->validator->getErrors());
        }

        $data = [];
        foreach ($fields as $name => $f) {
            $type = $f['type'] ?? 'text';
            if (! empty($f['virtual']) || $type === 'checkboxes' || $type === 'html') {
                continue;
            }
            if ($type === 'checkbox') {
                $data[$name] = $this->request->getPost($name) ? 1 : 0;

                continue;
            }
            $v = $this->request->getPost($name);
            $v = is_string($v) ? trim($v) : $v;
            $data[$name] = ($v === '' ? null : $v);
        }
        if ($this->yearScoped && ! $old) {
            $data['academic_year_id'] = Period::yearId();
        }
        if ($this->ownerScoped && ! $old) {
            $data['user_id'] = Auth::id();
        }

        $prepared = $this->prepare($data, $old);
        if (is_string($prepared)) {
            return $this->back($prepared);
        }

        $db = db_connect();
        $db->transException(true)->transStart();
        $m = $this->model();
        if ($old) {
            $m->update($old['id'], $prepared);
            $newId = (int) $old['id'];
        } else {
            $newId = (int) $m->insert($prepared);
        }
        $this->afterSave($newId, $prepared, $old);
        $db->transComplete();

        if ($this->audit) {
            // Jangan pernah menyimpan hash kata sandi di log audit.
            $hide = ['password_hash' => 1];
            Audit::log($old ? 'update' : 'create', $this->entity, $newId, $old ? array_diff_key($old, $hide) : null, array_diff_key($prepared, $hide));
        }

        return redirect()->to($this->redirectAfterSave($newId))
            ->with('success', $this->singular . ($old ? ' berhasil diperbarui.' : ' berhasil ditambahkan.'));
    }

    protected function redirectAfterSave(int $id): string
    {
        return site_url($this->base);
    }

    public function delete(int $id)
    {
        $row = $this->findOr404($id);
        if ($msg = $this->deleteBlocker($row)) {
            return redirect()->back()->with('error', $msg);
        }
        $this->model()->delete($id);
        Audit::log($this->archive ? 'archive' : 'delete', $this->entity, $id, array_diff_key($row, ['password_hash' => 1]));

        return redirect()->back()->with('success', $this->singular . ($this->archive ? ' diarsipkan.' : ' dihapus.'));
    }
}
