<?php
/**
 * Bagian daftar hadir di formulir agenda: cari siswa, lalu tentukan kehadirannya.
 *
 * @var list<array> $roster
 * @var int         $classId
 * @var int|null    $agendaId
 */
?>
<div aria-labelledby="att-title" id="attendance"
         data-att-url="<?= site_url('agendas/attendance') ?>" data-agenda-id="<?= esc((string) ($agendaId ?? ''), 'attr') ?>">
    <h3 id="att-title" class="sr-only">Daftar hadir siswa</h3>
    <p class="-mt-2 mb-4 text-sm text-muted">Semua siswa otomatis <strong class="text-ink">Hadir</strong>. Cari nama siswa yang tidak hadir, lalu pilih S/I/A. Status dari jam pelajaran lain di kelas &amp; tanggal yang sama ikut terisi.</p>

    <!-- Ringkasan hitungan -->
    <div class="att-counts mb-3" aria-hidden="true">
        <span class="att-count att-count-h"><b data-att-count="H">0</b> Hadir</span>
        <span class="att-count att-count-s"><b data-att-count="S">0</b> Sakit</span>
        <span class="att-count att-count-i"><b data-att-count="I">0</b> Izin</span>
        <span class="att-count att-count-a"><b data-att-count="A">0</b> Alpa</span>
    </div>
    <p id="att-summary" class="sr-only" aria-live="polite"></p>

    <!-- Bilah alat: cari + filter -->
    <div class="att-tools" data-att-tools>
        <div class="relative min-w-0 basis-full sm:basis-auto sm:flex-1">
            <label for="att-q" class="sr-only">Cari nama siswa</label>
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'size-4') ?></span>
            <input id="att-q" type="search" class="input h-10 min-h-0 pl-9 text-sm" placeholder="Cari nama siswa…" autocomplete="off" data-att-search>
        </div>
        <div class="att-tabs flex-1 sm:flex-none" role="group" aria-label="Tampilkan">
            <button type="button" data-att-filter="absent" aria-pressed="true">Tidak hadir <span data-att-tab="absent">0</span></button>
            <button type="button" data-att-filter="all" aria-pressed="false">Semua <span data-att-tab="all">0</span></button>
        </div>
        <button type="button" class="btn btn-ghost btn-sm h-10" data-att-all-present title="Tandai semua hadir"><?= icon('check', 'size-4') ?><span class="hidden sm:inline"> Semua hadir</span><span class="sr-only sm:hidden">Tandai semua hadir</span></button>
    </div>

    <div id="att-list" class="mt-3">
        <?= view('agendas/attendance_list', ['roster' => $roster, 'needClass' => ! $classId]) ?>
    </div>
    <noscript><p class="help mt-2">Tanpa JavaScript: pilih kelas lalu simpan, kemudian buka “Ubah” untuk mengisi daftar hadir.</p></noscript>
</div>
